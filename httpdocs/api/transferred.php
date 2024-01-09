<?php
# 1. create a transfer file named after the fingerprint of the tested citizen card in ../../transfer/
# 2. check in the database if the citizen card was reported as tranferred, if yes, return true immediately.
# 3. if not, monitor the created file until it gets deleted, then return true.
# 4. if the file was not deleted after 1 minute, delete it and return false.

$filename = $_GET['fingerprint'];

$file = fopen("../../transfer/$filename", "w");
fclose($file);

?>
