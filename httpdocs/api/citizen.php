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

$query = "SELECT participant.id, publication.id AS publication, "
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
        ."INNER JOIN citizen ON publication.id = citizen.publication "
        ."INNER JOIN participant ON participant.id = publication.participant "
        ."INNER JOIN participant AS app ON app.id = citizen.app "
        ."WHERE $condition";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $condition\"}");
$result->free();
$alice_id = intval($citizen['id']);
$alice_publication = intval($citizen['publication']);
unset($citizen['id']);
unset($citizen['publication']);
settype($citizen['published'], 'int');
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
# list all the bobs endorsed by alice
$bob_query = "SELECT publication_bob.id, "
            ."REPLACE(REPLACE(TO_BASE64(participant_bob.`key`), '\\n', ''), '=', '') AS `key`, "
            ."REPLACE(REPLACE(TO_BASE64(publication_bob.signature), '\\n', ''), '=', '') AS signature, "
            ."UNIX_TIMESTAMP(publication_bob.published) AS published, "
            ."REPLACE(REPLACE(TO_BASE64(app.key), '\\n', ''), '=', '') AS appKey, "
            ."REPLACE(REPLACE(TO_BASE64(bob.appSignature), '\\n', ''), '=', '') AS appSignature, "
            ."bob.familyName, bob.givenNames, "
            ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(bob.picture), '\\n', '')) AS picture, "
            ."ST_Y(bob.home) AS latitude, ST_X(bob.home) AS longitude, "
            ."REPLACE(REPLACE(TO_BASE64(pe.signature), '\\n', ''), '=', '') AS certificateSignature, "
            ."e.type, e.comment, "
            ."UNIX_TIMESTAMP(pe.published) AS certificatePublished "
            ."FROM publication pe "
            ."INNER JOIN certificate AS e ON e.publication=pe.id AND (e.type='endorse' OR (e.type='report' AND e.comment LIKE \"revoked+%\")) AND e.latest=1 ";
$query = $bob_query
        ."INNER JOIN publication AS publication_bob ON publication_bob.id=e.certifiedPublication "
        ."INNER JOIN citizen AS bob ON bob.publication=publication_bob.id "
        ."INNER JOIN participant AS participant_bob ON participant_bob.id=publication_bob.participant "
        ."INNER JOIN participant AS app ON app.id=bob.app "
        ."WHERE pe.participant=$alice_id ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
if (!$result)
  die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['id'], 'int');
  settype($e['published'], 'int');
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  if ($e['type'] === 'report') {
    $e['reported'] = $e['certificatePublished'];
    $e['reportedComment'] = $e['comment'];
    $e['reportedSignature'] = $e['certificateSignature'];
  } else { # endorse
    $e['endorsed'] = $e['certificatePublished'];
    $e['endorsedSignature'] = $e['certificateSignature'];
  }
  unset($e['comment']);
  unset($e['certificatePublished']);
  unset($e['type']);
  unset($e['certificateSignature']);
  $endorsements[] = $e;
}
$result->free();
# list all the bobs who endorsed alice
$query = $bob_query
        ."INNER JOIN publication AS pe_bob ON pe_bob.id=e.publication "
        ."INNER JOIN participant AS participant_bob ON participant_bob.id=pe_bob.participant "
        ."INNER JOIN publication AS publication_bob ON publication_bob.participant=participant_bob.id AND publication_bob.type='citizen' "
        ."INNER JOIN citizen AS bob ON bob.publication=publication_bob.id "
        ."INNER JOIN participant AS app ON app.id=bob.app "
        ."WHERE e.certifiedPublication=$alice_publication ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
while($e = $result->fetch_assoc()) {
  settype($e['id'], 'int');
  settype($e['published'], 'int');
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  if ($e['type'] === 'report') {
    $e['reportedYou'] = $e['certificatePublished'];
    $e['reportedYouComment'] = $e['comment'];
    $e['reportedYouSignature'] = $e['certificateSignature'];
  } else { # endorse
    $e['endorsedYou'] = $e['certificatePublished'];
    $e['endorsedYouSignature'] = $e['certificateSignature'];
  }
  unset($e['comment']);
  unset($e['certificatePublished']);
  unset($e['type']);
  unset($e['certificateSignature']);
  $id = intval($e['id']);
  $found = false;
  foreach ($endorsements as &$endorsement) {
    if ($endorsement['id'] === $id) {
      $found = true;
      if (isset($e['reportedYou'])) {
        $endorsement['reportedYou'] = $e['reportedYou'];
        $endorsement['reportedYouComment'] = $e['reportedYouComment'];
        $endorsement['reportedYouSignature'] = $e['reportedYouSignature'];
      } else { # endorsedYou
        $endorsement['endorsedYou'] = $e['endorsedYou'];
        $endorsement['endorsedYouSignature'] = $e['endorsedYouSignature'];
      }
      break;
    }
  }
  if (!$found)
    $endorsements[] = $e;
}
foreach ($endorsements as &$endorsement)
  unset($endorsement['id']);
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
