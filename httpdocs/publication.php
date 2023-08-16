<?php
require_once '../php/database.php';

function error($message) {
  die("{\"error\":\"$message\"}");
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
$query = "SELECT id, `schema`, `key`, signature, published FROM publication WHERE published <= $now AND ";
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
$type = get_type($publication['schema']);
if ($type == 'citizen') {
  $query = "SELECT givenNames, familyName, picture, ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizen = $result->fetch_assoc();
  $result->free();
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen = $publication + $citizen;
  echo json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'endorsement') {
  $query = "SELECT revoked, message, comment, endorsedSignature FROM endorsement WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsement = $result->fetch_assoc();
  $result->free();
  $endorsement['revoke'] = (intval($endorsement['revoked']) === $publication['published']);
  unset($endorsement['revoked']);
  $endorsement = $publication + $endorsement;
  echo json_encode($endorsement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'proposal') {
  $query = "SELECT trustee, area, title, description, question, answers, deadline, website FROM proposal WHERE id=$publication_id";
  # id AS areas is just a placeholder for having areas in the right order of fields
  $result = $mysqli->query($query) or error($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  if ($proposal['website'] == '')
    unset($proposal['website']);
  $proposal['deadline'] = intval($proposal['deadline']);
  $proposal = $publication + $proposal;
  echo json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'ballot') {
  $query = "SELECT proposal, stationKey, stationSignature, answer from ballot WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $ballot = $result->fetch_assoc();
  $result->free();
  $ballot = $publication + $ballot;
  echo json_encode($ballot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'area') {
  $query = "SELECT name, ST_AsGeoJSON(polygons) AS polygons FROM area WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $area = $result->fetch_assoc();
  $polygons = json_decode($area['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    error("Area without MultiPolygon: $polygons->type");
  $area['polygons'] = &$polygons->coordinates;
  $result->free();
  $area = $publication + $area;
  echo json_encode($area, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
  error('Publication type not supported: ' + $type);
}
$mysqli->close();
?>
