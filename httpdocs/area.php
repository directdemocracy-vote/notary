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
$trustee = $mysqli->escape_string($input->trustee);
$area = $mysqli->escape_string($input->area);

if (!$trustee)
  error("Missing trustee argument");
if (!$area)
  error("Missing area argument");

$now = intval(microtime(true) * 1000);  # milliseconds
$date_condition = "publication.published <= $now AND publication.expires >= $now";
$query = "SELECT publication.expires FROM area LEFT JOIN publication ON publication.id=area.id "
        ."WHERE publication.key='$trustee' AND area.name='$area' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("{\"status\":\"area not found\"}");
$area = $result->fetch_assoc();
$result->free();
$mysqli->close();
die("{\"expires\":\"$area[expires]\"}");
?>
