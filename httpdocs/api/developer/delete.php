<?php
require_once '../../../php/database.php';
require_once '../../../php/sanitizer.php';
require_once 'password.php';

$signature = sanitize_field($_POST["signature"], "base64", "signature");
if ($_POST['password'] !== $developer_password)
  die('Wrong password');
if ($_POST['type'] !== 'citizen')
  die('Only deletion of citizen is supported');

$result = $mysqli->query("SELECT REPLACE(TO_BASE64(`key`), '\\n', '') AS `key` FROM publication WHERE signature=FROM_BASE64('$signature')") or die($msqli->error);
$entry = $result->fetch_assoc();
if ($entry)
  $key = $entry['key'];
else {
  die("SELECT `key` FROM publication WHERE signature='$signature'");
}
die("Not yet implemeted");
?>
