<?php
require_once '../../../php/database.php';
require_once '../../../php/sanitizer.php';
require_once 'password.php';

$signature = sanitize_field($_POST["signature"], "base64", "signature");
if ($_POST['password'] !== $developer_password)
  die('Wrong password');
if ($_POST['type'] !== 'citizen')
  die('Only deletion of citizen is supported');
$query = "SELECT REPLACE(TO_BASE64(`key`), '\\n', '') AS `key`, id FROM publication WHERE signature=FROM_BASE64('$signature')";
$result = $mysqli->query($query) or die($msqli->error);
$entry = $result->fetch_assoc();
if (!$entry)
  die("Citizen not found");
$key = $entry['key'];
$id = intval($entry['id']);
$query = "SELECT id FROM publication WHERE `key`=FROM_BASE64('$key') AND `type`='endorsement'";
$result = $mysqli->query($query) or die($mysqli->error);
$endorsement_ids = array();
while($row = $result->fetch_assoc())
  $endorsement_ids[] = intval($row['id']);
$query = "SELECT id FROM endorsement WHERE endorsedSignature=FROM_BASE64('$signature')";
$result = $mysqli->query($query) or die($mysqli->error);
while($row = $result->fetch_assoc())
  $endorsement_ids[] = intval($row['id']);
$endorsements = implode(',', $endorsement_ids);
$query = "SELECT id FROM publication WHERE `key`=FROM_BASE64('$key') AND `type`='registration'";
$result = $mysqli->query($query) or die($mysqli->error);
$registration_ids = array();
while($row = $result->fetch_assoc())
  $registration_ids[] = intval($row['id']);
$registrations = implode(',', $registration_ids);
if ($registrations !== '') {
  $mysqli->query("DELETE FROM registration WHERE id IN ($registrations)") or die($mysqli->error);
  $mysqli->query("DELETE FROM publication WHERE id IN ($registrations)") or die($mysqli->error);
}
if ($endorsements !== '') {
  $mysqli->query("DELETE FROM endorsement WHERE id IN ($endorsements)") or die($mysqli->error);
  $mysqli->query("DELETE FROM publication WHERE id IN ($endorsements)") or die($mysqli->error);
}
$mysqli->query("DELETE FROM citizen WHERE id=$id") or die($mysqli->error);
$mysqli->query("DELETE FROM publication WHERE id=$id") or die($mysqli->error);
die("OK");
?>
