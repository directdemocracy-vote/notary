<?php
require_once '../../php/database.php';
$version = '2';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$type = $mysqli->escape_string(get_string_parameter('type'));
$published_from = intval(get_string_parameter('published_from'));
$published_to = intval(get_string_parameter('published_to'));
$v = $mysqli->escape_string(get_string_parameter('version'));
if ($v)
  $version = $v;

if (!$type)
  error("No type argument provided.");
if ($type == 'endorsement')
  $fields = "REPLACE(TO_BASE64(endorsement.endorsedSignature), '\\n', '') AS endorsedSignature, endorsement.revoke, endorsement.message, endorsement.comment";
elseif ($type == 'citizen')
  $fields = "citizen.familyName, citizen.givenNames, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')), "
           ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude";
elseif ($type == 'proposal')
  $fields = 'proposal.judge, proposal.area, proposal.title, proposal.description, proposal.question, proposal.answers, proposal.deadline, proposal.website';
else
  error("Unsupported type argument: $type.");

$condition = '';
if ($published_from)
  $condition .="UNIX_TIMESTAMP(p.published) >= $published_from AND ";
if ($published_to)
  $condition .="UNIX_TIMESTAMP(p.published) <= $published_to AND ";
$condition .= "p.version=$version AND p.type = '$type'";

$query = "SELECT p.`version`, p.`type`, "
        ."REPLACE(TO_BASE64(p.`key`), '\\n', '') AS `key`, "
        ."REPLACE(TO_BASE64(p.signature), '\\n', '') AS signature, "
        ."UNIX_TIMESTAMP(p.published) AS published, $fields "
        ."FROM $type "
        ."LEFT JOIN publication AS p ON p.id=$type.id AND $condition WHERE p.id IS NOT NULL";

$result = $mysqli->query($query) or error($mysqli->error);
$publications = array();
if ($result) {
  while($publication = $result->fetch_object()) {
    $publication->schema = 'https://directdemocracy.vote/json-schema/' . $publication->version . '/' . $publication->type . '.schema.json';
    unset($publication->version);
    unset($publication->type);
    $publication->published = intval($publication->published);
    if ($type == 'citizen') {
      $publication->latitude = floatval($publication->latitude);
      $publication->longitude = floatval($publication->longitude);
    } elseif ($type == 'endorsement') {
      if (intval($publication->revoke) === 1)
        $publication->revoke = true;
      if ($publication->message == '')
        unset($publication->message);
      if ($publication->comment == '')
        unset($publication->comment);
    } elseif ($type == 'proposal') {
      $publication->deadline = floatval($publication->deadline);
      if ($publication->website == '')
        unset($publication->website);
    }
    array_push($publications, $publication);
  }
  $result->free();
}
$mysqli->close();
echo json_encode($publications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
