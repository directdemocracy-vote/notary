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

$query = "SELECT area, trustee FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

# get area polygon from openstreemap

$area = str_replace("\n", "&", $referendum['area']);
$url = "https://nominatim.openstreetmap.org/search?$area&polygon_geojson=1&format=jsonv2";
die($url."<br>".urlencode($url));
$area = file_get_contents($url);
die($area);

$now = floatval(microtime(true) * 1000);  # milliseconds
$query = "SELECT publication.`key`, "
        ."citizen.latitude, citizen.longitude "
        ."FROM citizen LEFT JOIN publication ON publication.id=citizen.id "
        ."WHERE published <= $now AND expires >= $now"; // FIXME optimization: restrain latitude/logitude to area bounding rectangle
$result = $mysqli->query($query) or error($mysqli->error);
$citizens = [];
if ($result) {
  while ($citizen = $result->fetch_assoc()) {
    $lat = intval($citizen['latitude']) / 1000000;
    $lon = intval($citizen['longitude']) / 1000000;
    $url = "https://nominatim.openstreetmap.org/reverse.php?format=json&lat=$lat&lon=$lon";
    $response = file_get_contents($url);
    array_push($citizens, $citizen['key']);
  }
  $result->free();
}
$query = "SELECT publication.`schema`, publication.`key`, publication.signature, publication.published, publication.expires, "
        ."ballot.referendum, ballot.stationKey, ballot.stationSignature "
        ."FROM ballot LEFT JOIN publication ON publication.id=ballot.id "
        ."WHERE ballot.referendum='$referendum'"; // AND published <= $now AND expires >= $now"; FIXME
$result = $mysqli->query($query) or error($mysqli->error);
$ballots = [];
if ($result) {
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
