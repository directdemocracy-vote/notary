<?php
require_once '../php/database.php';

function error($message) {
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$input = json_decode(file_get_contents("php://input"));
$judge = $mysqli->escape_string($input->judge);
$area = $mysqli->escape_string($input->area);

if (!$judge)
  error("Missing judge argument");
if (!$area)
  error("Missing area argument");

$now = intval(microtime(true) * 1000);  # milliseconds
$date_condition = "publication.published <= $now";
$query = "SELECT publication.published FROM area LEFT JOIN publication ON publication.id=area.id "
        ."WHERE publication.key='$judge' AND area.name='$area' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
die("{\"published\":\"$area[published]\"}");
?>