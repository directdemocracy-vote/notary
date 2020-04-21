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
  $condition = "publication.fingerprint='$fingerprint'";
else
  $condition = "publication.`key`='$referendum_key'";

$query = "SELECT referendum.id, `key`, published, expires, "
        ."trustee, area, title, description, question, answers, deadline, website "
        ."FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

$trustee = $referendum['trustee'];
$area = $referendum['area'];
$referendum_id = $referendum['id'];

$query = "SELECT area.id FROM area LEFT JOIN publication on publication.id=area.id "
        ."WHERE name='$area' AND publication.key='$trustee'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Area was not published by trustee");
$area = $result->fetch_assoc();
$area_id = intval($area['id']);

# The following intermediary tables are created:
# corpus, stations, registrations and ballots
$query = "SELECT COUNT(*) AS c FROM corpus WHERE referendum=$referendum_id";
$result = $mysqli->query($query) or error($mysqli->error);
$c = $result->fetch_assoc();
if ($c)
  $count = $c['c'];
else {
  # create corpus, see https://github.com/directdemocracy-vote/doc/blob/master/voting.md#31-list-eligible-citizens
  # the corpus table should contain all citizen entitled to vote to referendum:
  # they must be endorsed by the trustee of the referendum and their home must be inside the area of the referendum
  $query = "INSERT INTO corpus(referendum, station, citizen) SELECT $referendum_id, 0, citizen.id FROM "
          ."citizen "
          ."LEFT JOIN publication AS citizen_p ON citizen_p.id=citizen.id "
          ."LEFT JOIN endorsement ON endorsement.publicationKey=citizen_p.`key` AND endorsement.`revoke`=0 "
          ."LEFT JOIN publication AS endorsement_p ON endorsement_p.id=endorsement.id AND endorsement_p.`key`='$trustee' "
          ."LEFT JOIN area ON area.id=$area_id "
          ."WHERE ST_Contains(area.polygons, citizen.home)";
  $mysqli->query($query) or error($mysqli->error);
  $count = $mysqli->affected_rows;
}
$now = intval(microtime(true) * 1000);

$results = new stdClass();
$results->key = $referendum['key'];
$results->trustee = $referendum['trustee'];
$results->area = $referendum['area'];
$results->title = $referendum['title'];
$results->description = $referendum['description'];
$results->question = $referendum['question'];
$results->answers = $referendum['answers'];
$results->deadline = intval($referendum['deadline']);
$results->published = intval($referendum['published']);
$results->expires = intval($referendum['expires']);
$results->corpus = $count;

if (intval($referendum['deadline']) > $now) {  # we should not count ballots, but can count participation
  die(json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

# list all the stations involved in the referendum
$query = "INSERT INTO stations(id, referendum, registrations_count, ballots_count) "
        ."SELECT station, $referendum_id, 0, 0 "
        ."FROM corpus GROUP BY id HAVING referendum=$referendum_id";
$mysqli->query($query) or error($mysqli->error);

# list all the registrations for each station
$query = "INSERT INTO registrations(referendum, station, citizen, published) "
        ."SELECT $referendum_id, station.id, corpus.citizen, registration_p.published FROM station "
        ."LEFT JOIN registration ON registration.stationKey=station.`key` AND registration.referendum='$referendum_key' "
        ."LEFT JOIN publication AS registration_p ON registration_p.id=registration.id "
        ."LEFT JOIN publication AS citizen_p ON citizen_p.`key`=registration_p.`key` "
        ."LEFT JOIN corpus ON corpus.citizen=citizen_p.id";
$mysqli->query($query) or error($mysqli->error);

# if a citizen registered several times (possibly at several stations) keep only the most recent registration
$query = "DELETE r1 FROM registrations r1 INNER JOIN registrations r2 "
        ."WHERE r1.citizen=r2.citizen AND r1.published < r2.published";
$mysqli->query($query) or error($mysqli->error);

# list all the ballots for each station
$query = "INSERT INTO ballots(referendum, station, `key`, `revoke`) "
        ."SELECT $referendum_id, station.id, ballot_p.`key`, ballot.`revoke` FROM station "
        ."LEFT JOIN ballot ON ballot.stationKey=station.`key` AND ballot.referendum='$referendum_key' "
        ."LEFT JOIN publication AS ballot_p ON ballot_p.id=ballot.id";
$mysqli->query($query) or error($mysqli->error);

# count registrations for each station
$query = "UPDATE stations "
        ."LEFT JOIN registrations ON registrations.station=stations.id "
        ."SET registrations_count=COUNT(registrations.*) ";
$mysqli->query($query) or error($mysqli->error);

# count ballots for each station
$query = "UPDATE stations "
        ."LEFT JOIN ballots ON ballots.station=stations.id "
        ."SET ballots_count=COUNT(ballots.*) "
        ."WHERE ballots.`revoke`=0";
$mysqli->query($query) or error($mysqli->error);

# delete bad stations and their ballots
$query = "DELETE FROM stations WHERE registration_count!=ballot_count";
$mysqli->query($query) or error($mysqli->error);
$query = "DELETE FROM ballots WHERE station NOT IN (SELECT id FROM stations)";
$mysqli->query($query) or error($mysqli->error);

# list valid votes
$query = "INSERT INTO votes(referendum, answer) "
        ."SELECT $referendum_id, answer FROM vote "
        ."LEFT JOINT vote_p ON vote.id=vote_p.id "
        ."LEFT JOIN ballots ON ballots.`key`=vote_p.`key` "
        ."WHERE ballots.referendum=$referendum_id";
$mysqli->query($query) or error($mysqli->error);

# count votes
$query = "INSERT INTO results(referendum, answer, `count`) "
        ."SELECT $referendum_id, answer, COUNT(*) FROM votes "
        ."GROUP BY answer";
$mysqli->query($query) or error($mysqli->error);

# save corpus size in results
$query = "INSERT INTO results(referendum, answer, `count`) "
        ."VALUES($referendum_id, '', $count)";
$mysqli->query($query) or error($mysqli->error);

# delete the content of intermediary table for referendum
# TODO: this should done once tested

$mysqli->close();
die("{\"status\":\"OK\"}");
?>
