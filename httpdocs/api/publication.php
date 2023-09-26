<?php
require_once '../../php/database.php';

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

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$fingerprint = $mysqli->escape_string(get_string_parameter('fingerprint'));
$key = $mysqli->escape_string(get_string_parameter('key'));

$query = "SELECT id, CONCAT('https://directdemocracy.vote/json-schema/', `version`, '/', `type`, '.schema.json') AS `schema`, `type`, "
        ."REPLACE(TO_BASE64(`key`), '\\n', '') AS `key`, REPLACE(TO_BASE64(signature), '\\n', '') AS signature, UNIX_TIMESTAMP(published) AS published "
        ."FROM publication WHERE published <= NOW() AND ";
if ($key)
  $query .= "`key` = FROM_BASE64('$key') ORDER BY published ASC";  # take the first publication from the key, e.g., the citizen publication
elseif ($fingerprint)
  $query .= "SHA1(TO_BASE64(signature))='$fingerprint'";
else
  error("No fingerprint or key argument provided.");
$result = $mysqli->query($query) or error($mysqli->error);
$publication = $result->fetch_assoc();
if (!$publication)
  error("Publication not found. $query");
$result->free();
$publication_id = intval($publication['id']);
unset($publication['id']);
$publication['published'] = intval($publication['published']);
$type = $publication['type'];
unset($publication['type']);
if ($type == 'citizen') {
  $query = "SELECT givenNames, familyName, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(picture), '\\n', '') AS picture, ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizen = $result->fetch_assoc();
  $result->free();
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen = $publication + $citizen;
  echo json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'endorsement') {
  $query = "SELECT revoke, message, comment, endorsedSignature FROM endorsement WHERE id=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsement = $result->fetch_assoc();
  $result->free();
  $endorsement['revoke'] = (intval($endorsement['revoke']) === 1);
  $endorsement = $publication + $endorsement;
  echo json_encode($endorsement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type == 'proposal') {
  $query = "SELECT judge, area, title, description, question, answers, deadline, website FROM proposal WHERE id=$publication_id";
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
  $query = "SELECT REPLACE(TO_BASE64(stationKey), '\\n', ''), REPLACE(TO_BASE64(stationSignature), '\\n', ''), answer from ballot WHERE id=$publication_id";
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
