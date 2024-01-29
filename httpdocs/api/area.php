<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$input = json_decode(file_get_contents("php://input"));
if ($input) {
  if (isset($input->judge))
    $judge = sanitize_field($input->judge, 'base64', 'judge');
  if (isset($input->area))
    $area = $mysqli->escape_string($input->area);
  if (isset($input->lat))
    $lat = floatval($input->lat);
  if (isset($input->lon))
    $lon = floatval($input->lon);
} else {
  if (isset($_GET['judge']))
    $judge = sanitize_field($_GET['judge'], 'base64', 'judge');
  if (isset($_GET['area']))
    $area = $mysqli->escape_string($_GET['area']);
  if (isset($_GET['lat']))
    $lat = floatval($_GET['lat']);
  if (isset($_GET['lon']))
    $lon = floatval($_GET['lon']);
}
if (!isset($judge) || !$judge)
  error('Missing judge argument');
if (isset($area))
  $condition = "area.name=\"$area\" AND area.local=0";
elseif (isset($lat) && isset($lon))
  $condition = "ST_Contains(area.polygons, POINT($lon, $lat)) AND area.local=1";
else
  error('Missing area or lat/lon arguments');

$query = "SELECT id FROM area "
        ."INNER JOIN publication ON publication.id=area.publication "
        ."INNER JOIN participant ON participant.id=publication.participant "
        ."WHERE participant.`key`=FROM_BASE64('$judge==') AND $condition AND publication.published <= NOW()";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
$id = $area ? intval($area['id']) : 0;
die("{\"id\":$id");
?>
