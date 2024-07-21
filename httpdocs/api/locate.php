<?php

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['osm_ids']))
  error('Missing osm_ids parameter');

$osm_ids = explode(',', $_GET['osm_ids']);

$list = '(';
foreach ($osm_ids as $osm_id) {
  if ($list !== '(')
    $list .= ',';
  $list .= intval($osm_id);
}
$list .= ')';
$result = $mysqli->query("SELECT osm_id, ST_Y(location) AS latitude, ST_X(location) AS longitude, name FROM locality WHERE osm_id IN $list")
          or error($mysqli->error);
$answer = "{\"localities\": ";
$comma = false;
while ($f = $result->fetch_assoc()) {
  if ($comma)
    $answer .= ',';
  else
    $comma = true;
  $answer .= json_encode($f);
}
$answer .= '}';
$result->free();
$mysqli->close();
die("{\"localities\":{\"osm_id\":$osm_id,\"latitude\":$latitude,\"longitude\":$longitude,\"name\":\"$name\"}}");
?>
