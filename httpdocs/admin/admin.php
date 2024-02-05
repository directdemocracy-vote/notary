<?php
require_once '../../php/database.php';
require_once 'password.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function query($query) {
  global $mysqli;
  $result = $mysqli->query($query) or error($mysqli->error);
  return $result;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$input = json_decode(file_get_contents("php://input"));

$password = $input->password;
if ($password !== $admin_password)
  error('Wrong password.');

$endorsements = $mysqli->escape_string($input->endorsements);
$signatures = $mysqli->escape_string($input->signatures);
$participations = $mysqli->escape_string($input->participations);
$votes = $mysqli->escape_string($input->votes);
$citizens = $mysqli->escape_string($input->citizens);
$proposals = $mysqli->escape_string($input->proposals);
$areas = $mysqli->escape_string($input->areas);
$results = $mysqli->escape_string($input->results);

$query = "";

function delete_certificicate($mysqli, $type) {
  query("DELETE FROM certificate WHERE type='$type'");
  return $mysqli->affected_rows;
}

function delete_publication($mysqli, $type) {
  query("DELETE FROM $type");
  return $mysqli->affected_rows;
}

$n_endorsement = $endorsements ? delete_certificate($mysqli, 'endorse') : 0;
$n_signature = $signatures ? delete_certificate($mysqli, 'sign') : 0;
$n_participation = $participations ? delete_publication($mysqli, 'participation') : 0;
$n_vote = $votes ? delete_publication($mysqli, 'vote') : 0;
$n_citizen = $citizens ? delete_publication($mysqli, 'citizen') : 0;
$n_proposal = $proposals ? delete_publication($mysqli, 'proposal') : 0;
$n_area = $areas ? delete_publication($mysqli, 'area') : 0;
if ($results)
  query("DELETE FROM results");

$n = $n_citizen + $n_endorsement + $n_proposal + $n_area + $n_signature + $n_participation + $n_vote;

# clean-up obsolete certificates
query("DELETE FROM certificate WHERE certificate.certifiedPublication NOT IN (SELECT id FROM publication)");

# clean-up orphan publications
$query = <<<EOT
DELETE FROM publication WHERE id NOT IN (
    SELECT publication FROM citizen UNION
    SELECT publication FROM certificate UNION
    SELECT publication FROM area UNION
    SELECT publication FROM proposal UNION
    SELECT publication FROM participation UNION
    SELECT publication FROM vote)
EOT;
query($query);
query("DELETE FROM certificate WHERE publication NOT IN (SELECT id FROM publication)");
query("DELETE FROM participation WHERE publication NOT IN (SELECT id FROM publication)");
query("DELETE FROM vote WHERE publication NOT IN (SELECT id FROM publication)");
query("DELETE FROM citizen WHERE publication NOT IN (SELECT id FROM publication)");
query("DELETE FROM area WHERE publication NOT IN (SELECT id FROM publication)");
query("DELETE FROM proposal WHERE publication NOT IN (SELECT id FROM publication)");

$result = query("SELECT MAX(id) AS `max` FROM publication");
if ($result) {
  $m = $result->fetch_assoc();
  $max = intval($m['max']) + 1;
  query("ALTER TABLE publication AUTO_INCREMENT=$max");
} else
  query("ALTER TABLE publication AUTO_INCREMENT=1");

if ($n_citizen) {
  query("DELETE FROM participant WHERE id > 5");
  query("ALTER TABLE participant AUTO_INCREMENT=6");
}

$list = '';
if ($n)
  $list .= ':<ul>';
if ($n_citizen)
  $list .= "<li>citizen: $n_citizen</li>";
if ($n_endorsement)
  $list .= "<li>certificate: $n_endorsement</li>";
if ($n_proposal)
  $list .= "<li>proposal: $n_proposal</li>";
if ($n_area)
  $list .= "<li>area: $n_area</li>";
if ($n_signature)
  $list .= "<li>signature: $n_signature</li>";
if ($n_participation)
  $list .= "<li>participation: $n_participation</li>";
if ($n_vote)
  $list .= "<li>vote: $n_vote</li>";
if ($n)
  $list .= '</ul>';
die("{\"status\":\"Deleted $n publications$list\"}");
 ?>
