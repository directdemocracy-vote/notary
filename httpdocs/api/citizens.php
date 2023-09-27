<?php

require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_float_parameter($name) {
  if (isset($_GET[$name]))
    return floatval($_GET[$name]);
  if (isset($_POST[$name]))
    return floatval($_POST[$name]);
  return FALSE;
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

$radius = get_float_parameter('radius');
if ($radius) {
  $radius = $radius / 1000;
  $latitude = get_float_parameter('latitude');
  $longitude = get_float_parameter('longitude');
}
$familyName = $mysqli->escape_string(get_string_parameter('familyName'));
$givenNames = $mysqli->escape_string(get_string_parameter('givenNames'));
$judge = $mysqli->escape_string(get_string_parameter('judge'));

$key = '';
if ($judge) {
  $result = $mysqli->query("SELECT `key` FROM webservice WHERE `type`='judge' AND url='$judge'") or error($mysqli->error);
  if ($j = $result->fetch_assoc())
    $key = $j['key'];
  $result->free();
}

$query = "SELECT citizen.familyName, citizen.givenNames, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '') AS picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude";
if ($radius)  # Unfortunately, ST_Distance_Sphere is not available in MySQL 5.6, so we need to revert to this complex formula
  $query .= ", (6371 * acos(cos(radians($latitude)) * cos(radians(ST_Y(citizen.home))) * cos(radians(ST_X(citizen.home)) - radians($longitude)) "
           ."+ sin(radians($latitude)) * sin(radians(ST_Y(citizen.home))))) AS distance";
$query .= ", publication.`version`, publication.`type`,"
         ." REPLACE(TO_BASE64(publication.`key`), '\\n', '') AS `key`,"
         ." REPLACE(TO_BASE64(publication.signature), '\\n', '') AS signature,"
         ." UNIX_TIMESTAMP(publication.published) AS published"
         ." FROM citizen"
         ." INNER JOIN publication ON publication.id = citizen.id";
if ($key)
  $query .= " INNER JOIN endorsement ON endorsement.endorsedSignature = publication.signature AND endorsement.`revoke` = 0 AND endorsement.latest = 1"
           ." INNER JOIN publication AS pe ON pe.id=endorsement.id AND pe.`key` = '$key' ";
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
$query .= " LIMIT 0, 20;";
$result = $mysqli->query($query) or die($query . ' => ' . $mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  unset($citizen['distance']);
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
