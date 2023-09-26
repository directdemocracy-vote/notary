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

$referendumFingerprint = $mysqli->escape_string(get_string_parameter('referendum'));
$station = $mysqli->escape_string(get_string_parameter('station'));

if (!$referendumFingerprint)
  error("Missing referendum argument");
if (!$station)
  error("Missing station argument");
if (!str_starts_with($station, 'https://'))
  error("The station argument should start with 'https://'");

$query = "SELECT publication.`signature` FROM publication "
        ."INNER JOIN proposal ON proposal.id=publication.id AND proposal.secret=1 "
        ."WHERE SHA1(publication.signature)='$referendumFingerprint'";
$result = $mysqli->query($query) or error($mysqli->error);
if (!$result)
  error("Specified referendum not found");
$referendum = $result->fetch_assoc();
$referendumSignature = $referendum['signature'];
$query = "SELECT publication.`version`, publication.`type`, TO_BASE64(publication.`key`) AS `key`, TO_BASE64(publication.signature) AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."TO_BASE64(participation.referendum) AS referendum, TO_BASE64(participation.blindKey) AS blindKey FROM participation "
        ."INNER JOIN publication ON publication.id=participation.id "
        ."INNER JOIN webservice AS station ON station.url='$station' "
        ."WHERE SHA1(participation.referendum)='$referendumFingerprint' AND participation.station=station.id";
$result = $mysqli->query($query) or error($mysqli->error);
$publication = $result->fetch_assoc();
$result->free();
if (!$publication) {
  $answer = file_get_contents("$station/api/participation.php?referendum=" . urlencode($referendumSignature));
  $publication = json_decode($answer,true);
  $signature = $publication['signature'];
  $publication['signature'] = '';
  $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $verify = openssl_verify($data, base64_decode($signature), public_key($publication['key']), OPENSSL_ALGO_SHA256);
  if ($verify != 1)
    error("Wrong signature for participation");
  $publication['signature'] = $signature;
  $key = $publication['key'];
  if ($publication['referendum'] != $referendumSignature)
    error("Referendum signature mismatch");
  $blindKey = $publication['blindKey'];
  $query = "SELECT id, TO_BASE64(`key`) AS `key` FROM webservice WHERE url='$station' AND `type`='station'";
  $result = $mysqli->query($query) or error($mysqli->error);
  if (!$result) {
    $mysqli->query("INSERT INTO webservice(`type`, `key`, url) VALUES('station', FROM_BASE64('$key'), '$station')") or error($mysqli->error);
    $id = mysqli->insert_id;
  } else {
    $webservice = $result->fetch_assoc();
    $result->free();
    if ($webservice['key'] != $key)
      error("Changed key for $station");
    $id = intval($webservice['id']);
  }
  # $publication['schema'] looks like this: 'https://directdemocracy.vote/json-schema/2/participation.json'
  $version = intval(explode('/', $publication['schema'])[4]);
  $query = "INSERT INTO publication(`version`, `type`, `key`, `signature`, published) "
          ."VALUES($version, 'participation', FROM_BASE64('$publication[key]'), FROM_BASE64('$publication[signature]'), FROM_UNIXTIME($publication[published]))";
  $mysqli->query($query) or error($mysqli->error);
  $publicationId = $mysqli->insert_id;
  $query = "INSERT INTO participation(id, referendum, blindKey, station) "
          ."VALUES($publicationId, FROM_BASE64('$referendumSignature'), FROM_BASE64('$blindKey'), $id)";
  $mysqli->query($query) or error($mysqli->error);
} else {
  settype($publication['published'], 'int');
  $publication['schema'] = 'https://directdemocracy.vote/json-schema/' . $publication['version'] . '/' . $publication['type'] . '.schema.json';
  unset($publication['version']);
  unset($publication['type']);
  $answer = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
$mysqli->close();
echo $answer;
?>