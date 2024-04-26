<?php

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['judge']))
  error('Missing judge parameter');
$commune = isset($_GET['commune']) ? sanitize_field($_GET['commune'], 'positive_int', 'commune') : null;
if (isset($_GET['type']))
  $type = ($_GET['type'] === 'inactive' || $_GET['type'] === 'active') ? $_GET['type'] : '';
else
  $type = '';
$familyName = isset($_GET['familyName']) ? $mysqli->escape_string($_GET['familyName']) : null;
$givenNames = isset($_GET['givenNames']) ? $mysqli->escape_string($_GET['givenNames']) : null;
$judge = sanitize_field($_GET['judge'], 'url', 'judge');

$key = '';
$result = $mysqli->query("SELECT REPLACE(REPLACE(TO_BASE64(`key`), '\\n', ''), '=', '') AS `key` FROM participant INNER JOIN webservice ON webservice.participant=participant.id WHERE participant.`type`='judge' AND webservice.url='$judge'") or error($mysqli->error);
if ($j = $result->fetch_assoc())
  $key = $j['key'];
$result->free();

$query = "SELECT citizen.familyName, citizen.givenNames, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture, "
        ."UNIX_TIMESTAMP(publication.published) AS published, REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature "
        ."FROM citizen "
        ."INNER JOIN publication ON publication.id = citizen.publication "
        ."INNER JOIN participant ON participant.id=publication.participant ";
if ($type === 'active')
  $query.= "INNER JOIN certificate ON certificate.certifiedPublication = publication.id AND certificate.type = 'trust' AND certificate.latest = 1 "
          ."INNER JOIN publication AS pe ON pe.id=certificate.publication "
          ."INNER JOIN participant AS pep ON pep.id=pe.participant AND pep.`key` = FROM_BASE64('$key==') ";
elseif ($type === 'inactive')
  $query.= "LEFT JOIN certificate ON certificate.certifiedPublication = publication.id AND certificate.type = 'distrust' AND certificate.latest = 1 "
          ."LEFT JOIN publication AS pe ON pe.id=certificate.publication "
          ."LEFT JOIN participant AS pep ON pep.id=pe.participant AND pep.`key` = FROM_BASE64('$key==') ";  
$query.= "WHERE status='active'";
if ($familyName or $givenNames) {
  $query .= " AND";
  if ($familyName) {
    $query .= " familyName LIKE \"%$familyName%\"";
    if ($givenNames)
      $query .= " AND";
  }
  if ($givenNames)
    $query .= " givenNames LIKE \"%$givenNames%\"";
}
$query .= " LIMIT 20;";
$result = $mysqli->query($query) or die($mysqli->error);
$citizens = array();
while ($citizen = $result->fetch_assoc()) {
  $citizen['published'] = intval($citizen['published']);
  unset($citizen['type']);
  $citizens[] = $citizen;
}
$result->free();
$citizens[] = $query; # debug
echo json_encode($citizens, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>
