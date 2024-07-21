<?php

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['osm_id']))
  error('Missing osm_id parameter');

$osm_id = sanitize_field($_GET['osm_id'], 'positive_int', 'osm_id');

$result = $mysqli->query("SELECT ST_Y(location) AS latitude, ST_X(location) AS longitude, name FROM locality WHERE osm_id=$osm_id")
          or error($mysqli->error);
if ($f = $result->fetch_assoc()) {
  $latitude = $f['latitude'];
  $longitude = $f['longitude'];
  $name = $f['name'];
} else
  die("\"error\": \"osm_id not found in database\"}");
$result->free();
$mysqli->close();
die("{\"locality\":{\"osm_id\":$osm_id,\"latitude\":$latitude,\"longitude\":$longitude,\"name\":\"$name\"}}");
?>
