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
$endorsements = $mysqli->escape_string($input->endorsements);
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

$n = 0;
if ($citizens)
  $n += delete_publication($mysqli, 'citizen');
if ($endorsements)
  $n += delete_publication($mysqli, 'endorsement');
if ($proposals)
  $n += delete_publication($mysqli, 'proposal');
if ($areas)
  $n += delete_publication($mysqli, 'area');
if ($participations)
  $n += delete_publication($mysqli, 'participation');
if ($votes)
  $n += delete_publication($mysqli, 'vote');
if ($results) {
  query("DELETE FROM results");
  query("DELETE FROM participation");
  query("DELETE FROM corpus");
  query("DELETE FROM votes");
  query("DELETE FROM participations");
  query("DELETE FROM stations");
}

# clean-up orphan endorsements
query("DELETE FROM endorsement WHERE endorsement.endorsedSignature NOT IN (SELECT signature FROM publication)");

# clean-up orphan publications
$query = <<<EOT
DELETE FROM publication WHERE id NOT IN (
    SELECT id FROM citizen UNION
    SELECT id FROM endorsement UNION
    SELECT id FROM area UNION
    SELECT id FROM proposal UNION
    SELECT id FROM participation UNION
    SELECT id FROM vote)
EOT;

query($query);
query("DELETE FROM citizen WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM endorsement WHERE id NOT IN (SELECT id FROM publication)");
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

die("{\"status\":\"Deleted $n publications.\"}");
 ?>
