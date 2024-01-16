<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_GET['signature']))
  $signature = sanitize_field($_GET['signature'], 'base64', 'signature');
elseif (isset($_GET['key']))
  $key = sanitize_field($_GET['key'], 'base64', 'key');
elseif (isset($_GET['fingerprint']))
  $fingerprint = sanitize_field($_GET['fingerprint'], 'hex', 'fingerprint');

$query = "SELECT publication.id, CONCAT('https://directdemocracy.vote/json-schema/', `version`, '/', publication.`type`, '.schema.json') AS `schema`, publication.`type`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(published) AS published "
        ."FROM publication "
        ."INNER JOIN participant ON participant.id=publication.participant "
        ."WHERE published <= NOW() AND ";
if (isset($signature))
  $query .= "signature = FROM_BASE64('$signature==')";
elseif (isset($key))
  $query .= "participant.`key` = FROM_BASE64('$key==') AND publication.`type`='citizen'";
elseif (isset($fingerprint))
  $query .= "signatureSHA1 = UNHEX('$fingerprint')";
else
  error("no fingerprint or key argument provided");
$result = $mysqli->query($query) or error($mysqli->error);
$publication = $result->fetch_assoc();
if (!$publication)
  error("publication not found");
$result->free();
$publication_id = intval($publication['id']);
unset($publication['id']);
$publication['published'] = intval($publication['published']);
$type = $publication['type'];
unset($publication['type']);
if ($type === 'citizen') {
  $query = "SELECT "
          ."REPLACE(REPLACE(TO_BASE64(app.`key`), '\\n', ''), '=', '') AS appKey, "
          ."REPLACE(REPLACE(TO_BASE64(appSignature), '\\n', ''), '=', '') AS appSignature, "
          ."givenNames, familyName, "
          ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(picture), '\\n', '')) AS picture, "
          ."ST_Y(home) AS latitude, ST_X(home) AS longitude "
          ."FROM citizen "
          ."INNER JOIN participant AS app ON app.id=citizen.app "
          ."WHERE citizen.publication=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $citizen = $result->fetch_assoc();
  $result->free();
  $citizen['latitude'] = floatval($citizen['latitude']);
  $citizen['longitude'] = floatval($citizen['longitude']);
  $citizen = $publication + $citizen;
  echo json_encode($citizen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type === 'certificate') {
  $query = "SELECT "
          ."REPLACE(REPLACE(TO_BASE64(appKey), '\\n', ''), '=', '') AS appKey, "
          ."REPLACE(REPLACE(TO_BASE64(appSignature), '\\n', ''), '=', '') AS appSignature, "
          ."type, "
          ."REPLACE(REPLACE(TO_BASE64(p.signature), '\\n', ''), '=', '') AS publication, "
          ."comment, message "
          ."FROM certificate WHERE publication=$publication_id "
          ."INNER JOIN publication as p WHERE p.id = certificate.certifiedPublication";
  $result = $mysqli->query($query) or error($mysqli->error);
  $certificate = $result->fetch_assoc();
  $result->free();
  if ($certificate['comment'] === '')
    unset($certificate['comment']);
  if ($certificate['message'] === '')
    unset($certificate['message']);
  $certificate = $publication + $certificate;
  echo json_encode($certificate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type === 'proposal') {
  $query = "SELECT REPLACE(REPLACE(TO_BASE64(area), '\\n', ''), '=', '') AS area, title, description, question, answers, secret, UNIX_TIMESTAMP(deadline) AS deadline, trust, website FROM proposal WHERE publication=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  if ($proposal['website'] === '')
    unset($proposal['website']);
  $proposal['deadline'] = intval($proposal['deadline']);
  $proposal['trust'] = intval($proposal['trust']);
  $proposal['secret'] = ($proposal['secret'] !== 0);
  if ($proposal['question'] === '')
    unset($proposal['question']);
  if ($proposal['answers'] === '')
    unset($proposal['answers']);
  else
    $proposal['answers'] = explode("\n", $proposal['answers']);
  $proposal = $publication + $proposal;
  echo json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif ($type === 'area') {
  $query = "SELECT name, ST_AsGeoJSON(polygons) AS polygons FROM area WHERE publication=$publication_id";
  $result = $mysqli->query($query) or error($mysqli->error);
  $area = $result->fetch_assoc();
  $polygons = json_decode($area['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    error("area without MultiPolygon: $polygons->type");
  $area['polygons'] = &$polygons->coordinates;
  $result->free();
  $area = $publication + $area;
  echo json_encode($area, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else
  error('publication type not supported: ' + $type);
$mysqli->close();
?>
