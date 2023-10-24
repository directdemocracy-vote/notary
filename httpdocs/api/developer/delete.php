<?php
require_once '../../../php/database.php';
require_once '../../../php/sanitizer.php';
require_once 'password.php';

$signature = sanitize_field($_POST["signature"], "base64", "signature");
if ($_POST['password'] !== $developer_password)
  die('Wrong password');
if ($_POST['type'] !== 'citizen')
  die('Only deletion of citizen is supported');

$result = $mysqli->query("SELECT `key` FROM publication WHERE signature='$signature'") or die($msqli->error);
$entry = $result->fetch_assoc();
die($query);
if ($entry)
  $key = $entry['key'];
else
  $key = 'not found';
die("Not yet implemeted: key=$key");
?>
