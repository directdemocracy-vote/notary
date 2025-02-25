<?php
require_once '../../php/header.php';
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

if (isset($_GET['signature'])) {
  $signature = sanitize_field($_GET["signature"], "base64", "signature");
  $condition = "publication.signature=FROM_BASE64('$signature==')";
  $join_condition = "pp.signature=FROM_BASE64('$signature==')";
} elseif (isset($_GET['fingerprint'])) {
  $fingerprint = sanitize_field($_GET["fingerprint"], "hex", "fingerprint");
  $condition = "publication.signatureSHA1=UNHEX('$fingerprint')";
  $join_condition = "pp.signatureSHA1=UNHEX('$fingerprint')";
} else
  error("Missing fingerprint or signature GET parameter");

$corpus = isset($_GET['corpus']) ? $_GET['corpus'] === '1' : false;
$query = "SELECT title, proposal.type FROM proposal INNER JOIN publication ON publication.id=proposal.publication AND $condition";
$result = $mysqli->query($query) or error($mysqli->error);
$answer = $result->fetch_assoc();
$result->free();
if (!$answer)
  error("Proposal not found");
$type = $answer['type'];
$query = "SELECT REPLACE(REPLACE(TO_BASE64(pc.signature), '\\n', ''), '=', '') AS signature, "
        ."citizen.givenNames, citizen.familyName, "
        ."CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture";
if (!$corpus)
  $query .= ", UNIX_TIMESTAMP(ps.published) AS published";
$query .= " FROM citizen"
         ." INNER JOIN publication AS pc ON pc.id=citizen.publication"
         ." INNER JOIN publication AS pp ON $join_condition"
         ." INNER JOIN proposal ON proposal.publication=pp.id";
if ($corpus)
  $query .= " INNER JOIN participant AS judge ON judge.`type`='judge' AND judge.id=pp.participant"
         ." INNER JOIN publication AS pe ON pe.participant=judge.id"
         ." INNER JOIN certificate ON certificate.publication=pe.id AND certificate.latest=1 AND certificate.certifiedPublication=pc.id"
         ." INNER JOIN area ON area.id=proposal.area AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))"
         ." INNER JOIN publication AS pa ON pa.id = area.publication AND pa.participant=pp.participant"
         ." WHERE certificate.type='trust' OR (certificate.type='report' AND"
         ." EXISTS(SELECT pep.id FROM publication AS pep"
         ." INNER JOIN certificate AS e ON e.publication=pep.id AND e.certifiedPublication=pp.id"
         ." WHERE pep.id=pc.id))";
elseif ($type === 'petition')
  $query .= " INNER JOIN certificate AS signature ON signature.certifiedPublication=pp.id"
           ." INNER JOIN publication AS ps ON ps.id=signature.publication AND ps.participant=pc.participant";
else
  $query .= " INNER JOIN participation ON participation.referendum=pp.id"
           ." INNER JOIN publication AS ps ON ps.id=participation.publication AND ps.participant=pc.participant";
$query .= " ORDER BY citizen.familyName, citizen.givenNames";
$result = $mysqli->query($query) or error($mysqli->error);
$answer['query'] = $query;
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
         ." INNER JOIN publication ON publication.id=proposal.publication AND $condition"
         ." SET corpus=$count";
  $mysqli->query($query) or error($mysqli->error);
}
$answer['participants'] = $participants;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
