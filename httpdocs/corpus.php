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

$comment = '';
$referendum_key = $mysqli->escape_string(get_string_parameter('referendum'));
if (!$referendum_key)
  die("Missing referendum argument");

$now = floatval(microtime(true) * 1000);  # milliseconds
$date_condition = "publication.published <= $now AND publication.expires >= $now";

$query = "SELECT id, area, trustee FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum_key' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

$trustee = $referendum['trustee'];
$area = $referendum['area'];
$referendum_id = $referendum['id'];

$query = "SELECT id FROM area LEFT JOIN publication on publication.id=area.id "
        ."WHERE name='$area' AND publication.key='$trustee' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("Area was not published by trustee");
$area = $results->fetch_assoc();
$area_id = intval($area['id']);

# create corpus, see https://github.com/directdemocracy-vote/doc/blob/master/voting.md#31-list-eligible-citizens
# the corpus table should contain all citizen entitled to vote to referendum:
# they must be endorsed by the trustee of the referendum and their home must be inside the area of the referendum
$query = "INSERT INTO corpus(referendum, station, citizen, answer) SELECT $referendum_id, 0, citizen.id, 0 FROM "
        ."citizen "
        ."LEFT JOIN publication AS citizen_p ON citizen_p.id=citizen.id "
        ."LEFT JOIN endorsement ON endorsement.publicationKey=citizen_p.`key` AND endorsement.revoke=0 "
        ."LEFT JOIN publication AS endorsement_p ON endorsement_p.id=endorsement.id AND endorsement_p.`key`='$trustee' "
        ."LEFT JOIN area ON area.id=$area_id "
        ."WHERE ST_Contains(area.polygons, citizen.home)";
$mysqli->query($query) or error($mysqli->error);
$count = $mysqli->affected_rows;

# list all the stations involved in referendum
$query = "INSERT INTO stations(id, referendum, station, registrations_count, ballots_count) SELECT station, referendum, 0, 0 "
        ."FROM corpus GROUP BY station HAVING referendum=$referendum_id";

# list all the registrations for each station, the revoke field is set if revoked by station or by citizen
$query = "INSERT INTO registrations(referendum, station, citizen, published, `revoke`) "
        ."SELECT $referendum_id, station.id, citizen.id, registration_p.published, registration.rejected FROM station "
        ."LEFT JOIN registration ON registration.stationKey=station.`key` AND registration.referendum='$referendum_key' "
        ."LEFT JOIN publication AS registration_p ON registration_p.id=registration.id "
        ."LEFT JOIN publication AS citizen_p ON citizen_p.`key`=registration_p.`key` "
        ."LEFT JOIN citizen AS citizen.id=citizen_p.id "
        ."ORDER BY registration_p.published DESC, registration.`revoke` LIMIT 1";

# if a citizen registered several times (possibly at several stations) keep only the most recent registration
$query = "UPDATE r1 FROM registrations r1, registrations r2 SET `revoke`=2 "
        ."WHERE r1.published < r2.published "
        ."AND r1.citizen=r2.citizen AND r1.revoke=0 AND r2.revoke=0 AND r1.station!=r2.station";


# list all the ballots for each station, the revoke field is set if revoked by station or by citizen
$query = "INSERT INTO ballots(referendum, station, `key`, published, `revoke`) "
        ."SELECT $referendum_id, station.id, ballot_p.`key`, ballot_p.published, ballot.rejected FROM station "
        ."LEFT JOIN ballot ON ballot.stationKey=station.`key` AND ballot.referendum='$referendum_key' "
        ."LEFT JOIN publication AS ballot_p ON ballot_p.id=ballot.id "
        ."ORDER BY ballot_p.published DESC, ballot.`revoke` LIMIT 1";

# if a ballot was published several times (possibly at several stations) revoke them all
$query = "UPDATE b1 FROM ballots b1, ballots b2 SET `revoke`=2 "
        ."WHERE b1.`key`=b2.`key` AND b1.revoke=0 AND b2.revoke=0";

$query = "UPDATE stations "
        ."LEFT JOIN ballots ON ballots.station=stations.id "
        ."LEFT JOIN registrations ON registrations.station=stations.id "
        ."SET registrations_count=COUNT(registrations.*), ballots_count=COUNT(ballots.*) "
        ."WHERE registrations.`revoke`=0 AND ballots.`revoke`=0"; // "AND registrations.station=ballot.station"; ?

$query = "DELETE FROM registrations WHERE station IN (SELECT id FROM stations WHERE registrations_count!=ballots_count)";
$query = "DELETE FROM ballots WHERE station IN (SELECT id FROM stations WHERE registrations_count!=ballots_count)";

