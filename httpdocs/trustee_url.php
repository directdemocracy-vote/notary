<?php
require_once '../php/database.php';

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
if (isset($_GET['referendum'])) {
  $referendum = $mysqli->escape_string($_GET['referendum']);
  $query = "SELECT trustee.url FROM trustee LEFT JOIN referendum WHERE trustee.`key` = referendum.trustee AND referendum.`key` = \"$referendum\"";
} else if (isset($_GET['key'])) {
  $key = $mysqli->escape_string($_GET['key']);
  $query = "SELECT url FROM trustee WHERE `key` = \"$key\"";
} else
  die("Missing key or referendum argument.");
$result = $mysqli->query($query);
if (!$result)
  die("Trustee not found. $query");
$trustee = $result->fetch_assoc();
$result->free();
die($trustee['url']);
?>
