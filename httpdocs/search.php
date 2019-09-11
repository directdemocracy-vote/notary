<?php

require_once '../php/database.php';

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

$latitude = get_float_parameter('latitude') / 100000;
$longitude = get_float_parameter('longitude') / 100000;
$range = get_float_parameter('range') / 1000;
$familyName = $mysqli->escape_string(get_string_parameter('familyName'));
$givenNames = $mysqli->escape_string(get_string_parameter('givenNames'));

$query = "SELECT givenNames, familyName, latitude, longitude, picture, "
        ."(6371 * acos(cos(radians(78.3232)) * cos(radians($latitude)) * cos(radians($longitude) - radians(65.3234)) "
        ."+ sin(radians(78.3232)) * sin(radians($latitude)))) as distance FROM card HAVING distance < $range "
if ($familyName || $givenNames) {
  $query .= "WHERE ";
  if ($familyName) {
    $query .= "familyName LIKE '%$familyName%' ";
    if ($givenNames)
      $query .= "AND ";
  }
  if ($givenNames)
    $query .= "givenNames LIKE '%$givenNames%' ";
}
$query .= "ORDER BY distance LIMIT 0, 20;";
$result = $mysqli->query($query) or error($mysqli->error);
$card = $result->fetch_assoc();
$mysqli->close();
echo json_encode($card);
?>
