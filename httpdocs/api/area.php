<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$input = json_decode(file_get_contents("php://input"));
$judge = sanitize_field($input->judge, "base64", "judge");
$area = $mysqli->escape_string($input->area);

if (!$judge)
  error("Missing judge argument");
if (!$area)
  error("Missing area argument");

$query = "SELECT UNIX_TIMESTAMP(publication.published) AS published FROM area "
        ."LEFT JOIN publication ON publication.id=area.publication "
        ."WHERE publication.`key`='$judge' AND area.name='$area' AND publication.published <= NOW()";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
if ($area)
  die("{\"published\":\"$area[published]\"}");
else
  die('{"published":"never"}');
?>
