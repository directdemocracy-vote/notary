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
if (isset($_POST['key']))
  $condition = "publication.`key`='" . $mysqli->escape_string($_POST['key']) . "'";
else if (isset($_POST['fingerprint']))
  $condition = "publication.fingerprint='" . $mysqli->escape_string($_POST['fingerprint']) . "'";
else
  die("{\"error\":\"missing key or fingerprint POST argument\"}");
$query = "SELECT publication.`key`, publication.published, publication.signature, "
        ."citizen.familyName, citizen.givenNames, citizen.picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude "
        ."FROM publication INNER JOIN citizen ON publication.id = citizen.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $condition\"}");
$result->free();
settype($citizen['published'], 'int');
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
$endorsements = endorsements($mysqli, $citizen['key']);
$query = "SELECT pc.id, pc.fingerprint, pe.published, e.`revoke`, "
        ."c.familyName, c.givenNames, c.picture, ST_Y(home) AS latitude, ST_X(home) AS longitude "
        ."FROM publication pe "
        ."INNER JOIN endorsement e ON e.id = pe.id "
        ."INNER JOIN publication pc ON pc.`key` = pe.`key` "
        ."INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE e.endorsedSignature = '$citizen[signature]' "
        ."ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
if (!$result)
  die("{\"error\":\"$mysqli->error\"}");
$citizen_endorsements = array();
$already = array();
while($e = $result->fetch_assoc()) {
  if (in_array($e['id'], $already))
    continue;
  $already[] = $e['id'];
  unset($e['id']);
  settype($e['published'], 'int');  
  $e['revoke'] = (intval($e['revoke']) == 1);
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  $citizen_endorsements[] = $e;
}
$result->free();
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
$answer['citizen_endorsements'] = $citizen_endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