$query = "INSERT INTO votes(referendum, answer) "
        ."SELECT $referendum_id, answer FROM vote "
        ."LEFT JOINT vote_p ON vote.id=vote_p.id "
        ."LEFT JOIN ballots ON ballots.`key`=vote_p.`key` "
        ."WHERE ballots.referendum=$referendum_id";

$query = "INSERT INTO `count`(referendum, answer, `count`) SELECT $referendum_id, votes.answer, COUNT(votes.*) FROM votes "
        ."WHERE ";

SELECT $referendum_id, station.id, citizen.id, 0 FROM "
        ."citizen "
        ."LEFT JOIN publication AS citizen_p ON citizen_p.id=citizen.id "
        ."LEFT JOIN publication AS registration_p ON registration_p.id=citizen_p.id "
        ."LEFT JOIN registration ON registration.id=registration_p.id "
        ."LEFT JOIN endorsement ON endorsement.publicationKey=citizen_p.`key` AND endorsement.revoke=0 "
        ."LEFT JOIN publication AS endorsement_p ON endorsement_p.id=endorsement.id AND endorsement_p.`key`='$trustee' "
        ."LEFT JOIN station ON station.`key`=registration.stationKey "
        ."LEFT JOIN area ON area.id=$area_id "
        ."WHERE ST_Contains(area.polygons, citizen.home)";
$mysqli->query($query) or error($mysqli->error);
$count = $mysqli->affected_rows;
$citizens = $result->fetch_row();
$result->free();
$list = "'$citizen[0]'";
for($i = 1; i < $count; $i++)
  $list .= ",'$citizen[$i]";  # maybe use SHA1 to speed-up things and lower query length
$query = "SELECT DISTINCT registration.stationKey LEFT JOIN publication ON publication.id=registration.id "
        ."WHERE publication.key IN ($list) AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)  # no citizen in the corpus actually voted
  die("{\"corpus\":$count}");
$stations = $result->fetch_row();
$results->free();

# list valid stations, see https://github.com/directdemocracy-vote/doc/blob/master/voting.md#32-list-valid-stations
$citizens = array();
$valid_stations = array();
foreach($stations as $station) {  # for each station
  # list all citizen who registered there
  $query = "SELECT publication.`key` FROM publication LEFT JOIN registration ON registration.id=publication.id "
          ."WHERE registration.referendum='$referendum_key' AND registration.stationKey='$station' AND $date_condition";
  $result = $mysqli->query($query) or error($mysqli->error);
  if (!$results)
    error("Station citizen mismatch");
  $registrations = $results->fetch_row();
  $results->free();
  # TODO: remove cancelled registrations (ABab)
  # TODO: remove rejected registrations ((RSBb + RSAa)s)
  # check that ballot count equals registration count
  $query = "SELECT publication.key FROM publication LEFT JOIN ballot ON ballot.id=publication.id "
          ."WHERE registration.referendum='$referendum_key' AND registration.stationKey='$station' AND $date_condition";
  $result = $mysqli->query($query) or error($mysqli->error);
  if (!$results)
    $comment .= "station " . SHA1($station) . " published no ballot\n";
  $ballots = $results->fetch_row();
  # TODO: remove rejected ballots ((RSBb + RSAa)s)
  $n_ballots = count($ballots);
  $n_registrations = count($registrations);
  if ($n_ballots != $n_registrations)
    $comment .= "station " . SHA1($station) . " has a mismatch of ballots ($n_ballots) and registrations ($n_registrations).\n";
  else
    array_push($valid_stations, array('keys' => $stations, 'registrations' => $registrations, 'ballots' => $ballots));
}
$new_valid_stations = array();
foreach($valid_stations as $station) {  # eliminate duplicate votes

}

$response = array('count' => $count, 'corpus' => $citizens);



$query = "SELECT publication.`schema`, publication.`key`, publication.signature, publication.published, publication.expires, "
        ."ballot.referendum, ballot.stationKey, ballot.stationSignature "
        ."FROM ballot LEFT JOIN publication ON publication.id=ballot.id "
        ."WHERE ballot.referendum='$referendum'"; // AND published <= $now AND expires >= $now"; FIXME
$result = $mysqli->query($query) or error($mysqli->error);
$ballots = [];
if ($result) {
  while ($ballot = $result->fetch_assoc()) {
    $ballot['published'] = floatval($ballot['published']);
    $ballot['expires'] = floatval($ballot['expires']);
    $station_key = $ballot['stationKey'];
    $station_signature = $ballot['stationSignature'];
    unset($ballot['stationKey']);
    unset($ballot['stationSignature']);
    $ballot['station'] = array('key' => $station_key, 'signature' => $station_signature);
    array_push($ballots, $ballot);
  }
  $result->free();
}
$mysqli->close();
$response = array('registrations' => $registrations, 'ballots' => $ballots);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
