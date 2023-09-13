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

function public_key($key) {
  $public_key = "-----BEGIN PUBLIC KEY-----\n";
  $l = strlen($key);
  for($i = 0; $i < $l; $i += 64)
    $public_key .= substr($key, $i, 64) . "\n";
  $public_key.= "-----END PUBLIC KEY-----";
  return $public_key;
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
if (!$result)
  error("Specified referendum not found");
$referendum = $result->fetch_assoc();
$referendumKey = $referendum['key'];
$query = "SELECT publication.`schema`, publication.`key`, publication.`signature`, publication.`published`, "
        ."participation.referendum, participation.participation FROM participation "
        ."INNER JOIN publication ON publication.id=participation.id "
        ."INNER JOIN webservice AS station ON station.url='$station' "
        ."WHERE participation.referendumFingerprint='$referendumFingerprint' AND participation.station=station.id";
$result = $mysqli->query($query) or error($mysqli->error);
$publication = $result->fetch_assoc();
$result->free();
if (!$publication) {
  $answer = file_get_contents("$station/api/participation.php?referendum=" . urlencode($referendumKey));
  $publication = json_decode($answer,true);
  $signature = $publication['signature'];
  $publication['signature'] = '';
  $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $verify = openssl_verify($data, base64_decode($signature), public_key($publication['key']), OPENSSL_ALGO_SHA256);
  if ($verify != 1)
    error("Wrong signature for participation");
  $publication['signature'] = $signature;
  $key = $publication['key'];
  if ($publication['referendum'] != $referendumKey)
    error("Referendum key mismatch");
  $participation = $publication['participation'];
  $query = "SELECT id, `key` FROM webservice WHERE url='$station' AND `type`='station'";
  $result = $mysqli->query($query) or error($mysqli->error);
  if (!$result) {
    $mysqli->query("INSERT INTO webservice(`type`, `key`, url) VALUES('station', '$key', '$station')") or error($mysqli->error);
    $id = mysqli->insert_id;
  } else {
    $webservice = $result->fetch_assoc();
    $result->free();
    if ($webservice['key'] != $key)
      error("Changed key for $station");
    $id = intval($webservice['id']);
  }
  $query = "INSERT INTO publication(`schema`, `key`, `signature`, fingerprint, published) "
          ."VALUES('$publication[schema]', '$publication[key]', '$publication[signature]', "
          ."SHA1('$publication[signature]'), $publication[published])";
  $mysqli->query($query) or error($mysqli->error);
  $publicationId = $mysqli->insert_id;
  $query = "INSERT INTO participation(id, referendum, participation, station, referendumFingerprint) "
          ."VALUES($publicationId, '$referendumKey', '$participation', $id, '$referendumFingerprint'";
  $mysqli->query($query) or error($mysqli->error);
} else {
  settype($publication['published'], 'int');  
  $answer = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$mysqli->close();
echo $answer;
?>