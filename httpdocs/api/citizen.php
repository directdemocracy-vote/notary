<?php
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_POST['signature'])) {
  $signature = sanitize_field($_POST['signature'], 'base64', 'signature');
  $condition = "publication.signature = FROM_BASE64('$signature==')";
} elseif (isset($_POST['key'])) {
  $key = sanitize_field($_POST['key'], 'base64', 'key');
  $condition = "publication.`key`=FROM_BASE64('$key==')";
} elseif (isset($_POST['fingerprint'])) {
  $fingerprint = sanitize_field($_POST['fingerprint'], 'hex', 'fingerprint');
  $condition = "publication.signatureSHA1 = UNHEX('$fingerprint')";
} else
  die('{"error":"missing key, signature or fingerprint POST argument"}');

$query = "SELECT "
        ."REPLACE(REPLACE(TO_BASE64(publication.`key`), '\\n', ''), '=', '') AS `key`, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."REPLACE(REPLACE(TO_BASE64(citizen.appKey), '\\n', ''), '=', '') AS appKey, "
        ."citizen.familyName, citizen.givenNames, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude "
        ."FROM publication INNER JOIN citizen ON publication.id = citizen.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $condition\"}");
$result->free();
settype($citizen['published'], 'int');
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
$endorsements = endorsements($mysqli, $citizen['key']);
$query = "SELECT "
        ."REPLACE(REPLACE(TO_BASE64(pc.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(pe.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(c.appKey), '\\n', ''), '=', '') AS appKey, "
        ."e.`revoke`, c.familyName, c.givenNames, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(c.picture), '\\n', '')) AS picture, "
        ."ST_Y(c.home) AS latitude, ST_X(c.home) AS longitude "
        ."FROM publication pe "
        ."INNER JOIN endorsement e ON e.id = pe.id "
        ."INNER JOIN publication pc ON pc.`key` = pe.`key` "
        ."INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE e.endorsedSignature = FROM_BASE64('$citizen[signature]==') AND e.latest = 1 "
        ."ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
if (!$result)
  die("{\"error\":\"$mysqli->error\"}");
$citizen_endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['published'], 'int');
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  $e['revoke'] = (intval($e['revoke']) == 1);
  $citizen_endorsements[] = $e;
}
$result->free();
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
$answer['citizen_endorsements'] = $citizen_endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
