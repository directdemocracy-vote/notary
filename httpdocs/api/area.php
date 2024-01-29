<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$input = json_decode(file_get_contents("php://input"));
$judge = sanitize_field($input->judge, "base64", "judge");
if (isset($input->area)) {
  $area = $mysqli->escape_string($input->area);
  $condition = "area.name=\"$area\" AND area.local=0";
} else if (isset($input->lat) && isset($input->lon)) {
  $lat = floatval($input->lat);
  $lon = floatval($input->lon);
  $condition = "ST_Contains(area.polygons, POINT($lon, $lat)) AND area.local=1";
} else
  error("Missing area and lat/lon parameters");
if (!$judge)
  error("Missing judge argument");

$query = "SELECT UNIX_TIMESTAMP(publication.published) AS published FROM area "
        ."LEFT JOIN publication ON publication.id=area.publication "
        ."WHERE publication.`key`='$judge' AND $condition AND publication.published <= NOW()";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
if ($area)
  die("{\"published\":\"$area[published]\"}");
else
  die('{"published":"never"}');
?>
