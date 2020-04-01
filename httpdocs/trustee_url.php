<?php
require_once '../php/database.php';

die("Hello");
/*
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
if (isset($_POST['referendum'])) {
  $referendum = $mysqli->escape_string($_POST['referendum']);
  $query = "SELECT url FROM trustee WHERE `key` = (SELECT trustee FROM referendum WHERE `key` = $referendum)";
} else if isset($_POST['key']) {
  $key = $mysqli->escape_string($_POST['key']);
  $query = "SELECT url FROM trustee WHERE `key` = \"$key\"";
} else
  die("Missing key or referendum argument.");
$result = $mysqli->query($query);
$trustee = $result->fetch_assoc();
$result->free();
if (!$trustee)
  die("Trustee not found.");
die($trustee['url']);
*/
?>
