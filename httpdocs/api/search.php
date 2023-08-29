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
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$range = get_float_parameter('range');
if ($range) {
  $range = $range / 1000;
  $latitude = get_float_parameter('latitude');
  $longitude = get_float_parameter('longitude');
}
$familyName = $mysqli->escape_string(get_string_parameter('familyName'));
$givenNames = $mysqli->escape_string(get_string_parameter('givenNames'));
$judge = $mysqli->escape_string(get_string_parameter('judge'));

$key = '';
if ($judge) {
  $caca = "SELECT `key` FROM judge WHERE url='$judge'";
  $result = $mysqli->query("SELECT `key` FROM judge WHERE url='$judge'") or error($mysqli->error);
  if ($j = $result->fetch_assoc())
    $key = $j['key'];
  $result->free();
}

$query = "SELECT citizen.familyName, citizen.givenNames, citizen.picture, ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude";
if ($range)  # Unfortunately, ST_Distance_Sphere is not available in MySQL 5.6, so we need to revert to this complex formula
  $query .= ", (6371 * acos(cos(radians($latitude)) * cos(radians(ST_Y(citizen.home))) * cos(radians(ST_X(citizen.home)) - radians($longitude)) "
           ."+ sin(radians($latitude)) * sin(radians(ST_Y(citizen.home))))) AS distance";
$query .= ", publication.`schema`, publication.`key`, publication.signature, publication.published";
$query .= " FROM citizen";
$query .= " INNER JOIN publication ON publication.id = citizen.id";
if ($key)
  $query .= " INNER JOIN endorsement ON endorsement.endorsedFingerprint = publication.fingerprint AND endorsement.`key` = '$key' AND endorsement.`revoke` = 0 AND endorsement.latest = 1";
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
if ($range)
  $query .= " HAVING distance < $range ORDER BY distance";
$query .= " LIMIT 0, 20;";
$result = $mysqli->query($query) or error($mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  unset($citizen['distance']);
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen['published'] = floatval($citizen['published']);
  $citizen['caca'] = $caca;
  $citizens[] = $citizen;
}
$result->free();
echo json_encode($citizens, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
