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
if (!$area)
  error("Unable to parse JSON post");
$areas = explode("\\n", $area);
$count = count($areas);
$referendums = array();
$test = new StdClass();
$test->count = $count;
$test->areas = array();
foreach($areas as $i => $area) {
  $area_name = $area;
  for($j = $i + 1; $j < $count; $j++)
    $area_name .= "\n" . $areas[$j];
  $test->areas[] = $area_name;
  $query = "SELECT "
          ."publication.schema, publication.key, publication.signature, publication.published, publication.expires, "
          ."referendum.trustee, referendum.area, referendum.title, referendum.description, "
          ."referendum.question, referendum.answers, referendum.deadline, referendum.website, "
          ."participation.count AS participation, participation.corpus AS corpus "
          ."FROM referendum "
          ."LEFT JOIN publication ON publication.id = referendum.id "
          ."LEFT JOIN participation ON participation.referendum = referendum.id "
          ."WHERE referendum.area = \"$area_name\" "
          ."ORDER BY participation DESC LIMIT 5";
  $result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
  while ($referendum = $result->fetch_assoc()) {
    settype($referendum['published'], 'int');
    settype($referendum['expires'], 'int');
    settype($referendum['deadline'], 'int');
    settype($referendum['participation'], 'int');
    settype($referendum['corpus'], 'int');
    $referendums[] = $referendum;
  }
  $result->free();
}
$mysqli->close();
die(json_encode($test, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

die(json_encode($referendums, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
