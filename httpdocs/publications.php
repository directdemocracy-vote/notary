<?php
require_once '../php/database.php';
$version = '0.0.1';

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
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$type = $mysqli->escape_string(get_string_parameter('type'));
$published_from = intval(get_string_parameter('published_from'));
$published_to = intval(get_string_parameter('published_to'));
$v = $mysqli->escape_string(get_string_parameter('version'));
if ($v)
  $version = $v;

if (!$type)
  error("No type argument provided.");
if ($type == 'endorsement')
  $fields = 'endorsement.publicationKey, endorsement.publicationSignature, endorsement.`revoke`, endorsement.message, endorsement.comment';
elseif ($type == 'citizen')
  $fields = 'citizen.familyName, citizen.givenNames, citizen.picture, ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude';
elseif ($type == 'referendum')
  $fields = 'referendum.trustee, referendum.area, referendum.title, referendum.description, referendum.question, referendum.answers, referendum.deadline, referendum.website';
elseif ($type == 'vote')
  $fields = 'vote.answer';
else
  error("Unsupported type argument: $type.");

$condition = '';
if ($published_from)
  $condition .="p.published >= $published_from AND ";
if ($published_to)
  $condition .="p.published <= $published_to AND ";
$condition .= "p.schema='https://directdemocracy.vote/json-schema/$version/$type.schema.json'";
/*
$query = "SELECT p.`schema`, p.`key`, p.signature, p.published, p.expires, $fields FROM $type "
        ."LEFT JOIN publication AS p ON p.id=$type.id AND $condition";
*/
$query = "SELECT p.`schema`, p.`key`, p.signature, p.published, p.expires, $fields FROM publication AS p "
        ."LEFT JOIN $type ON $type.id=p.id AND $condition";

$result = $mysqli->query($query) or error($mysqli->error);
$publications = array();
if ($result) {
  while($publication = $result->fetch_object()) {
    $publication->published = floatval($publication->published);
    $publication->expires = floatval($publication->expires);
    if ($type == 'citizen') {
      $publication->latitude = floatval($publication->latitude);
      $publication->longitude = floatval($publication->longitude);
    } elseif ($type == 'endorsement') {
      if ($publication->revoke == "0")
        unset($publication->revoke);
      else
        $publication->revoke = true;
      if ($publication->message == '')
        unset($publication->message);
      if ($publication->comment == '')
        unset($publication->comment);
    } elseif ($type == 'referendum') {
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
