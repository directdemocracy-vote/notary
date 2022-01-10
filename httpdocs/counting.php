<?php
require_once '../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

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
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$referendum_key = $mysqli->escape_string(get_string_parameter('referendum'));
$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));
if (!$referendum_key && !$fingerprint)
  error("Missing referendum or fingerprint argument");

if ($fingerprint)
  $condition = "publication.fingerprint=\"$fingerprint\"";
else
  $condition = "publication.`key`=\"$referendum_key\"";

$query = "SELECT referendum.id, `key`, published, expires, "
        ."trustee, area, title, description, question, answers, `secret`, deadline, website "
        ."FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

$trustee = $referendum['trustee'];
$area = $referendum['area'];
$referendum_id = intval($referendum['id']);
$referendum_key = $referendum['key'];
$referendum_published = intval($referendum['published']);
$referendum_deadline = intval($referendum['deadline']);

$results = new stdClass();
$results->key = $referendum_key;
$results->trustee = $trustee;
$results->area = $area;
$results->title = $referendum['title'];
$results->description = $referendum['description'];
$results->website = $referendum['website'];
$results->question = $referendum['question'];
$results->answers = $referendum['answers'];
$results->secret = true;  # ($referendum['secret'] === 1)?true:false;
$results->deadline = $referendum_deadline;
$results->published = $referendum_published;
$results->expires = intval($referendum['expires']);

$answers = explode("\n", $referendum['answers']);
$n_answers = count($answers);

$query = "SELECT updated, count, corpus, registrations, rejected, void FROM participation WHERE referendum=$referendum_id";
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
      $query = "SELECT answer, count FROM results WHERE referendum=$referendum_id";
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
        ."WHERE name=\"$area\" AND publication.key=\"$trustee\"";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Area was not published by trustee");
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
# the corpus table should contain all citizen entitled to vote to referendum:
# they must be endorsed by the trustee of the referendum and their home must be inside the area of the referendum


# FIXME: move this down
$mysqli->query("DELETE FROM corpus WHERE referendum=$referendum_id");
$mysqli->query("DELETE FROM stations WHERE referendum=$referendum_id");
$mysqli->query("DELETE FROM registrations WHERE referendum=$referendum_id");

$query = "INSERT INTO corpus(citizen, referendum) "
        ."SELECT DISTINCT citizen.id, $referendum_id FROM citizen "
        ."LEFT JOIN area ON ST_Contains(area.polygons, citizen.home) "
        ."INNER JOIN publication AS citizen_p ON citizen_p.id=citizen.id "
        ."INNER JOIN endorsement ON endorsement.publicationKey=citizen_p.`key` "
        ."INNER JOIN publication AS endorsement_p ON endorsement_p.id=endorsement.id "
        ."WHERE area.id=$area_id "
        ."AND endorsement_p.published < $referendum_deadline "
        ."AND endorsement.revoked > $referendum_published "
        ."AND endorsement_p.`key`=\"$trustee\"";
$mysqli->query($query) or error($mysqli->error);
$count = $mysqli->affected_rows;

$results->corpus = $count;

# list all the stations involved in the referendum
$query = "INSERT INTO stations(id, referendum, registrations_count, ballots_count) "
        ."SELECT DISTINCT station.id, $referendum_id, 0, 0 "
        ."FROM station INNER JOIN registration ON registration.stationKey=station.`key` "
        ."WHERE registration.referendum=\"$referendum_key\"";
$mysqli->query($query) or error($mysqli->error);

# list all the registrations for each station
$query = "INSERT INTO registrations(referendum, station, citizen, published) "
        ."SELECT corpus.referendum, station.id, corpus.citizen, registration_p.published "
        ."FROM corpus "
        ."INNER JOIN publication AS citizen_p ON citizen_p.id=corpus.citizen "
        ."INNER JOIN publication AS registration_p ON registration_p.`key`=citizen_p.`key` "
        ."INNER JOIN registration ON registration.id=registration_p.id "
        ."INNER JOIN station ON station.`key`=registration.stationKey "
        ."WHERE registration.referendum=\"$referendum_key\" AND corpus.referendum=$referendum_id";
$mysqli->query($query) or error($mysqli->error);

# if a citizen registered several times (possibly at several stations) keep only the most recent registration
$query = "DELETE r1 FROM registrations r1 INNER JOIN registrations r2 "
        ."WHERE r1.citizen=r2.citizen AND r1.referendum=r2.referendum AND r1.published < r2.published";
$mysqli->query($query) or error($mysqli->error);

# count participation
$query = "SELECT COUNT(citizen) AS participation FROM registrations WHERE referendum=$referendum_id";
$result = $mysqli->query($query) or error($mysqli->error);
$c = $result->fetch_assoc();
$result->free();
$results->participation = intval($c['participation']);

$query = "INSERT INTO participation (referendum, count, corpus, updated) "
        ."VALUES($referendum_id, $results->participation, $count, NOW()) "
        ."ON DUPLICATE KEY UPDATE count=$results->participation, corpus=$count, updated=NOW()";
$mysqli->query($query) or error($mysqli->error);

$results->updated = time();

# $mysqli->query("DELETE FROM corpus WHERE referendum=$referendum_id");  # the corpus table is not needed anymore after this point

$now = intval(microtime(true) * 1000);

if (intval($referendum['deadline']) > $now && $results->participation < $count) {  # we should not count ballots, but can count participation
  # $mysqli->query("DELETE FROM stations WHERE referendum=$referendum_id");
  # $mysqli->query("DELETE FROM registrations WHERE referendum=$referendum_id");
  die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$results->count = array_fill(0, $n_answers, 0);

# list all the ballots for each station
$query = "INSERT INTO ballots(referendum, station, `key`, answer) "
        ."SELECT $referendum_id, station.id, ballot_p.`key`, ballot.answer FROM station "
        ."INNER JOIN ballot ON ballot.stationKey=station.`key` AND ballot.referendum=\"$referendum_key\" "
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
$query = "SELECT COUNT(*) AS c FROM registrations WHERE referendum=$referendum_id";
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
$query = "SELECT COUNT(*) AS c FROM registrations WHERE referendum=$referendum_id";
$r = $mysqli->query($query) or error($mysqli->error);
$b = $r->fetch_assoc();
$r->free();
$results->rejected = $results->registrations - intval($b['c']);

# count ballots
$mysqli->query("DELETE FROM results WHERE referendum=$referendum_id");
$total = 0;
foreach($answers as $i => $answer) {
  $query = "SELECT COUNT(*) AS c FROM ballots WHERE answer=\"$answer\" AND referendum=$referendum_id";
  $r = $mysqli->query($query) or error($mysqli->error);
  $b = $r->fetch_assoc();
  $r->free();
  $c = intval($b['c']);
  $results->count[$i] = $c;
  $total += $c;
  $query = "INSERT INTO results(referendum, answer, `count`) VALUES($referendum_id, \"$answer\", $c)";
  $mysqli->query($query) or error($mysqli->error);
}

$results->void = $results->registrations - $results->rejected - $total;

$query = "UPDATE participation SET "
        ."registrations=$results->registrations, rejected=$results->rejected, void=$results->void, updated=NOW() "
        ."WHERE referendum=$referendum_id";
$mysqli->query($query) or error($mysqli->error);

# delete the content of intermediary tables
$mysqli->query("DELETE FROM stations WHERE referendum=$referendum_id");
$mysqli->query("DELETE FROM registrations WHERE referendum=$referendum_id");
$mysqli->query("DELETE FROM ballots WHERE referendum=$referendum_id");

$mysqli->close();
die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
