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
$area = $mysqli->escape_string($_POST['area']);
$fingerprint = $mysqli->escape_string($_POST['fingerprint']);
if (!$area)
  error("Unable to parse JSON post");

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

if ($fingerprint) {
  $query = "$query_base WHERE publication.fingerprint = \"$fingerprint\" "
          ."AND RIGHT(\"$area\", CHAR_LENGTH(referendum.area)) = referendum.area";
  $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
  $referendum = $result->fetch_assoc();
  $result->free();
  set_types($referendum);
  $json = json_encode($referendum, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
} else {
  $referendums = array();
  $areas = explode("\\n", rtrim($area));
  $count = count($areas);
  foreach($areas as $i => $area) {
    $area_name = $area;
    for($j = $i + 1; $j < $count; $j++)
      $area_name .= "\n" . $areas[$j];
    $query = "$query_base WHERE referendum.area = \"$area_name\" ORDER BY participation DESC LIMIT 5";
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
