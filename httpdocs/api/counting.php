<?php
require_once '../../php/database.php';

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$proposal_key = $mysqli->escape_string(get_string_parameter('proposal'));
$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));
if (!$proposal_key && !$fingerprint)
  error("Missing proposal or fingerprint argument");

if ($fingerprint)
  $condition = "publication.signatureSHA1 = UNHEX('$fingerprint')";
else
  $condition = "publication.`key`=\"$proposal_key\"";

$query = "SELECT proposal.id, `key`, UNIX_TIMESTAMP(published) AS published, "
        ."judge, area, title, description, question, answers, `secret`, UNIX_TIMESTAMP(deadline) AS deadline, website "
        ."FROM proposal LEFT JOIN publication ON publication.id=proposal.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Proposal not found");
$proposal = $result->fetch_assoc();
$result->free();

$judge = $proposal['judge'];
$area = $proposal['area'];
$proposal_id = intval($proposal['id']);
$proposal_key = $proposal['key'];
$proposal_published = intval($proposal['published']);
$proposal_deadline = intval($proposal['deadline']);

$results = new stdClass();
$results->key = $proposal_key;
$results->judge = $judge;
$results->area = $area;
$results->title = $proposal['title'];
$results->description = $proposal['description'];
$results->website = $proposal['website'];
$results->question = $proposal['question'];
$results->answers = $proposal['answers'];
$results->secret = $proposal['secret'] === '1';
$results->deadline = $proposal_deadline;
$results->published = $proposal_published;

$answers = explode("\n", $proposal['answers']);
$n_answers = count($answers);

$query = "SELECT updated, count, corpus, registrations, rejected, void FROM participation WHERE proposal=$proposal_id";
$result = $mysqli->query($query) or error($mysqli->error);

