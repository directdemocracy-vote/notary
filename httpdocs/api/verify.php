<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['signature']))
  error('missing signature parameter');
if (!isset($_GET['from']))
  error('missing from parameter');
$signature = sanitize_field($_GET['signature'], 'base64', 'signature');
$from = sanitize_field($_GET['from'], 'hex', 'from');
$query = "SELECT "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(ps.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(pa.`key`), '\\n', ''), '=', '') AS appKey, "
        ."REPLACE(REPLACE(TO_BASE64(vote.appSignature), '\\n', ''), '=', '') AS appSignature, "
        ."REPLACE(REPLACE(TO_BASE64(pp.signature), '\\n', ''), '=', '') AS referendum, "
        ."vote.number, "
        ."vote.area, "
        ."REPLACE(TO_BASE64(vote.ballot), '\\n', '') AS  ballot, "
        ."vote.answer "
        ."FROM vote "
        ."INNER JOIN publication ON publication.id=vote.publication "
        ."INNER JOIN participant AS ps ON ps.id=publication.participant "
        ."INNER JOIN publication AS pp ON pp.id=vote.referendum "
        ."INNER JOIN participant AS pa ON pa.type='app' AND pa.id=vote.app "
        ."WHERE pp.signature=FROM_BASE64('$signature==') AND vote.ballot >= UNHEX('$from') "
        ."ORDER BY vote.ballot LIMIT 100";
$result = $mysqli->query($query) or error($query - ' => ' . $mysqli->error);
if (!$result)
  error('vote not found');
$votes = [];
while($vote = $result->fetch_assoc()) {
  settype($vote['published'], 'int');
  settype($vote['number'], 'int');
  settype($vote['area'], 'int');
  $votes[] = $vote;
}
$result->free();
$mysqli->close();
die(json_encode($votes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
