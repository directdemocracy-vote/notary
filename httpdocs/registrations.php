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

$referendum = $mysqli->escape_string(get_string_parameter('referendum'));
if (!$referendum)
  die("Missing referendum argument");

$now = floatval(microtime(true) * 1000);  # milliseconds
$query = "SELECT publication.`schema`, publication.`key`, publication.signature, publication.published, publication.expires, "
        ."registration.referendum, registration.stationKey, registration.stationSignature "
        ."FROM registration LEFT JOIN publication ON publication.id=registration.id "
        ."WHERE registration.referendum='$referendum' AND published <= $now AND expires >= $now";
$result = $mysqli->query($query) or error($mysqli->error);
$registrations = [];
if ($results) {
  while ($registration = $result->fetch_assoc()) {
    $registration['published'] = floatval($registration['published']);
    $registration['expires'] = floatval($registration['expires']);
    $station_key = $registration['stationKey'];
    $station_signature = $registration['stationSignature'];
    unset($registration['stationKey']);
    unset($registration['stationSignature']);
    $registration['station'] = array('key' => $station_key, 'signature' => $station_signature);
    array_push($registrations, $registration);
  }
  $result->free();
}
$query = "SELECT publication.`schema`, publication.`key`, publication.signature, publication.published, publication.expires, "
        ."ballot.referendum, ballot.stationKey, ballot.stationSignature "
        ."FROM ballot LEFT JOIN publication ON publication.id=ballot.id "
        ."WHERE ballot.referendum='$referendum' AND published <= $now AND expires >= $now";
$result = $mysqli->query($query) or error($mysqli->error);
$ballots = [];
if ($results) {
  while ($ballot = $result->fetch_assoc()) {
    $ballot['published'] = floatval($ballot['published']);
    $ballot['expires'] = floatval($ballot['expires']);
    $station_key = $ballot['stationKey'];
    $station_signature = $ballot['stationSignature'];
    unset($ballot['stationKey']);
    unset($ballot['stationSignature']);
    $ballot['station'] = array('key' => $station_key, 'signature' => $station_signature);
    array_push($ballots, $ballot);
  }
  $result->free();
}
$mysqli->close();
$response = array('registrations' => $registrations, 'ballots' => $ballots);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
