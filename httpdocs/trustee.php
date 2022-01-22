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
if (isset($_POST['trustee']))
  $trustee = $mysqli->escape_string($_POST['trustee']);
else
  $trustee = 'https://trustee.directdemocracy.vote';

$query = "SELECT `key` FROM trustee WHERE url=\"$trustee\"";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$assoc = $result->fetch_assoc() or die("{\"error\":\"trustee not found: $trustee\"}");
$result->free();
$trustee_key = $assoc['key'];

$query = "SELECT "
        ."endorsement_p.published, endorsement_p.expires, endorsement.`revoke`, citizen.familyName, citizen.givenNames, "
        ."FROM publication AS endorsement_p "
        ."INNER JOIN endorsement ON endorsement.id = endorsement_p.id "
        ."INNER JOIN publication AS citizen_p ON citizen_p.fingerprint = endorsement.publicationFingerprint "
        ."INNER JOIN citizen ON citizen.id = citizen_p.id "
        ."WHERE endorsement_p.`key`=\"$trustee_key\"";
die($query);
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['expires'], 'int');
  settype($endorsement['revoke'], 'bool');
  $endorsements[] = $endorsement;
}
$result->free();
$answer = array();
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
