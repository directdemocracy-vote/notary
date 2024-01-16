<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_POST['judge']))
  $judge = sanitize_field($_POST["judge"], "url", "judge");
else
  $judge = "https://judge.directdemocracy.vote";

$query = "SELECT id, REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM participant INNER JOIN webservice ON webservice.participant=participant.id WHERE participant.`type`='judge' AND webservice.url=\"$judge\"";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$webservice = $result->fetch_assoc();
$result->free();
if (!$webservice) {
  $file = file_get_contents("$judge/api/key.php");
  $j = json_decode($file);
  $judge_key = sanitize_field($j->key, "base64", "judge_key");
  $mysqli->query("INSERT INTO participant(`type`, `key`) VALUE('judge', FROM_BASE64('$judge_key=='))";
  $judge_id = $mysqli->insert_id;
  $mysqli->query("INSERT INTO webservice(participant, url) VALUES($judge_id, '$judge')") or die($mysqli->error);
} else
  $judge_id = intval($webservice['id']);
$query = "SELECT "
        ."UNIX_TIMESTAMP(certificate_p.published) AS published, "
        ."certificate.type, certificate.latest, citizen.familyName, citizen.givenNames, "
        ."REPLACE(REPLACE(TO_BASE64(citizen_p.signature), '\\n', ''), '=', '') AS signature "
        ."FROM publication AS certificate_p "
        ."INNER JOIN certificate ON certificate.id = certificate_p.id "
        ."INNER JOIN publication AS citizen_p ON citizen_p.id = certificate.publicationId "
        ."INNER JOIN citizen ON citizen.id = citizen_p.id "
        ."WHERE certificate_p.participant = $judge_id AND (certificate.type='endorse' OR certificate.type='report') "
        ."ORDER BY certificate_p.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['latest'], 'bool');
  $endorsements[] = $endorsement;
}
$result->free();
$answer = array();
$answer['endorsements'] = $endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
