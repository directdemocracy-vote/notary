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

$citizens = $mysqli->escape_string($input->citizens);
$commitments = $mysqli->escape_string($input->commitments);
$proposals = $mysqli->escape_string($input->proposals);
$areas = $mysqli->escape_string($input->areas);
$participations = $mysqli->escape_string($input->participations);
$votes = $mysqli->escape_string($input->votes);
$results = $mysqli->escape_string($input->results);

$query = "";

function delete_publication($mysqli, $type) {
  query("DELETE publication, $type FROM publication INNER JOIN $type ON $type.id=publication.id");
  return $mysqli->affected_rows / 2;
}

$n_citizen = $citizens ? delete_publication($mysqli, 'citizen') : 0;
$n_commitment = $commitments ? delete_publication($mysqli, 'commitment') : 0;
$n_proposal = $proposals ? delete_publication($mysqli, 'proposal') : 0;
$n_area = $areas ? delete_publication($mysqli, 'area') : 0;
$n_participation = $participations ? delete_publication($mysqli, 'participation') : 0;
$n_vote = $votes ? delete_publication($mysqli, 'vote') : 0;
if ($results)
  query("DELETE FROM results");

$n = $n_citizen + $n_commitment + $n_proposal + $n_area + $n_participation + $n_vote;

# clean-up orphan commitments
query("DELETE FROM commitment WHERE commitment.publication NOT IN (SELECT signature FROM publication)");

# clean-up orphan publications
$query = <<<EOT
DELETE FROM publication WHERE id NOT IN (
    SELECT id FROM citizen UNION
    SELECT id FROM commitment UNION
    SELECT id FROM area UNION
    SELECT id FROM proposal UNION
    SELECT id FROM participation UNION
    SELECT id FROM vote)
EOT;

query($query);
query("DELETE FROM citizen WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM commitment WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM area WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM proposal WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM participation WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM vote WHERE id NOT IN (SELECT id FROM publication)");

$result = query("SELECT MAX(id) AS `max` FROM publication");
if ($result) {
  $m = $result->fetch_assoc();
  $max = intval($m['max']) + 1;
  query("ALTER TABLE publication AUTO_INCREMENT=$max");
} else
  query("ALTER TABLE publication AUTO_INCREMENT=1");

$list = '';
if ($n)
  $list .= ':<ul>';
if ($n_citizen)
  $list .= "<li>citizen: $n_citizen</li>";
if ($n_commitment)
  $list .= "<li>commitment: $n_commitment</li>";
if ($n_proposal)
  $list .= "<li>proposal: $n_proposal</li>";
if ($n_area)
  $list .= "<li>area: $n_area</li>";
if ($n_participation)
  $list .= "<li>participation: $n_participation</li>";
if ($n_vote)
  $list .= "<li>vote: $n_vote</li>";
if ($n)
  $list .= '</ul>';
die("{\"status\":\"Deleted $n publications$list\"}");
 ?>
