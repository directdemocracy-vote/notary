<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");


if (isset($_GET['fingerprint'])) {
  $fingerprint = sanitize_field($_GET["fingerprint"], "hex", "fingerprint");
  $condition = "publication.signatureSHA1=UNHEX('$fingerprint')";
  $join_condition = "SHA1(certificate.publication)='$fingerprint'";
} elseif (isset($_GET['signature'])) {
  $signature = sanitize_field($_GET["signature"], "base64", "signature");
  $condition = "publication.signature=FROM_BASE64('$signature==')";
  $join_condition = "certificate.publication=FROM_BASE64('$signature==')";
} else
  error("Missing fingerprint or signature parameter");


if (isset($_GET['judge']))
  $judge = sanitize_field($_GET["judge"], "url", "judge");
else
  error("Missing judge parameter");


$query = "SELECT citizen.givenNames, citizen.familyName FROM citizen INNER JOIN publication ON publication.id=citizen.id "
        ."AND $condition";
$result = $mysqli->query($query) or error($mysqli->error);
$citizen = $result->fetch_assoc();
$result->free();
$answer = array();
$answer['givenNames'] = $citizen['givenNames'];
$answer['familyName'] = $citizen['familyName'];
$query = "SELECT UNIX_TIMESTAMP(publication.published) AS published, certificate.type, certificate.latest FROM publication"
        ." INNER JOIN participant AS judge ON judge.`type`='judge' AND judge.`key`=publication.`key`
        ." INNER JOIN webservice ON webservice.participant=participant.id AND webservice.url='$judge'"
        ." INNER JOIN certificate ON certificate.id=publication.id AND $join_condition"
        ." ORDER BY publication.published DESC";
$result = $mysqli->query($query) or error($mysqli->error);
$endorsements = array();
while ($endorsement = $result->fetch_assoc()) {
  settype($endorsement['published'], 'int');
  settype($endorsement['latest'], 'int');
  $endorsements[] = $endorsement;
}
$result->free();
$answer['endorsements'] = $endorsements;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
