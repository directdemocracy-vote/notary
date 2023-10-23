<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

$version = '2';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$type = sanitize_field($_GET["type"], "string", "type");
$published_from = isset($_GET["published_from"]) ? sanitize_field($_GET["published_from"], "positive_int", "published_from") : null;
$published_to = isset($_GET["published_to"]) ? sanitize_field($_GET["published_to"], "positive_int", "published_to") : null;
$v = isset($_GET["version"]) ? sanitize_field($_GET["version"], "string", "version") : null;
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

$query = "SELECT CONCAT('https://directdemocracy.vote/json-schema/', p.`version`, '/', p.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(TO_BASE64(p.`key`), '\\n', '') AS `key`, "
        ."REPLACE(TO_BASE64(p.signature), '\\n', '') AS signature, "
        ."UNIX_TIMESTAMP(p.published) AS published, $fields "
        ."FROM $type "
        ."LEFT JOIN publication AS p ON p.id=$type.id AND $condition WHERE p.id IS NOT NULL";

$result = $mysqli->query($query) or error($mysqli->error);
$publications = array();
if ($result) {
  while($publication = $result->fetch_object()) {
    $publication->published = intval($publication->published);
    if ($type == 'citizen') {
      $publication->latitude = floatval($publication->latitude);
      $publication->longitude = floatval($publication->longitude);
    } elseif ($type == 'endorsement') {
      $publication->revoke = (intval($publication->revoke) === 1);
      if ($publication->message == '')
        unset($publication->message);
      if ($publication->comment == '')
        unset($publication->comment);
    } elseif ($type == 'proposal') {
      $publication->deadline = intval($publication->deadline);
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
