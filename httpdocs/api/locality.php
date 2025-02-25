<?php
require_once '../../php/header.php';
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

if (!isset($_GET['locality']))
  error('Missing locality parameter');
if (!isset($_GET['judge']))
  error('Missing judge parameter');

$locality = sanitize_field($_GET['locality'], 'positive_int', 'locality');
$judge = sanitize_field($_GET['judge'], 'url', 'judge');

$result = $mysqli->query(
   "SELECT REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM participant "
  ."INNER JOIN webservice ON webservice.participant=participant.id "
  ."WHERE participant.`type`='judge' AND webservice.url='$judge'") or error($mysqli->error);
if ($j = $result->fetch_assoc())
  $key = $j['key'];
$result->free();

$query = "SELECT COUNT(*) AS count FROM citizen WHERE status='active' AND locality=$locality";
$result = $mysqli->query($query) or die($mysqli->error);
$c = $result->fetch_assoc();
$untrusted = $c['count'];
$result->free();
$mysqli->close();
die("{\"trusted-citizens\":0,\"untrusted-citizens\":$untrusted,\"referendums\":0,\"petitions\":0}");
?>
