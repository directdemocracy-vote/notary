<?php
require_once '../php/database.php';

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
if (isset($_POST['area']))
  $area = $mysqli->escape_string($_POST['area']);
else
  error("Unable to parse JSON post");
if (isset($_POST['fingerprint']))
  $fingerprint = $mysqli->escape_string($_POST['fingerprint']);
if (isset($_POST['fingerprints']))
  $fingerprints = explode(',', $mysqli->escape_string($_POST['fingerprints']));

$query_base = "SELECT "
             ."publication.schema, publication.key, publication.signature, publication.published, publication.expires, "
             ."proposal.trustee, proposal.area, proposal.title, proposal.description, "
             ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
             ."participation.count AS participation, participation.corpus AS corpus "
             ."FROM proposal "
             ."LEFT JOIN publication ON publication.id = proposal.id "
             ."LEFT JOIN participation ON participation.proposal = proposal.id ";

function set_types(&$proposal) {
  settype($proposal['published'], 'int');
  settype($proposal['expires'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
}

if (isset($fingerprint)) {
  $query = "$query_base WHERE publication.fingerprint = \"$fingerprint\" "
          ."AND RIGHT(\"$area\", CHAR_LENGTH(proposal.area)) = proposal.area";
  $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
  $proposal = $result->fetch_assoc();
  $result->free();
  set_types($proposal);
  $json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
  $proposals = array();
  if (isset($fingerprints)) {
    $list = '(';
    foreach($fingerprints as $fingerprint)
      $list .= "\"$fingerprint\",";
    $list = substr($list, 0, -1).')';
  }
  $areas = explode("\\n", rtrim($area));
  $count = count($areas);
  foreach($areas as $i => $area) {
    $area_name = $area;
    for($j = $i + 1; $j < $count; $j++)
      $area_name .= "\n" . $areas[$j];
    if (isset($fingerprints)) {
      $query = "$query_base WHERE publication.fingerprint IN $list AND proposal.area=\"$area_name\" ORDER BY participation";
      $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
      while ($proposal = $result->fetch_assoc()) {
        set_types($proposal);
        $proposals[] = $proposal;
      }
      $result->free();
    }
    $query = "$query_base WHERE proposal.area = \"$area_name\" AND proposal.deadline > (1000 * UNIX_TIMESTAMP()) ";
    if (isset($fingerprints))
      $query.= "AND publication.fingerprint NOT IN $list ";
    $query.= "ORDER BY participation DESC LIMIT 2";
    $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
    while ($proposal = $result->fetch_assoc()) {
      set_types($proposal);
      $proposals[] = $proposal;
    }
    $result->free();
  }
  $json = json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$mysqli->close();
die($json);
?>
