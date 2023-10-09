<?php
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_POST['judge']))
  $judge = sanitize_field('post', 'url', 'judge');
else
  $judge = 'https://judge.directdemocracy.vote';

$query = "SELECT REPLACE(TO_BASE64(`key`), '\\n', '') AS `key` FROM webservice WHERE `type`='judge' AND url=\"$judge\"";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$webservice = $result->fetch_assoc();
$result->free();
if (!$webservice) {
  $file = file_get_contents("$judge/api/key.php");
  $j = json_decode($file);
  $judge_key = sanitize_field($j->key, "base_64", "judge_key");
  $mysqli->query("INSERT INTO webservice(`type`, `key`, url) VALUES('judge', FROM_BASE64('$judge_key'), '$judge')") or die($mysqli->error);
} else
  $judge_key = $webservice['key'];

$query = "SELECT "
        ."UNIX_TIMESTAMP(endorsement_p.published) AS published, "
        ."endorsement.revoke, endorsement.latest, citizen.familyName, citizen.givenNames, "
        ."REPLACE(TO_BASE64(citizen_p.signature), '\\n', '') AS signature "
        ."FROM publication AS endorsement_p "
        ."INNER JOIN endorsement ON endorsement.id = endorsement_p.id "
        ."INNER JOIN publication AS citizen_p ON citizen_p.signature = endorsement.endorsedSignature "
        ."INNER JOIN citizen ON citizen.id = citizen_p.id "
        ."WHERE endorsement_p.`key` = FROM_BASE64('$judge_key') "
        ."ORDER BY endorsement_p.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['revoke'], 'bool');
  settype($endorsement['latest'], 'bool');
  $endorsements[] = $endorsement;
}
$result->free();
$answer = array();
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
