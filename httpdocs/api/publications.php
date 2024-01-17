<?php
# used by the judge to retrive the new endorse and report certificates
# currently only support this use case

require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

$version = 2;

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$type = isset($_GET['type']) ? $mysqli->escape_string($_GET["type"]) : null;
$certificate_type = isset($_GET['certificate_type']) ? $mysqli->escape_string($_GET['certificate_type']) : null;
$since = isset($_GET['since']) ? sanitize_field($_GET['since'], 'positive_int', 'since') : null;
$until = isset($_GET['until']) ? sanitize_field($_GET['until'], 'positive_int', 'until') : null;

if ($type !== 'certificate' || $certificate_type !== 'endorse report')
  error('supportint only type=certificate&certificate_type=endorse+report');

$condition = '';
if ($since)
  $condition .="UNIX_TIMESTAMP(publication.published) >= $since AND ";
if ($until)
  $condition .="UNIX_TIMESTAMP(publication.published) <= $until AND ";
$condition .= "publication.version=$version AND publication.type='$type'";
$app_fields = "REPLACE(REPLACE(TO_BASE64(app.`key`), '\\n', ''), '=', '') AS appKey, REPLACE(REPLACE(TO_BASE64($type.appSignature), '\\n', ''), '=', '') AS appSignature, ";
$app_join = "INNER JOIN participant AS app ON app.id=$type.app ";
$certificate_fields = "certificate.type, REPLACE(REPLACE(TO_BASE64(certifiedPublication.`signature`), '\\n', ''), '=', '') AS publication, certificiate.comment, certificate.message ";
$certificate_join = "INNER JOIN publication AS certifiedPublication ON certifiedPublication.id=certificate.certifiedPublication ";
$query = "SELECT CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        .$app_fields
        .$certificate_fields
        ."FROM $type "
        ."INNER JOIN publication ON publication.id=$type.publication AND $condition "
        ."INNER JOIN participant ON participant.id=publication.participant "
        .$app_join
        .$certificate_join;
$result = $mysqli->query($query) or error($mysqli->error);
$publications = [];
if ($result) {
  while($publication = $result->fetch_object()) {
    $publication->published = intval($publication->published);
    if ($type == 'certificate') {
      if ($publication->message == '')
        unset($publication->message);
      if ($publication->comment == '')
        unset($publication->comment);
    }
    $publications[] = $publication;
  }
  $result->free();
}
$mysqli->close();
echo json_encode($publications, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
