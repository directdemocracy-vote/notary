<?php
# This API entry is called from the app (client)
# It returns a proposal with many information
# The fingerprint or signature is a mandatory parameter

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_GET['signature']))
  $signature = sanitize_field($_GET["signature"], "base64", "signature");
elseif (isset($_GET['fingerprint']))
  $fingerprint = sanitize_field($_GET["fingerprint"], "hex", "fingerprint");
else
  die('{"error":"Missing fingerprint or signature parameter"}');

$condition = (isset($signature)) ? "publication.signature=FROM_BASE64('$signature==')" : "publication.signatureSHA1=UNHEX('$fingerprint')";

$query = "SELECT proposal.id, "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, UNIX_TIMESTAMP(proposal.deadline) AS deadline, proposal.trust, proposal.website, "
        ."proposal.participants, proposal.corpus, UNIX_TIMESTAMP(proposal.results) AS results, "
        ."webservice.url AS judge, "
        ."REPLACE(REPLACE(TO_BASE64(proposal.area), '\\n', ''), '=', '') AS area, "
        ."REPLACE(REPLACE(TO_BASE64(pa.`key`), '\\n', ''), '=', '') AS areaKey, "
        ."UNIX_TIMESTAMP(pa.published) AS areaPublished, "
        ."area.name AS areaName, "
        ."ST_AsGeoJSON(area.polygons) AS areaPolygons "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS pa ON pa.signature = proposal.area "
        ."LEFT JOIN area ON area.id = pa.id "
        ."LEFT JOIN webservice ON webservice.`key` = publication.`key` AND webservice.type='judge' "
        ."WHERE $condition";

$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$proposal = $result->fetch_assoc();
$result->free();
if (!$proposal)
  die('{"error":"Proposal not found"}');
$id = intval($proposal['id']);
unset($proposal['id']);
settype($proposal['published'], 'int');
settype($proposal['areaPublished'], 'int');
settype($proposal['secret'], 'bool');
settype($proposal['deadline'], 'int');
settype($proposal['trust'], 'int');
settype($proposal['participants'], 'int');
settype($proposal['corpus'], 'int');
if ($proposal['answers'] === '')
  unset($proposal['answers']);
else
  $proposal['answers'] = explode("\n", $proposal['answers']);
if ($proposal['question'] === '')
  unset($proposal['question']);
$proposal['areaName'] = explode("\n", $proposal['areaName']);
$polygons = json_decode($proposal['areaPolygons']);
if ($polygons->type !== 'MultiPolygon')
  die("{\"error\":\"Area without MultiPolygon: $polygons->type\"}");
$proposal['areaPolygons'] = &$polygons->coordinates;
if ($proposal['secret']) {
  $proposal['results'] = [];
  $proposal['answers'][] = ''; // abstention
  foreach($proposal['answers'] as $key => $value) {
    $query = "SELECT `count` FROM results WHERE referendum=$id AND answer=\"$value\"";
    $r = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
    $c = $r->fetch_assoc();
    $r->free();
    $proposal['results'][] = $c ? intval($c['count']) : 0;
  }
}
$mysqli->close();
$json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
die($json);
?>
