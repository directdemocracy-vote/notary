<?php
# This is a proxy to query any judge for the reputation of a citizen
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['key']))
  die('{"error":"missing key argument"}');
if (!isset($_GET['judge']))
  die('{"error":"missing judge argument"}');

$key = sanitize_field($_GET['key'], 'base64', 'key');
$judge = sanitize_field($_GET['judge'], 'base64', 'judge');

$query = "SELECT url FROM webservice INNER JOIN participant ON participant.id=webservice.participant WHERE participant.`key`=FROM_BASE64('$judge==')";
$result = $mysqli->query($query) or error($mysqli->error);
$j = $result->fetch_assoc();
$result->free();
$mysqli->close();
if (!$j)
  error('judge not found');
$url = $j['url'];
$response = file_get_contents("$url/api/reputation.php?key=".urlencode($key));
die($response);
?>
