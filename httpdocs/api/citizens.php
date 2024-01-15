<?php

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$radius = sanitize_field($_GET["radius"], "positive_float", "radius");
if ($radius) {
  $radius = $radius / 1000;
  $latitude = sanitize_field($_GET["latitude"], "float", "latitude");
  $longitude = sanitize_field($_GET["longitude"], "float", "longitude");
}

$familyName = isset($_GET["familyName"]) ? $mysqli->escape_string($_GET["familyName"]) : null;
$givenNames = isset($_GET["givenNames"]) ? $mysqli->escape_string($_GET["givenNames"]) : null;
$judge = isset($_GET["judge"]) ? sanitize_field($_GET["judge"], "url", "judge") : null;

$key = '';
if ($judge) {
  $result = $mysqli->query("SELECT REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM participant INNER JOIN webservice ON webservice.participantId=participant.id WHERE participant.`type`='judge' AND webservice.url='$judge'") or error($mysqli->error);
  if ($j = $result->fetch_assoc())
    $key = $j['key'];
  $result->free();
}

$query = "SELECT citizen.familyName, citizen.givenNames, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude";
if ($radius)
  $query .= ", ST_Distance_Sphere(citizen.home, POINT($longitude, $latitude), 6370.986) AS distance";
# If ST_Distance_Sphere is not available (like in MySQL 5.6), we need to revert to this formula:
#  $query .= ", (6370.986 * acos(cos(radians($latitude)) * cos(radians(ST_Y(citizen.home))) * cos(radians(ST_X(citizen.home)) - radians($longitude)) "
#           ."+ sin(radians($latitude)) * sin(radians(ST_Y(citizen.home))))) AS distance";

$query .= ", publication.`version`, publication.`type`, "
         ."REPLACE(REPLACE(TO_BASE64(publication.`key`), '\\n', ''), '=', '') AS `key`, "
         ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
         ."UNIX_TIMESTAMP(publication.published) AS published "
         ."FROM citizen "
         ."INNER JOIN publication ON publication.id = citizen.id";
if ($key)
  $query .= " INNER JOIN certificate ON certificate.publication = publication.signature AND certificate.type = 'endorse' AND certificate.latest = 1"
           ." INNER JOIN publication AS pe ON pe.id=certificate.id AND pe.`key` = FROM_BASE64('$key==')";
if ($familyName or $givenNames) {
  $query .= " WHERE";
  if ($familyName) {
    $query .= " familyName LIKE \"%$familyName%\"";
    if ($givenNames)
      $query .= " AND";
  }
  if ($givenNames)
    $query .= " givenNames LIKE \"%$givenNames%\"";
}
if ($radius)
  $query .= " HAVING distance < $radius ORDER BY distance";
$query .= " LIMIT 20;";
$result = $mysqli->query($query) or die($mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  # unset($citizen['distance']);
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen['published'] = intval($citizen['published']);
  $citizen['schema'] = 'https://directdemocracy.vote/json-schema/' . $citizen['version'] . '/' . $citizen['type'] . 'schema.json';
  unset($citizen['type']);
  $citizens[] = $citizen;
}
$result->free();
echo json_encode($citizens, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
