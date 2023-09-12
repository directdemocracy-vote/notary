<?php
require_once '../../php/database.php';

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

$referendumFingerprint = $mysqli->escape_string(get_string_parameter('referendum'));
$station = $mysqli->escape_string(get_string_parameter('station'));

if (!$referendumFingerprint)
  error("Missing referendum argument");
if (!$station)
  error("Missing station argument");
if (!str_starts_with($station, 'https://'))
  error("The station argument should start with 'https://'");

$query = "SELECT publication.`key` FROM publication "
        ."INNER JOIN proposal ON proposal.id=publication.id AND proposal.secret=1 "
        ."WHERE publication.fingerprint='$referendumFingerprint'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result) {
  error("Specified referendum not found");
$referendum = $result->fetch_assoc();
$referendumKey = $referendum['key'];
$query = "SELECT participation FROM participation WHERE referendumFingerprint='$referendumFingerprint' AND station='$station'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result) {
  $answer = file_get_contents("$station/api/participation.php?key=$referendumKey");
  $data = json_decode($answer,true);
  $key = $data['key'];
  if ($data['referendum'] != $referendumKey)
    error("Referendum key mismatch");
  $participation = $data['participation'];
  # FIXME: here we should verify $data['signature']
  $query = "SELECT id, `key` FROM webservice WHERE url='$station' AND `type`='station'";
  $result = $mysqli->query($query) or error($mysqli->error);
  if (!$result) {
    $mysqli->query("INSERT INTO webservice(`type`, `key`, url) VALUES('station', '$key', '$station')") or error($mysqli->error);
    $id = mysqli->insert_id;
  } else {
    $webservice = $result->fetch_assoc();
    $result->free()
    if ($webservice['key'] != $key)
      error("Changed key for $station");
    $id = $webservice['id'];
  }
  $query = "INSERT INTO participation(referendum, participation, station, referendumFingerprint) VALUES('$referendumKey', '$participation', $id, '$referendumFingerprint'";
  $mysqli->query($query) or error($mysqli->error);
} else {
  $p = $result->fetch_assoc();
  $result->free();
  $participation = $p['participation'];
}
$mysqli->close();
$response = array('participation' => $participation);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>