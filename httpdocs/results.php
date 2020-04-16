<?php
require_once '../php/database.php';

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

$referendum_key = $mysqli->escape_string(get_string_parameter('referendum'));
if (!$referendum_key)
  error("Missing referendum argument");

$query = "SELECT id FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum_key'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();
$referendum_id = $referendum['id'];

$query = "SELECT answer, `count` FROM results WHERE referendum=$referendum)";
$result = $mysqli->query($query) or error($mysqli->error);
$results = array();
while ($r = $result->fetch_object())
  array_push($results, $r);
$result->free();
$mysqli->close();

echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
