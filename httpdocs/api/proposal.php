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
  $signature = sanitize_field($_GET['signature'], 'base64', 'signature');
elseif (isset($_GET['fingerprint']))
  $fingerprint = sanitize_field($_GET['fingerprint'], 'hex', 'fingerprint');
else
  die('{"error":"Missing fingerprint or signature parameter"}');

$citizen = isset($_GET['citizen']) ? sanitize_field($_GET['citizen'], 'base64', 'citizen signature') : false;

$condition = (isset($signature)) ? "publication.signature=FROM_BASE64('$signature==')" : "publication.signatureSHA1=UNHEX('$fingerprint')";

$query = "SELECT publication.id, "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.type, proposal.secret, UNIX_TIMESTAMP(proposal.deadline) AS deadline, proposal.trust, proposal.website, "
        ."proposal.participants, proposal.corpus, UNIX_TIMESTAMP(proposal.results) AS results, "
        ."webservice.url AS judge, "
        ."proposal.area, "
        ."REPLACE(REPLACE(TO_BASE64(participantArea.`key`), '\\n', ''), '=', '') AS areaKey, "
        ."REPLACE(REPLACE(TO_BASE64(pa.signature), '\\n', ''), '=', '') AS areaSignature, "
        ."UNIX_TIMESTAMP(pa.published) AS areaPublished, "
        ."area.name AS areaName, "
        ."ST_AsGeoJSON(area.polygons) AS areaPolygons, "
        ."area.local AS areaLocal, "
        ."participant.id AS judgeParticipant "
        ."FROM proposal "
        ."INNER JOIN publication ON publication.id = proposal.publication "
        ."INNER JOIN area ON area.id = proposal.area "
        ."INNER JOIN publication AS pa ON pa.id = area.publication "
        ."INNER JOIN participant ON participant.id = publication.participant AND participant.type='judge' "
        ."INNER JOIN participant AS participantArea ON participantArea.id = pa.participant "
        ."INNER JOIN webservice ON webservice.participant=participant.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or error($mysqli->error);
$proposal = $result->fetch_assoc();
$result->free();
if (!$proposal)
  die('{"error":"Proposal not found"}');
$id = intval($proposal['id']);
unset($proposal['id']);
settype($proposal['published'], 'int');
settype($proposal['areaPublished'], 'int');
settype($proposal['area'], 'int');
settype($proposal['secret'], 'bool');
settype($proposal['deadline'], 'int');
settype($proposal['trust'], 'int');
settype($proposal['areaLocal'], 'bool');
settype($proposal['participants'], 'int');
settype($proposal['corpus'], 'int');
$judge = intval($proposal['judgeParticipant']);
unset($proposal['judgeParticipant']);
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
  $r = $mysqli->query("SELECT `count` FROM results WHERE referendum=$id AND answer=''") or error($mysqli->error);
  $c = $r->fetch_assoc();
  $r->free();
  $proposal['results'][] = $c ? intval($c['count']) : 0;
  foreach($proposal['answers'] as $key => $value) {
    $query = "SELECT `count` FROM results WHERE referendum=$id AND answer=\"$value\"";
    $r = $mysqli->query($query) or error($mysqli->error);
    $c = $r->fetch_assoc();
    $r->free();
    $proposal['results'][] = $c ? intval($c['count']) : 0;
  }
  $r = $mysqli->query("SELECT COUNT(DISTINCT area) AS count FROM vote WHERE referendum=$id") or error($mysqli->error);
  $c = r->fetch_assoc();
  $r->free();
  $proposal['areas'] = $c ? intval($c{'count']) : 0;
}
if ($citizen) {
  $query = "SELECT "
          ."UNIX_TIMESTAMP(pt.published) AS published, "
          ."trust.type "
          ."FROM certificate AS trust "
          ."INNER JOIN publication AS pc ON pc.signature=FROM_BASE64('$citizen==') AND pc.id=trust.certifiedPublication "
          ."INNER JOIN publication AS pt ON pt.id=trust.publication AND pt.participant=$judge "
          ."WHERE trust.latest=1";
  $result = $mysqli->query($query) or error($mysqli->error);
  $certificate = $result->fetch_assoc();
  $result->free();
  if ($certificate)
    $proposal['trusted'] = $certificate['type'] === 'distrust' ? -1 : intval($certificate['published']);
  else
    $proposal['trusted'] = 0;
}
$mysqli->close();
die(json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
