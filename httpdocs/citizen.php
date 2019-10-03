<?php
require_once '../php/database.php';
require_once '../php/endorsements.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$key = $mysqli->escape_string($_POST['key']);
$query = "SELECT publication.published, publication.expires, publication.signature, "
        ."citizen.familyName, citizen.givenNames, citizen.picture, "
        ."citizen.latitude, citizen.longitude "
        ."FROM publication INNER JOIN citizen ON publication.id = citizen.id "
        ."WHERE publication.`key` = '$key'";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $key\"}");
$result->free();
settype($citizen['published'], 'int');
settype($citizen['expires'], 'int');
settype($citizen['latitude'], 'int');
settype($citizen['longitude'], 'int');
$endorsements = endorsements($mysqli, $key);
$query = "SELECT pc.fingerprint, pe.published, e.revoke, "
        ."c.familyName, c.givenNames, c.picture FROM "
        ."publication pe INNER JOIN endorsement e ON pe.id = e.id, "
        ."publication pc INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE e.publicationKey = '$key' AND pc.`key` = pe.`key` "
        ."ORDER BY e.revoke ASC, pe.published, c.familyName, c.givenNames";
$result = $mysqli->query($query);
if (!$result)
  return "{\"error\":\"$mysqli->error\"}";
$citizen_endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['published'], 'int');
  settype($e['revoke'], 'bool');
  $citizen_endorsements[] = $e;
}
$result->free();
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
$answer['citizen_endorsements'] = $citizen_endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES));
?>
