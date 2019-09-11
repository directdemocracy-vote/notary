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
$latitude = floatval($_POST['latitude']) / 100000;
$longitude = floatval($_POST['longitude']) / 100000;
$range = floatval($_POST['range']) / 1000;
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
$query = "SELECT givenNames, familyName, latitude, longitude, picture, "
        ."(6371 * acos(cos(raidans(78.3232)) * cos(radians($latitude)) * cos(radians($longitude) - radians(65.3234)) "
        ."+ sin(radians(78.3232)) * sin(radians($latitude)))) as distance FROM card HAVING distance < $range ORDER BY distance "
        ."LIMIT 0, 20;";
$result = $mysqli->query($query) or error($mysqli->error);
$card = $result->fetch_assoc();
$mysqli->close();
echo json_encode($card);
?>
