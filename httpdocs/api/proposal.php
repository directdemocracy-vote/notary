<?php
# This API entry returns a proposal with many information
# The fingerprint is a mandatory parameter
# The latitude and longiture are optional parameters. If they are provided, the answer will not contain the polygons field,
# but instead will contain a boolean field named inside that is true if the provided latitude longitude point is inside the
# proposal area.

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

$condition = (isset($signature)) ? "publication.signature=FROM_BASE64('$signature' + '==')" : "publication.signatureSHA1=UNHEX('$fingerprint')";

if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
  $latitude = sanitize_field($_GET["latitude"], "float", "latitude");
  $longitude = sanitize_field($_GET["longitude"], "float", "longitude");
  $extra = 'area.id AS area_id';
} else
  $extra = 'ST_AsGeoJSON(area.polygons) AS polygons';

$query = "SELECT "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(proposal.area), '\\n', ''), '=', '') AS area, "
        ."proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
        ."proposal.participants, proposal.corpus, "
        ."area.name AS areas, $extra "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS pa ON pa.signature = proposal.area "
        ."LEFT JOIN area ON area.id = pa.id "
        ."WHERE $condition";

$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$proposal = $result->fetch_assoc();
$result->free();
if (!$proposal)
  die('{"error":"Proposal not found"}');
settype($proposal['published'], 'int');
settype($proposal['secret'], 'bool');
settype($proposal['deadline'], 'int');
settype($proposal['participants'], 'int');
settype($proposal['corpus'], 'int');
if ($proposal['answers'] === '')
  unset($proposal['answers']);
else
  $proposal['answers'] = explode("\n", $proposal['answers']);
if ($proposal['question'] === '')
  unset($proposal['question']);
$proposal['areas'] = explode("\n", $proposal['areas']);
if (!isset($latitude)) {
  $polygons = json_decode($proposal['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    die("{\"error\":\"Area without MultiPolygon: $polygons->type\"}");
  $proposal['polygons'] = &$polygons->coordinates;
} else {
  $query = "SELECT id FROM area WHERE id=$proposal[area_id] AND ST_Contains(polygons, POINT($longitude, $latitude))";
  $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
  $proposal['inside'] = $result->fetch_assoc() ? true : false;
  unset($proposal['area_id']);
}
$mysqli->close();
$json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
die($json);
?>
