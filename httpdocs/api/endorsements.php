<?php
require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['fingerprint']))
  error("Missing fingerprint parameter");
if (!isset($_GET['judge']))
  error("Missing judge parameter");

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
$judge = $mysqli->escape_string($_GET['judge']);

$query = "SELECT citizen.givenNames, citizen.familyName FROM citizen INNER JOIN publication ON publication.id=citizen.id AND publication.fingerprint='$fingerprint'";
$result = $mysqli->query($query) or error($mysqli->error);
$citizen = $result->fetch_assoc();
$result->free();
$answer = array();
$answer['givenNames'] = $citizen['givenNames'];
$answer['familyName'] = $citizen['familyName'];
$query = "SELECT publication.published, endorsement.`revoke`, endorsement.latest FROM publication"
        ." INNER JOIN judge ON judge.`key`=publication.`key` AND judge.url='$judge'"
        ." INNER JOIN endorsement ON endorsement.id=publication.id AND endorsement.endorsedFingerprint='$fingerprint'"
        ." ORDER BY publication.published DESC";
$result = $mysqli->query($query) or error($mysqli->error);
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['revoked'], 'int');
  settype($endorsement['latest'], 'int');
  $endorsements[] = $endorsement;
}
$result->free();
$answer['endorsements'] = $endorsements;
$answer['query'] = $query;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>