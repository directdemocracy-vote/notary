<?php

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['commune']))
  error('Missing commune parameter');
if (!isset($_GET['judge']))
  error('Missing judge parameter');

$commune = sanitize_field($_GET['commune'], 'positive_int', 'commune');
$judge = sanitize_field($_GET['judge'], 'url', 'judge');

$result = $mysqli->query("SELECT REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM participant INNER JOIN webservice ON webservice.participant=participant.id WHERE participant.`type`='judge' AND webservice.url='$judge'") or error($mysqli->error);
if ($j = $result->fetch_assoc())
  $key = $j['key'];
$result->free();

$query = "SELECT COUNT(*) AS count FROM citizen WHERE status='active' AND commune=$commune";
$result = $mysqli->query($query) or die($mysqli->error);
$c = $result->fetch_assoc();
$count = $c['count'];
$result->free();
$mysqli->close();
die("{\"inactive-citizens\":$count}");
?>
