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

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
$query = "SELECT title FROM proposal "
        ."INNER JOIN publication ON publication.id=proposal.id AND publication.fingerprint='${fingerprint}'";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$title = $result->fetch_assoc();
$result->free();
if (!$title)
  error("Proposal not found");
$answer = array();
$answer['title'] = $title['title'];
$query = "SELECT pc.fingerprint, citizen.givenNames, citizen.familyName, citizen.picture "
        ."FROM citizen "
        ."INNER JOIN publication AS pc ON pc.id=citizen.id "
        ."INNER JOIN publication AS pp ON pp.fingerprint='$fingerprint' "
        ."INNER JOIN proposal ON proposal.id=pp.id "
        ."INNER JOIN judge ON judge.url=proposal.judge "
        ."INNER JOIN publication AS pe ON pe.`key`=judge.`key` "
        ."INNER JOIN endorsement ON endorsement.id=pe.id AND endorsement.latest=1 AND endorsement.`revoke`=0 AND endorsement.endorsedFingerprint=pc.fingerprint "
        ."INNER JOIN publication AS pa ON proposal.area=pa.`signature` "
        ."INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home))) "
        ."INNER JOIN endorsement AS signature ON signature.endorsedFingerprint='$fingerprint' "
        ."INNER JOIN publication AS ps ON ps.id=signature.id AND ps.`key`=pc.`key`";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$participants = array();
while ($participant = $result->fetch_assoc())
  $participants[] = $participant;
$result->free();
$answer['participants'] = $participants;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>