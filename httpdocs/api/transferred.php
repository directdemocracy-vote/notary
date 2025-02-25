<?php
# 1. create a transfer file named after the fingerprint of the tested citizen card in ../../transfer/
# 2. check in the database if the citizen card was reported as tranferred, if yes, return true immediately.
# 3. if not, monitor the created file until it gets deleted, then return true.
# 4. if the file was not deleted after 1 minute, delete it and return false.

require_once '../../php/header.php';
require_once '../../php/sanitizer.php';

if (isset($_GET['fingerprint']))
  $fingerprint = sanitize_field($_GET['fingerprint'], 'hex', 'fingerprint');
else
  die('{"error":"missing fingerprint parameter"}');
$filename = "../../transfers/$fingerprint";
$file = fopen($filename, "w");
fclose($file);

require_once '../../php/database.php';
$query = "SELECT publication.id FROM publication "
        ."INNER JOIN certificate ON certificate.certifiedPublication=publication.id AND certificate.`type`='report' AND certificate.comment='transferred' "
        ."WHERE publication.type='certificate' AND SHA1(publication.signature)='$fingerprint'";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$id = $result->fetch_assoc();
if ($id) {
  unlink($filename);
  die('{"transferred":true}');
}
$counter = 0;
while(file_exists($filename)) {
  usleep(250000); # wait for 0.25 seconds
  $counter += 0.25;
  if ($counter >= 60) {
    unlink($filename);
    die('{"transferred":false}');
  }
}
die('{"transferred":true}');
?>
