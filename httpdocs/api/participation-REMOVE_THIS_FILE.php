<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';
require_once '../../php/public_key.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$referendumSignature = sanitize_field($_GET["referendum"], "base64", "referendum");
$station = sanitize_field($_GET["station"], "url", "station");

if (!$referendumSignature)
  error("Missing referendum argument");
if (!$station)
  error("Missing station argument");
if (!str_starts_with($station, 'https://'))
  error("The station argument should start with 'https://'");

$query = "SELECT publication.`version`, publication.`type`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(participation.referendum), '\\n', ''), '=', '') AS referendum, "
        ."REPLACE(REPLACE(TO_BASE64(participation.blindKey), '\\n', ''), '=', '') AS blindKey FROM participation "
        ."INNER JOIN publication ON publication.id=participation.id "
        ."INNER JOIN webservice AS station ON station.url='$station' "
        ."WHERE participation.referendum='$referendumSignature' AND participation.station=station.id";
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
  $query = "SELECT id, REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM webservice WHERE url='$station' AND `type`='station'";
  $result = $mysqli->query($query) or error($mysqli->error);
  if ($result->num_rows === 0) {
    $mysqli->query("INSERT INTO webservice(`type`, `key`, url) VALUES('station', FROM_BASE64('$key=='), '$station')") or error($mysqli->error);
    $id = $mysqli->insert_id;
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
          ."VALUES($version, 'participation', FROM_BASE64('$publication[key]=='), FROM_BASE64('$publication[signature]=='), "
          ."FROM_UNIXTIME($publication[published]))";
  $mysqli->query($query) or error($mysqli->error);
  $publicationId = $mysqli->insert_id;
  $query = "INSERT INTO participation(id, referendum, blindKey, station) "
          ."VALUES($publicationId, FROM_BASE64('$referendumSignature=='), FROM_BASE64('$blindKey=='), $id)";
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