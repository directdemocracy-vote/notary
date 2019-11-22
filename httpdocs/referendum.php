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
$areas = json_decode(file_get_contents("php://input"));
if (!$areas)
  error("Unable to parse JSON post");
if (!$areas->reference)
  error("Missing areas.reference field");
$reference = $areas->reference;
$query = "SELECT "
        ."publication.schema, publication.key, publication.signature, publication.published, publication.expires, "
        ."referendum.trustee, referendum.id AS areas, referendum.title, referendum.description, "
        ."referendum.question, referendum.answers, referendum.deadline, referendum.website "
        ."FROM referendum "
        ."LEFT JOIN publication ON publication.id = referendum.id "
        ."LEFT JOIN area ON area.parent = referendum.id "
        ."WHERE area.reference=\"$reference\" AND (";
foreach($areas->areas as $area) {
  $type = $area->type;
  $name = $area->name;
  $query .= "(area.type=\"$type\" AND area.name=\"$name\") OR ";
}
$query = substr($query, 0, -4); // remove last " OR "
$query .= ")";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$referendums = array();
while ($referendum = $result->fetch_assoc()) {
  settype($referendum['published'], 'int');
  settype($referendum['expires'], 'int');
  settype($referendum['areas'], 'int');
  settype($referendum['deadline'], 'int');
  $q = "SELECT reference, type, name FROM area WHERE parent=$referendum[areas]";
  $r = mysqli->query($q) or die("{\"error\":\"$mysqli->error\"}");
  $referendum['areas'] = array();
  while ($area = $r->fetch_assoc())
    $referendum['areas'][] = $area;
  $r->free();
  $referendums[] = $referendum;
}
$result->free();
$mysqli->close();
die(json_encode($referendums, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
