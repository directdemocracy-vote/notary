<?php
# This API entry returns a proposal with many information

require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
if (!isset($fingerprint))
  error('Missing fingerprint parameter.');

function return_results($query) {
  global $mysqli;
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposals = array();
  while ($proposal = $result->fetch_assoc()) {
    $proposal['participation'] = 0;
    $proposal['corpus'] = 0;
    set_types($proposal);
    $proposals[] = $proposal;
  }
  $result->free();
  $mysqli->close();
  die(json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$query = "SELECT "
        ."publication.schema, publication.key, publication.signature, publication.published, "
        ."proposal.judge, proposal.area, proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
        ."proposal.participants, proposal.corpus, "
        ."area.name, ST_AsGeoJSON(area.polygons) AS polygons "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS pa ON pa.signature = proposal.area "
        ."LEFT JOIN area ON area.id = pa.id "
        ."WHERE publication.fingerprint = '$fingerprint'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  $mysqli->close();
  $polygons = json_decode($proposal['polygons']);
  if ($polygons->type !== 'MultiPolygon')
    die("Area without MultiPolygon: $polygons->type");
  $proposal['polygons'] = &$polygons->coordinates;
  $proposal['name'] = explode("\n", $proposal['areas']);
  settype($proposal['published'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
  $json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  die($json);
}
?>