<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_GET['signature'])) {
  $signature = sanitize_field('get', "base_64", "signature");
  $signature = $mysqli->escape_string($_GET['signature']);
  $condition = "publication.signature=FROM_BASE64('$signature')";
  $join1_condition = "pp.signature=FROM_BASE64('$signature')";
  $join2_condition = "e.endorsedSignature=FROM_BASE64('$signature')";
  $join3_condition = "signature.endorsedSignature=FROM_BASE64('$signature')";
} elseif (isset($_GET['fingerprint'])) {
  $fingerprint = sanitize_field('get', "hex", "fingerprint");
  $condition = "publication.signatureSHA1=UNHEX('$fingerprint')";
  $join1_condition = "pp.signatureSHA1=UNHEX('$fingerprint')";
  $join2_condition = "SHA1(e.endorsedSignature)='$fingerprint'";
  $join3_condition = "SHA1(signature.endorsedSignature)='$fingerprint'";
} else
  error("Missing fingerprint or signature GET parameter");

if (isset($_GET['corpus']))
  $corpus = ($_GET['corpus'] === '1');
else
  $corpus = false;

$query = "SELECT title FROM proposal INNER JOIN publication ON publication.id=proposal.id AND $condition";
$result = $mysqli->query($query) or error($mysqli->error);
$title = $result->fetch_assoc();
$result->free();
if (!$title)
  error("Proposal not found");
$answer = array();
$answer['title'] = $title['title'];
$query = "SELECT REPLACE(TO_BASE64(pc.signature), '\\n', '') AS signature, "
        ."citizen.givenNames, citizen.familyName, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture";
if (!$corpus)
  $query .= ", UNIX_TIMESTAMP(ps.published) AS published";
$query .= " FROM citizen"
         ." INNER JOIN publication AS pc ON pc.id=citizen.id"
         ." INNER JOIN publication AS pp ON $join1_condition"
         ." INNER JOIN proposal ON proposal.id=pp.id";
if ($corpus)
  $query .= " INNER JOIN webservice AS judge ON judge.`type`='judge' AND judge.url=proposal.judge"
         ." INNER JOIN publication AS pe ON pe.`key`=judge.`key`"
         ." INNER JOIN endorsement ON endorsement.id=pe.id AND endorsement.latest=1 AND endorsement.endorsedSignature=pc.signature"
         ." INNER JOIN publication AS pa ON proposal.area=pa.`signature`"
         ." INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))"
         ." WHERE endorsement.`revoke`=0 OR (endorsement.`revoke`=1 AND"
         ." EXISTS(SELECT pep.id FROM publication AS pep"
         ." INNER JOIN endorsement AS e ON e.id=pep.id AND $join2_condition AND e.accepted=1"
         ." WHERE pep.`key`=pc.`key`))";
else
  $query .= " INNER JOIN endorsement AS signature ON $join3_condition AND signature.accepted=1"
           ." INNER JOIN publication AS ps ON ps.id=signature.id AND ps.`key`=pc.`key`";
$query .= " ORDER BY citizen.familyName, citizen.givenNames";

$result = $mysqli->query($query) or error($mysqli->error);
$participants = array();
while ($participant = $result->fetch_assoc()) {
  if ($corpus)
    settype($participant['published'], 'int');
  $participants[] = $participant;
}
$result->free();
if ($corpus) {
  $count = sizeof($participants);
  $query = "UPDATE proposal "
         ." INNER JOIN publication ON publication.id=proposal.id AND $condition"
         ." SET corpus=$count";
  $mysqli->query($query) or error($mysqli->error);
}
$answer['participants'] = $participants;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
