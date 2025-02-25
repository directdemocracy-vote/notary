<?php
require_once '../../php/header.php';
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

if (isset($_GET['signature'])) {
  $signature = sanitize_field($_GET['signature'], 'base64', 'signature');
  $condition = "publication.signature=FROM_BASE64('$signature==')";
  $join_condition = "pp.signature=FROM_BASE64('$signature==')";
} elseif (isset($_GET['fingerprint'])) {
  $fingerprint = sanitize_field($_GET['fingerprint'], 'hex', 'fingerprint');
  $condition = "publication.signatureSHA1=UNHEX('$fingerprint')";
  $join_condition = "pp.signatureSHA1=UNHEX('$fingerprint')";
} else
  error("Missing fingerprint or signature GET parameter");

$query = "SELECT publication.id, proposal.title, proposal.answers FROM proposal INNER JOIN publication ON publication.id=proposal.publication AND $condition WHERE proposal.type!='petition'";
$r = $mysqli->query($query) or error($mysqli->error);
$a = $r->fetch_assoc();
$r->free();
if (!$a)
  error('proposal not found');
$response = [];
$referendum = $a['id'];
$response['title'] = $a['title'];
$answers = [];
$answers[] = '';
$response['answers'] = explode("\n", $a['answers']);
$answers = array_merge($answers, $response['answers']);
$result = [];
$query = "SELECT area.name, vote.area";
$count = 0;
foreach ($answers as &$answer) {
  $query .= ", SUM(CASE WHEN answer=\"$answer\" THEN 1 ELSE 0 END) AS a$count";
  $count++;
}
$query .= " FROM vote INNER JOIN area ON area.id=vote.area GROUP BY vote.area";
$r = $mysqli->query($query) or error($mysqli->error);
$response['areas'] = [];
while ($c = $r->fetch_assoc()) {
  $area = [];
  $area['id'] = intval($c['area']);
  $area['name'] = $c['name'];
  $area['answers'] = [];
  for($i = 0; $i < $count; $i++)
    $area['answers'][] = intval($c["a$i"]);
  $response['areas'][] = $area;
}
$r->free();
$mysqli->close();
die(json_encode($response, JSON_UNESCAPED_SLASHES));
?>