if ($result) {
  $participation = $result->fetch_assoc();
  $result->free();
  if ($participation) {
    $updated = strtotime($participation['updated']);
    #FIXME: restore the 5 minutes delay
    #if (time() - $updated < 300)  {  # updated less than 5 minutes ago, return cached values
    if (time() - $updated < 3)  {  # updated less than 3 seconds, return cached values
      $results->corpus = intval($participation['corpus']);
      $results->participation = intval($participation['count']);
      $results->registrations = intval($participation['registrations']);
      $results->rejected = intval($participation['rejected']);
      $results->void = intval($participation['void']);
      $results->count = array_fill(0, $n_answers, 0);
      $results->updated = $updated;
      $query = "SELECT answer, count FROM results WHERE proposal=$proposal_id";
      $result = $mysqli->query($query) or error($mysqli->error);
      while ($r = $result->fetch_assoc()) {
        $i = array_search($r['answer'], $answers);
        if ($i === FALSE)
          error("Wrong answer found in results: $r[answer]");
        $results->count[$i] = intval($r['count']);
      }
      $result->free();
      die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
  }
}

$query = "SELECT area.id, ST_AsGeoJSON(polygons) AS polygons FROM area LEFT JOIN publication on publication.id=area.id "
        ."WHERE name=\"$area\" AND publication.key=\"$judge\"";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Area was not published by judge");
$a = $result->fetch_assoc();
$result->free();
$area_id = intval($a['id']);
$polygons = json_decode($a['polygons']);
if ($polygons->type !== 'MultiPolygon')
  error("Area without MultiPolygon: $polygons->type");
$results->area_polygons = &$polygons->coordinates;
# The following intermediary tables are created:
# corpus, stations, registrations and ballots

# create corpus, see https://github.com/directdemocracy-vote/doc/blob/master/voting.md#31-list-eligible-citizens
# the corpus table should contain all citizen entitled to vote to the proposal:
# they must be endorsed by the judge of the proposal and their home must be inside the area of the proposal

$query = "INSERT INTO corpus(citizen, proposal) "
        ."SELECT DISTINCT citizen.id, $proposal_id FROM citizen "
        ."LEFT JOIN area ON ST_Contains(area.polygons, citizen.home) "
        ."INNER JOIN publication AS citizen_p ON citizen_p.id=citizen.id "
        ."INNER JOIN endorsement ON endorsement.publicationKey=citizen_p.`key` "
        ."INNER JOIN publication AS endorsement_p ON endorsement_p.id=endorsement.id "
        ."WHERE area.id=$area_id "
        ."AND UNIX_TIMESTAMP(endorsement_p.published) < $proposal_deadline "
        ."AND endorsement.revoke = 0 "
        ."AND endorsement_p.`key` = \"$judge\"";
$mysqli->query($query) or error($mysqli->error);
$count = $mysqli->affected_rows;

$results->corpus = $count;

if (!$results->secret) {  # public proposal
  # count participation
  $query = "SELECT COUNT(citizen) AS participation FROM ballot INNER JOIN publication ON publication.id=ballot.id INNER JOIN corpus ON corpus.citizen=publication.`key` AND corpus.proposal=ballot.proposal WHERE ballot.proposal=$proposal_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $c = $result->fetch_assoc();
  $result->free();
  $results->participation = intval($c['participation']);

  $query = "INSERT INTO participation (proposal, count, corpus, updated) "
          ."VALUES($proposal_id, $results->participation, $count, NOW()) "
          ."ON DUPLICATE KEY UPDATE count=$results->participation, corpus=$count, updated=NOW()";
  $mysqli->query($query) or error($mysqli->error);

  $results->updated = time();
  die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

# secret proposal

$mysqli->query("DELETE FROM corpus WHERE proposal=$proposal_id");
$mysqli->query("DELETE FROM stations WHERE proposal=$proposal_id");
$mysqli->query("DELETE FROM registrations WHERE proposal=$proposal_id");

# list all the stations involved in the proposal
$query = "INSERT INTO stations(id, proposal, registrations_count, ballots_count) "
        ."SELECT DISTINCT station.id, $proposal_id, 0, 0 "
        ."FROM station INNER JOIN registration ON registration.stationKey=station.`key` "
        ."WHERE registration.proposal=\"$proposal_key\"";
$mysqli->query($query) or error($mysqli->error);

# list all the registrations for each station
$query = "INSERT INTO registrations(proposal, station, citizen, published) "
        ."SELECT corpus.proposal, station.id, corpus.citizen, registration_p.published "
        ."FROM corpus "
        ."INNER JOIN publication AS citizen_p ON citizen_p.id=corpus.citizen "
        ."INNER JOIN publication AS registration_p ON registration_p.`key`=citizen_p.`key` "
        ."INNER JOIN registration ON registration.id=registration_p.id "
        ."INNER JOIN station ON station.`key`=registration.stationKey "
        ."WHERE registration.proposal=\"$proposal_key\" AND corpus.proposal=$proposal_id";
$mysqli->query($query) or error($mysqli->error);

# if a citizen registered several times (possibly at several stations) keep only the most recent registration
$query = "DELETE r1 FROM registrations r1 INNER JOIN registrations r2 "
        ."WHERE r1.citizen=r2.citizen AND r1.proposal=r2.proposal AND r1.published < r2.published";
$mysqli->query($query) or error($mysqli->error);

# count participation
$query = "SELECT COUNT(citizen) AS participation FROM registrations WHERE proposal=$proposal_id";
$result = $mysqli->query($query) or error($mysqli->error);
$c = $result->fetch_assoc();
$result->free();
$results->participation = intval($c['participation']);

$query = "INSERT INTO participation (proposal, count, corpus, updated) "
        ."VALUES($proposal_id, $results->participation, $count, NOW()) "
        ."ON DUPLICATE KEY UPDATE count=$results->participation, corpus=$count, updated=NOW()";
$mysqli->query($query) or error($mysqli->error);

$results->updated = time();

# $mysqli->query("DELETE FROM corpus WHERE proposal=$proposal_id");  # the corpus table is not needed anymore after this point

$now = intval(microtime(true) * 1000);

if (intval($proposal['deadline']) > $now && $results->participation < $count) {  # we should not count ballots, but can count participation
  # $mysqli->query("DELETE FROM stations WHERE proposal=$proposal_id");
  # $mysqli->query("DELETE FROM registrations WHERE proposal=$proposal_id");
  die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$results->count = array_fill(0, $n_answers, 0);

# list all the ballots for each station
$query = "INSERT INTO ballots(proposal, station, `key`, answer) "
        ."SELECT $proposal_id, station.id, ballot_p.`key`, ballot.answer FROM station "
        ."INNER JOIN ballot ON ballot.stationKey=station.`key` AND ballot.proposal=\"$proposal_key\" "
        ."INNER JOIN publication AS ballot_p ON ballot_p.id=ballot.id";
$mysqli->query($query) or error($mysqli->error);

# count registrations and ballots for each station
$answers_list = '("' . join('","', $answers). '")';
$query = "UPDATE stations SET "
        ."registrations_count=(SELECT COUNT(*) FROM registrations WHERE registrations.station = stations.id), "
        ."ballots_count=(SELECT COUNT(*) FROM ballots WHERE ballots.station=stations.id "
        ."AND ballots.answer IN $answers_list)";
$mysqli->query($query) or error($mysqli->error);

# count registrations
$query = "SELECT COUNT(*) AS c FROM registrations WHERE proposal=$proposal_id";
$r = $mysqli->query($query) or error($mysqli->error);
$b = $r->fetch_assoc();
$r->free();
$results->registrations = intval($b['c']);

# delete bad stations and their ballots
$query = "DELETE FROM stations WHERE ballots_count > registrations_count";
$mysqli->query($query) or error($mysqli->error);
$query = "DELETE FROM ballots WHERE station NOT IN (SELECT id FROM stations)";
$mysqli->query($query) or error($mysqli->error);
$query = "DELETE FROM registrations WHERE station NOT IN (SELECT id FROM stations)";
$mysqli->query($query) or error($mysqli->error);

# count rejected registrations (not counted)
$query = "SELECT COUNT(*) AS c FROM registrations WHERE proposal=$proposal_id";
$r = $mysqli->query($query) or error($mysqli->error);
$b = $r->fetch_assoc();
$r->free();
$results->rejected = $results->registrations - intval($b['c']);

# count ballots
$mysqli->query("DELETE FROM results WHERE proposal=$proposal_id");
$total = 0;
foreach($answers as $i => $answer) {
  $query = "SELECT COUNT(*) AS c FROM ballots WHERE answer=\"$answer\" AND proposal=$proposal_id";
  $r = $mysqli->query($query) or error($mysqli->error);
  $b = $r->fetch_assoc();
  $r->free();
  $c = intval($b['c']);
  $results->count[$i] = $c;
  $total += $c;
  $query = "INSERT INTO results(proposal, answer, `count`) VALUES($proposal_id, \"$answer\", $c)";
  $mysqli->query($query) or error($mysqli->error);
}

$results->void = $results->registrations - $results->rejected - $total;

$query = "UPDATE participation SET "
        ."registrations=$results->registrations, rejected=$results->rejected, void=$results->void, updated=NOW() "
        ."WHERE proposal=$proposal_id";
$mysqli->query($query) or error($mysqli->error);

# delete the content of intermediary tables
$mysqli->query("DELETE FROM stations WHERE proposal=$proposal_id");
$mysqli->query("DELETE FROM registrations WHERE proposal=$proposal_id");
$mysqli->query("DELETE FROM ballots WHERE proposal=$proposal_id");

$mysqli->close();
die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
