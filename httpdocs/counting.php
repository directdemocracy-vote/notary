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
if (!$referendum_key)
  error("Missing referendum argument");

$query = "SELECT id, area, trustee FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum_key'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

$trustee = $referendum['trustee'];
$area = $referendum['area'];
$referendum_id = $referendum['id'];

$query = "SELECT id FROM area LEFT JOIN publication on publication.id=area.id "
        ."WHERE name='$area' AND publication.key='$trustee'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Area was not published by trustee");
$area = $results->fetch_assoc();
$area_id = intval($area['id']);

# The following intermediary tables are created:
# corpus, stations, registrations, ballots and votes

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

# list all the stations involved in the referendum
$query = "INSERT INTO stations(id, referendum, station, registrations_count, ballots_count) "
        ."SELECT station, $referendum_id, 0, 0 "
        ."FROM corpus GROUP BY station HAVING referendum=$referendum_id";

# list all the registrations for each station
$query = "INSERT INTO registrations(referendum, station, citizen, published) "
        ."SELECT $referendum_id, station.id, corpus.citizen, registration_p.published FROM station "
        ."LEFT JOIN registration ON registration.stationKey=station.`key` AND registration.referendum='$referendum_key' "
        ."LEFT JOIN publication AS registration_p ON registration_p.id=registration.id "
        ."LEFT JOIN publication AS citizen_p ON citizen_p.`key`=registration_p.`key` "
        ."LEFT JOIN corpus ON corpus.citizen=citizen_p.id";

# if a citizen registered several times (possibly at several stations) keep only the most recent registration
$query = "DELETE r1 FROM registrations r1 INNER JOIN registrations r2 "
        ."WHERE r1.citizen=r2.citizen AND r1.published < r2.published";

# list all the ballots for each station
$query = "INSERT INTO ballots(referendum, station, `key`, `revoke`) "
        ."SELECT $referendum_id, station.id, ballot_p.`key`, ballot.`revoke` FROM station "
        ."LEFT JOIN ballot ON ballot.stationKey=station.`key` AND ballot.referendum='$referendum_key' "
        ."LEFT JOIN publication AS ballot_p ON ballot_p.id=ballot.id";

# count registrations for each station
$query = "UPDATE stations "
        ."LEFT JOIN registrations ON registrations.station=stations.id "
        ."SET registrations_count=COUNT(registrations.*) ";

# count ballots for each station
$query = "UPDATE stations "
        ."LEFT JOIN ballots ON ballots.station=stations.id "
        ."SET ballots_count=COUNT(ballots.*) "
        ."WHERE ballots.`revoke`=0";

# delete bad stations and their ballots
$query = "DELETE FROM stations WHERE registration_count!=ballot_count";
$query = "DELETE FROM ballots WHERE station NOT IN (SELECT id FROM stations)";

# list valid votes
$query = "INSERT INTO votes(referendum, answer) "
        ."SELECT $referendum_id, answer FROM vote "
        ."LEFT JOINT vote_p ON vote.id=vote_p.id "
        ."LEFT JOIN ballots ON ballots.`key`=vote_p.`key` "
        ."WHERE ballots.referendum=$referendum_id";

# count votes
$query = "INSERT INTO results(referendum, answer, `count`) "
        ."SELECT $referendum_id, answer, COUNT(*) FROM votes "
        ."GROUP BY answer";

# save corpus size in results
$query = "INSERT INTO results(referendum, answer, `count`) "
        ."VALUES($referendum, '', COUNT(*) FROM corpus WHERE referendum=$referendum)";

# delete the content of intermediary table for referendum
# TODO: this should done once tested

$mysqli->close();
die("{\"status\":\"OK\"}");
?>
