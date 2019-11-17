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
$query = "SELECT referendum.title FROM referendum LEFT JOIN area ON area.parent = referendum.id WHERE area.reference=\"$reference\" AND (";
foreach($areas->areas as $area) {
  $type = $area->type;
  $name = $area->name;
  $query .= "(area.type=\"$type\" AND area.name=\"$name\") OR ";
}
$query = substr($query, 0, -4); // remove last " OR "
$query .= ")";
die($query);
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
while ($referendum = $result->fetch_assoc()) {

}
$result->free();
settype($citizen['published'], 'int');
settype($citizen['expires'], 'int');
settype($citizen['latitude'], 'int');
settype($citizen['longitude'], 'int');
$endorsements = endorsements($mysqli, $key);
$query = "SELECT pc.fingerprint, pe.published, e.revoke, "
        ."c.familyName, c.givenNames, c.picture FROM "
        ."publication pe INNER JOIN endorsement e ON pe.id = e.id, "
        ."publication pc INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE e.publicationKey = '$key' AND pc.`key` = pe.`key` "
        ."ORDER BY e.revoke ASC, pe.published, c.familyName, c.givenNames";
$result = $mysqli->query($query);
if (!$result)
  return "{\"error\":\"$mysqli->error\"}";
$citizen_endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['published'], 'int');
  settype($e['revoke'], 'bool');
  $citizen_endorsements[] = $e;
}
$result->free();
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
$answer['citizen_endorsements'] = $citizen_endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
