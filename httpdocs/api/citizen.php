<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_POST['signature'])) {
  $signature = sanitize_field($_POST['signature'], 'base64', 'signature');
  $condition = "publication.signature = FROM_BASE64('$signature==')";
} elseif (isset($_POST['key'])) {
  $key = sanitize_field($_POST['key'], 'base64', 'key');
  $condition = "participant.`key`=FROM_BASE64('$key==')";
} elseif (isset($_POST['fingerprint'])) {
  $fingerprint = sanitize_field($_POST['fingerprint'], 'hex', 'fingerprint');
  $condition = "publication.signatureSHA1 = UNHEX('$fingerprint')";
} else
  die('{"error":"missing key, signature or fingerprint POST argument"}');

$query = "SELECT publication.id, "
        ."CONCAT('https://directdemocracy.vote/json-schema/', `version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(app.`key`), '\\n', ''), '=', '') AS appKey, "
        ."REPLACE(REPLACE(TO_BASE64(citizen.appSignature), '\\n', ''), '=', '') AS appSignature, "
        ."citizen.givenNames, citizen.familyName, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude "
        ."FROM publication "
        ."INNER JOIN citizen ON publication.id = citizen.id "
        ."INNER JOIN participant ON participant.id = publication.participantId "
        ."INNER JOIN participant AS app ON app.id = citizen.appId "
        ."WHERE $condition";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $condition\"}");
$result->free();
$alice_id = intval($citizen['id']);
unset($citizen['id']);
settype($citizen['published'], 'int');
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
# list all the bobs endorsed by alice
$query = "SELECT bob.id, "
        ."REPLACE(REPLACE(TO_BASE64(participant_bob.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication_bob.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication_bob.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(app.key), '\\n', ''), '=', '') AS appKey, "
        ."REPLACE(REPLACE(TO_BASE64(bob.appSignature), '\\n', ''), '=', '') AS appSignature, "
        ."bob.familyName, bob.givenNames, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(bob.picture), '\\n', '')) AS picture, "
        ."ST_Y(bob.home) AS latitude, ST_X(bob.home) AS longitude "
        ."REPLACE(REPLACE(TO_BASE64(pe.signature), '\\n', ''), '=', '') AS endorsementSignature, "
        ."UNIX_TIMESTAMP(pe.published) AS endorsementPublished "
        ."FROM publication pe "
        ."INNER JOIN certificate AS e ON e.id=pe.id AND e.type='endorse' AND e.latest=1 "
        ."INNER JOIN publication AS publication_bob ON e.publicationId=publication.id "
        ."INNER JOIN bob AS bob.id=publication_bob.id "
        ."INNER JOIN participant AS app ON app.id=bob.appId " 
        ."WHERE pe.participantId=$alice_id ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
if (!$result)
  die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['id'], 'int');
  settype($e['published'], 'int');
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  $endorsements[] = $e;
}
$result->free();
$query = "SELECT pc.id, "
        ."UNIX_TIMESTAMP(pe.published) AS published, "
        ."e.type "
        ."FROM publication pe "
        ."INNER JOIN certificate e ON e.id=pe.id AND e.latest=1 AND (e.type='endorse' OR (e.type='report' and e.comment='revoke')) "
        ."INNER JOIN publication pc ON pc.id = e.publicationId "
        ."INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE pe.id=$id ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
while($e = $result->fetch_assoc()) {
  settype($e['id'], 'int');
  settype($e['published'], 'int');
  $id = $e['id'];
  $endorse = ($e['type'] === 'endorse');
  foreach ($endorsements as &$endorsement) {
    if ($endorsement['id'] === $id) {
      if ($endorse)
        $endorsement['endorsedYou'] = $e['published'];
      else # report/revoke
        $endorsement['reportedYou'] = $e['published'];
      break;
    }
  }
}
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
