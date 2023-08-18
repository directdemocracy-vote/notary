<?php

require_once '../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$participation = json_decode(file_get_contents("php://input"));
if (!$participation)
  error("Unable to parse JSON post");

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $participation->fingerprint;
$polygons = 'ST_GeomFromText("MULTIPOLYGON(';
$t1 = false;
foreach($participation->polygons as $polygon1) {
  if ($t1)
    $polygons .= ', ';
  $polygons .= '(';
  $t1 = true;
  $t2 = false;
  foreach($polygon1 as $polygon2) {
    if ($t2)
      $polygons .= ', ';
    $polygons .= '(';
    $t2 = true;
    $t3 = false;
    foreach($polygon2 as $coordinates) {
      if ($t3)
        $polygons .= ', ';
      $t3 = true;
      $polygons .= $coordinates[0] . ' ' . $coordinates[1];
    }
    $polygons .= ')';
  }
  $polygons .= ')';
}
$polygons .= ')")';

$query = "SELECT referendum.id, referendum.judge, publication.`key` FROM referendum "
        ."LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.fingerprint=\"$fingerprint\"";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$referendum = $result->fetch_assoc();
$referendum_id = intval($referendum['id']);
$referendum_key = $referendum['key'];
$judge = $referendum['judge'];
$result->free();

$query = "SELECT DISTINCT citizen.id, citizen.familyName, citizen.givenNames, citizen.picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude, "
        ."citizen_publication.`key`, citizen_publication.published, "
        ."registration.stationKey, registration_publication.published AS voted "
        ."FROM citizen "
        ."LEFT JOIN publication AS citizen_publication ON citizen_publication.id=citizen.id "
        ."LEFT JOIN publication AS registration_publication ON registration_publication.`key`=citizen_publication.`key` "
        ."LEFT JOIN registration ON registration.id=registration_publication.id "
        ."LEFT JOIN endorsement ON endorsement.publicationKey=citizen_publication.`key` "
        ."LEFT JOIN publication AS endorsement_publication ON endorsement_publication.id=endorsement.id "
        ."WHERE CONTAINS($polygons, home) "
        ."AND registration.referendum=\"$referendum_key\" "
        ."AND endorsement_publication.`key`=\"$judge\" "
        ."AND endorsement_publication.published < registration_publication.published "
        ."AND endorsement.revoked > registration_publication.published "
        ."ORDER BY familyName, givenNames";

$query = "SELECT DISTINCT citizen.id, citizen.familyName, citizen.givenNames, citizen.picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude, "
        ."citizen_publication.`key`, citizen_publication.published, NOW() AS voted "
        ."FROM citizen "
        ."LEFT JOIN publication AS citizen_publication ON citizen_publication.id=citizen.id "
        ."LEFT JOIN endorsement ON endorsement.publicationKey=citizen_publication.`key` "
        ."LEFT JOIN publication AS endorsement_publication ON endorsement_publication.id=endorsement.id "
        ."WHERE CONTAINS($polygons, home) "
        ."AND endorsement_publication.`key`=\"$judge\" "
        # ."AND endorsement_publication.published < registration_publication.published " < referendum.deadline
        # ."AND endorsement.revoked > registration_publication.published " > referendum.deadline
        ."ORDER BY familyName, givenNames";

$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  $citizen['id'] = intval($citizen['id']);
  $citizen['published'] = intval($citizen['published']);
  $citizen['voted'] = intval($citizen['voted']);
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizens[] = $citizen;
}
$result->free();
echo json_encode($citizens, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
