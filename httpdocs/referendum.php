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
else
  $fingerprints = [];

$query_base = "SELECT "
             ."publication.schema, publication.key, publication.signature, publication.published, publication.expires, "
             ."referendum.trustee, referendum.area, referendum.title, referendum.description, "
             ."referendum.question, referendum.answers, referendum.deadline, referendum.website, "
             ."participation.count AS participation, participation.corpus AS corpus "
             ."FROM referendum "
             ."LEFT JOIN publication ON publication.id = referendum.id "
             ."LEFT JOIN participation ON participation.referendum = referendum.id ";

function set_types(&$referendum) {
  settype($referendum['published'], 'int');
  settype($referendum['expires'], 'int');
  settype($referendum['deadline'], 'int');
  settype($referendum['participation'], 'int');
  settype($referendum['corpus'], 'int');
}

if (isset($fingerprint)) {
  $query = "$query_base WHERE publication.fingerprint = \"$fingerprint\" "
          ."AND RIGHT(\"$area\", CHAR_LENGTH(referendum.area)) = referendum.area";
  $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
  $referendum = $result->fetch_assoc();
  $result->free();
  set_types($referendum);
  $json = json_encode($referendum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
  $referendums = array();
  if ($fingerprints) {
    $list = '(';
    foreach($fingerprints as $fingerprint)
      $list .= "\"$fingerprint\",";
    $list = substr($list, 0, -1).')';
    $query = "$query_base WHERE publication.fingerprint IN $list "
            ."AND RIGHT(\"$area\", CHAR_LENGTH(referendum.area)) = referendum.area "
            ."ORDER BY participation";
    $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
    die("{\"error\":\"$query\"");
    while ($referendum = $result->fetch_assoc()) {
      set_types($referendum);
      $referendums[] = $referendum;
    }
    $result->free();
  }
  $areas = explode("\\n", rtrim($area));
  $count = count($areas);
  foreach($areas as $i => $area) {
    $area_name = $area;
    for($j = $i + 1; $j < $count; $j++)
      $area_name .= "\n" . $areas[$j];
    $query = "$query_base WHERE referendum.area = \"$area_name\" AND referendum.deadline > (1000 * UNIX_TIMESTAMP()) ";
    if ($fingerprints)
      $query.= "AND publication.fingerprint NOT IN $list ";
    $query.= "ORDER BY participation DESC LIMIT 2";
    $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
    while ($referendum = $result->fetch_assoc()) {
      set_types($referendum);
      $referendums[] = $referendum;
    }
    $result->free();
  }
  $json = json_encode($referendums, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$mysqli->close();
die($json);
?>
