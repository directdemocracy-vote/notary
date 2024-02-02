<?php
# This is a proxy to query any judge for the reputation of a citizen
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['judge']))
  die('{"error":"missing judge argument"}');
if (!isset($_GET['lat']))
  die('{"error":"missing lat argument"}');
if (!isset($_GET['lon']))
  die('{"error":"missing lon argument"}');

$judge = sanitize_field($_GET['judge'], 'base64', 'judge');
$lat = sanitize_field($_GET['lat'], 'float', 'lat');
$lon = sanitize_field($_GET['lon'], 'float', 'lon');

$query = "SELECT url FROM webservice INNER JOIN participant ON participant.id=webservice.participant WHERE participant.`key`=FROM_BASE64('$judge==')";
$result = $mysqli->query($query) or error($mysqli->error);
$j = $result->fetch_assoc();
$result->free();
$mysqli->close();
if (!$j)
  error('judge not found');
$url = $j['url'];
die(file_get_contents("$url/api/publish_area.php?key=".urlencode($key)."&lat=$lat&lon=$lon"));
?>
