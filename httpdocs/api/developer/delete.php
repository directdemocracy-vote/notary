<?php
require_once '../../../php/database.php';
require_once '../../../php/sanitizer.php';
require_once 'password.php';

$signature = sanitize_field($_POST["signature"], "base64", "signature");
if ($_POST['password'] !== $developer_password)
  die('Wrong password');
$type = $_POST['type'];
if ($type === 'citizen') {
  $query = "SELECT participant, id FROM publication WHERE signature=FROM_BASE64('$signature==')";
  $result = $mysqli->query($query) or die($msqli->error);
  $entry = $result->fetch_assoc();
  if (!$entry)
    die("Citizen not found");
  $participant = intval($entry['participant']);
  $id = intval($entry['id']);
  $query = "SELECT id FROM publication WHERE participant=$participant AND `type`='certificate'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $certificate_ids = array();
  while($row = $result->fetch_assoc())
    $certificate_ids[] = intval($row['id']);
  $query = "SELECT publication FROM certificate WHERE certifiedPublication=$id";
  $result = $mysqli->query($query) or die($mysqli->error);
  while($row = $result->fetch_assoc())
    $certificate_ids[] = intval($row['publication']);
  $certificates = implode(',', $certificate_ids);
  $query = "SELECT id FROM publication WHERE participant=$participant AND `type`='participation'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $registration_ids = array();
  while($row = $result->fetch_assoc())
    $registration_ids[] = intval($row['id']);
  $registrations = implode(',', $registration_ids);
  if ($registrations !== '') {
    $mysqli->query("DELETE FROM participation WHERE publication IN ($registrations)") or die($mysqli->error);
    $mysqli->query("DELETE FROM publication WHERE id IN ($registrations)") or die($mysqli->error);
  }
  if ($certificates !== '') {
    $mysqli->query("DELETE FROM certificate WHERE publication IN ($certificates)") or die($mysqli->error);
    $mysqli->query("DELETE FROM publication WHERE id IN ($certificates)") or die($mysqli->error);
  }
  $mysqli->query("DELETE FROM citizen WHERE publication=$id") or die($mysqli->error);
  $mysqli->query("DELETE FROM publication WHERE id=$id") or die($mysqli->error);
  die('OK');
} elseif ($type === 'proposal') {
  $query = "SELECT id FROM publication WHERE `signature`=FROM_BASE64('$signature==') AND `type`='proposal'";
  $result = $mysqli->query($query) or die($msqli->error);
  $entry = $result->fetch_assoc();
  if (!$entry)
    die("Proposal not found");
  $id = intval($entry['id']);
  $query = "SELECT publication FROM certificate WHERE certifiedPublication=$id";
  $result = $mysqli->query($query) or die($mysqli->error);
  $certificate_ids = array();
  while($row = $result->fetch_assoc())
    $certificate_ids[] = intval($row['publication']);
  $certificates = implode(',', $certificate_ids);
  if ($certificates !== '') {
    $mysqli->query("DELETE FROM certificate WHERE publication IN ($certificates)") or die($mysqli->error);
    $mysqli->query("DELETE FROM publication WHERE id IN ($certificates)") or die($mysqli->error);
  }
  # FIXME: we should also delete the publications for vote and participation
  $mysqli->query("DELETE FROM vote WHERE referendum=$id") or die($mysqli->error);
  $mysqli->query("DELETE FROM participation WHERE referendum=$id") or die($mysqli->error);
  $mysqli->query("DELETE FROM proposal WHERE publication=$id") or die($msqli->error);
  $mysqli->query("DELETE FROM publication WHERE id=$id") or die($msqli->error);
  die('OK');
} else
  die('Only deletion of a citizen or a proposal is supported');
?>
