<?php
require_once '../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));
$key = $mysqli->escape_string(get_string_parameter('key'));

$now = intval(microtime(true) * 1000);  # milliseconds
$query = "SELECT id, `schema`, `key`, signature, published, expires FROM publication WHERE published <= $now AND expires >= $now AND ";
if ($key)
  $query .= "`key`=\"$key\"";
elseif ($fingerprint)
  $query .= "fingerprint=\"$fingerprint\";";
else
  error("No fingerprint or key argument provided.");

$result = $mysqli->query($query) or error($mysqli->error);
$publication = $result->fetch_assoc();
if (!$publication)
  error("Publication not found.");
$result->free();
$publication_id = intval($publication['id']);
unset($publication['id']);
$publication['published'] = intval($publication['published']);
$publication['expires'] = intval($publication['expires']);
$type = get_type($publication['schema']);
if ($type == 'citizen') {
  $query = "SELECT familyName, givenNames, picture, ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizen = $result->fetch_assoc();
  $result->free();
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen = $publication + $citizen;
  echo json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'endorsement') {
  $query = "SELECT publicationKey, publicationSignature, revoked, message, comment FROM endorsement WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsement = $result->fetch_assoc();
  $result->free();
  $endorsement['revoke'] = (intval($endorsement['revoked']) === $publication['published']);
  unset($endorsement['revoked']);
  $endorsement = $publication + $endorsement;
  echo json_encode($endorsement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'referendum') {
  $query = "SELECT trustee, area, title, description, question, answers, deadline, website FROM referendum WHERE id=$publication_id";
  # id AS areas is just a placeholder for having areas in the right order of fields
  $result = $mysqli->query($query) or error($mysqli->error);
  $referendum = $result->fetch_assoc();
  $result->free();
  if ($referendum['website'] == '')
    unset($referendum['website']);
  $referendum['deadline'] = intval($referendum['deadline']);
  $referendum = $publication + $referendum;
  echo json_encode($referendum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'ballot') {
  $query = "SELECT referendum, stationKey, stationSignature, answer from ballot WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $ballot = $result->fetch_assoc();
  $result->free();
  $ballot = $publication + $ballot;
  echo json_encode($ballot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'area') {
  $query = "SELECT name, polygons FROM area WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $area = $result->fetch_assoc();
  // $area['polygons'] = json_decode(str_replace(['(', ')'], ['[', ']'], substr($area['polygons'], 12))); // remove MULTIPOLYGON and replace parenthesis with square brackets
  $area['polygons'] = json_decode('[10,10]');
  $result->free();
  $area = $publication + $area;
  echo json_encode($area, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
  error('Publication type not supported: ' + $type);
}
$mysqli->close();
?>
