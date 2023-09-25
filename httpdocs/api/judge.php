<?php
require_once '../../php/database.php';
require_once '../../php/endorsements.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
if (isset($_POST['judge']))
  $judge = $mysqli->escape_string($_POST['judge']);
else
  $judge = 'https://judge.directdemocracy.vote';

$query = "SELECT TO_BASE64(`key`) AS `key` FROM webservice WHERE `type`='judge' AND url=\"$judge\"";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$assoc = $result->fetch_assoc() or die("{\"error\":\"judge not found: $judge\"}");
$result->free();
$judge_key = $assoc['key'];

$query = "SELECT "
        ."UNIX_TIMESTAMP(endorsement_p.published), endorsement.revoke, endorsement.latest, citizen.familyName, citizen.givenNames, "
        ."TO_BASE64(citizen_p.signature) AS signature "
        ."FROM publication AS endorsement_p "
        ."INNER JOIN endorsement ON endorsement.id = endorsement_p.id "
        ."INNER JOIN publication AS citizen_p ON citizen_p.signature = endorsement.endorsedSignature "
        ."INNER JOIN citizen ON citizen.id = citizen_p.id "
        ."WHERE endorsement_p.`key` = FROM_BASE64('$judge_key') "
        ."ORDER BY endorsement_p.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['revoke'], 'bool');
  settype($endorsement['latest'], 'bool');
  $endorsements[] = $endorsement;
}
$result->free();
$answer = array();
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
