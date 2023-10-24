<?php
require_once '../../../php/database.php';
require_once '../../../php/sanitizer.php';
require_once 'password.php';

$signature = sanitize_field($_POST["signature"], "base64", "signature");
if ($_POST['password'] !== $developer_password)
  die('Wrong password');
if ($_POST['type'] !== 'citizen')
  die('Only deletion of citizen is supported');
die('Not yet implemeted');
?>
