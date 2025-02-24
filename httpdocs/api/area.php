<?php
require_once '../../php/header.php';
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

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

$query = "SELECT "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."area.id, "
        ."area.name, "
        ."ST_AsGeoJSON(area.polygons) AS polygons, "
        ."area.local "
        ."FROM area "
        ."INNER JOIN publication ON publication.id=area.publication "
        ."INNER JOIN participant ON participant.id=publication.participant "
        ."WHERE participant.`key`=FROM_BASE64('$judge==') AND $condition "
        ."ORDER BY publication.published DESC LIMIT 1";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
if ($area) {
  $area['published'] = intval($area['published']);
  $area['id'] = intval($area['id']);
  $area['name'] = explode("\n", $area['name']);
  $polygons = json_decode($area['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    error("area without MultiPolygon: $polygons->type");
  $area['polygons'] = &$polygons->coordinates;
  $area['local'] = $area['local'] == 1 ? true : false; 
  die(json_encode($area, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
} else
  die('{"id":0}');
?>
