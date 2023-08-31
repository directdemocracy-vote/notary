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

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
if (!isset($fingerprint))
  die('Missing fingerprint parameter.');

if (isset($_GET['latitude']) && isset($_GET['longiture'])) {
  $latitude = floatval($_GET['latitude']);
  $longitude = floatval($_GET['longitude']);
  $extra = 'area.id AS area_id';
} else
  $extra = 'ST_AsGeoJSON(area.polygons) AS polygons';

$query = "SELECT "
        ."publication.schema, publication.key, publication.signature, publication.published, "
        ."proposal.judge, proposal.area, proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
        ."proposal.participants, proposal.corpus, "
        ."area.name, $extra "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS pa ON pa.signature = proposal.area "
        ."LEFT JOIN area ON area.id = pa.id "
        ."WHERE publication.fingerprint = '$fingerprint'";
$result = $mysqli->query($query) or die($mysqli->error);
$proposal = $result->fetch_assoc();
$result->free();
$proposal['name'] = explode("\n", $proposal['name']);
settype($proposal['published'], 'int');
settype($proposal['secret'], 'bool');
settype($proposal['deadline'], 'int');
settype($proposal['participation'], 'int');
settype($proposal['corpus'], 'int');
if (!isset($latitude)) {
  $polygons = json_decode($proposal['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    die("Area without MultiPolygon: $polygons->type");
  $proposal['polygons'] = &$polygons->coordinates;
} else {
  $query = "SELECT id FROM area WHERE id=$proposal[area_id] AND ST_Contains(polygons, POINT($longitude, $latitude))";
  $mysqli->query($query) or die($mysli->error);
  $proposal['inside'] = $mysqli->fetch_assoc() ? true : false;
  unset($proposal['area_id']);
}
$mysqli->close();
$json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
die($json);
?>