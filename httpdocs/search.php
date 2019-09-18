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

$range = get_float_parameter('range');
if ($range) {
  $latitude = get_float_parameter('latitude') / 100000;
  $longitude = get_float_parameter('longitude') / 100000;
}
$familyName = $mysqli->escape_string(get_string_parameter('familyName'));
$givenNames = $mysqli->escape_string(get_string_parameter('givenNames'));
$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));

if ($fingerprint) {
  $query = "SELECT * FROM publication WHERE fingerprint='$fingerprint';";
  $result = $mysqli->query($query) or error($mysqli->error);
  $publication = $result->fetch_assoc();
  $result->free();
  if ($publication) {
    $query = "SELECT picture, familyName, givenNames, latitude, longitude FROM citizen WHERE id=$publication[id]";
    $result = $mysqli->query($query) or error($mysqli->error);
    $citizen = $result->fetch_assoc();
    $result->free();
    $citizen = array('schema' => $publication['schema'],
                     'expires' => $publication['expires'],
                     'published' => $publication['published'],
                     'signature' => $publication['signature'],
                     'key' => $publication['key']) + $citizen;
    echo json_encode($citizen);
  } else
    error("Citizen not found: $query");
} else {
  $query = "SELECT id, picture, familyName, givenNames, latitude, longitude";
  if ($range)
    $query .= ", (6371 * acos(cos(radians(78.3232)) * cos(radians($latitude)) * cos(radians($longitude) - radians(65.3234)) "
             ."+ sin(radians(78.3232)) * sin(radians($latitude)))) as distance ";
  $query .= " FROM citizen";
  if ($range)
    $query .= " HAVING distance < $range";
  if ($familyName or $givenNames) {
    $query .= " WHERE";
    if ($familyName) {
      $query .= " familyName LIKE '%$familyName%'";
      if ($givenNames or $fingerprint)
        $query .= " AND";
    }
    if ($givenNames)
      $query .= " givenNames LIKE '%$givenNames%'";
  }
  if ($range)
    $query .= " ORDER BY distance";
  $query .= " LIMIT 0, 20;";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizens = array();
  while ($citizen = $result->fetch_assoc()) {
    $query = "SELECT `schema`, `key`, signature, published, expires FROM publication WHERE id=$citizen[id]";
    $r = $mysqli->query($query) or error($mysqli->error);
    $publication = $r->fetch_assoc();
    $r->free();
    $citizen = array('expires', $publication['expires']) + $citizen;  # insert first
    $citizen = array('published', $publication['published']) + $citizen;
    $citizen = array('signature', $publication['signature']) + $citizen;
    $citizen = array('key', $publication['key']) + $citizen;
    $citizen = array('schema', $publication['schema']) + $citizen;
    $citizens[] = $citizen;
  }
  $result->free();
  echo json_encode($citizens);
}
$mysqli->close();
?>
