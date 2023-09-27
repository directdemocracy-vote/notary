<?php
# This API entry returns a proposal with many information
# The fingerprint is a mandatory parameter
# The latitude and longiture are optional parameters. If they are provided, the answer will not contain the polygons field,
# but instead will contain a boolean field named inside that is true if the provided latitude longitude point is inside the
# proposal area.

require_once '../../php/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
if (!isset($fingerprint))
  die('{"error":"Missing fingerprint parameter"}');

if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
  $latitude = floatval($_GET['latitude']);
  $longitude = floatval($_GET['longitude']);
  $extra = 'area.id AS area_id';
} else
  $extra = 'ST_AsGeoJSON(area.polygons) AS polygons';

$query = "SELECT "
          ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(TO_BASE64(publication.`key`), '\\n', '') AS `key`, "
        ."REPLACE(TO_BASE64(publication.signature), '\\n', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."proposal.judge, proposal.area, proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
        ."proposal.participants, proposal.corpus, "
        ."area.name AS areas, $extra "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS pa ON pa.signature = proposal.area "
        ."LEFT JOIN area ON area.id = pa.id "
        ."WHERE SHA1(REPLACE(TO_BASE64(publication.signature), '\\n', '')) = '$fingerprint'";

$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$proposal = $result->fetch_assoc();
$result->free();
if (!$proposal)
  die('{"error":"Proposal not found"}');
echo("$proposal[schema]");
foreach($proposal as $key => $value)
  echo("key:" . $key . " value:" . $value . "\n");
# echo("$proposal");
die(json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
settype($proposal['published'], 'int');
settype($proposal['secret'], 'bool');
settype($proposal['deadline'], 'int');
settype($proposal['participation'], 'int');
settype($proposal['corpus'], 'int');
$proposal['answers'] = explode("\n", $proposal['answers']);
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