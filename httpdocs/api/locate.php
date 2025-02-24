<?php
require_once '../../php/header.php';
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

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
$answer = "{\"localities\":[";
$comma = false;
while ($f = $result->fetch_assoc()) {
  $f['osm_id'] = intval($f['osm_id']);
  $f['latitude'] = floatval($f['latitude']);
  $f['longitude'] = floatval($f['longitude']);
  if ($comma)
    $answer .= ',';
  else
    $comma = true;
  $answer .= json_encode($f);
}
$answer .= ']}';
$result->free();
$mysqli->close();
die($answer);
?>
