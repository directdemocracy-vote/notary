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
  die("Missing referendum argument");

$now = floatval(microtime(true) * 1000);  # milliseconds
$date_condition = "publication.published <= $now AND publication.expires >= $now";

$query = "SELECT area, trustee FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum_key' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("Referendum not found");
$referendum = $result->fetch_assoc();
$result->free();

$trustee = $referendum['trustee'];
$area = $referendum['area'];

$query = "SELECT id FROM area LEFT JOIN publication on publication.id=area.id "
        ."WHERE name='$area' AND publication.key='$trustee' AND $date_condition";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  die("Area was not published by trustee");
$area = $results->fetch_assoc();
$area_id = intval($area['id']);

$query = "SELECT publication.key FROM citizen LEFT JOIN publication ON publication.id=citizen.id "
        ."LEFT JOIN area ON area.id=$area_id WHERE ST_Contains(area.polygons, citizen.home)";
$result = $mysqli->query($query) or error($mysqli->error);
$count = 0;
$citizens = array();
if ($result) {
  $count = $result->num_rows;
  $citizen = $result->fetch_assoc();
  $list = "'$citizen[key]'";
  while($citizen = $result->fetch_assoc())
    $list .= ",'$citizen[key]";  # maybe use SHA1 to speed-up things and lower query length
  $result->free();
  $query = "SELECT DISTINCT registration.stationKey LEFT JOIN publication ON publication.id=registration.id "
          ."WHERE publication.key IN ($list)";
}

$response = array('count' => $count, 'corpus' => $citizens);




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
