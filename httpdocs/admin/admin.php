<?php
require_once '../../php/database.php';

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
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$input = json_decode(file_get_contents("php://input"));

$password = $input->password;
if ($password !== $database_password)
  error('Wrong password.');

$citizens = $mysqli->escape_string($input->citizens);
$endorsements = $mysqli->escape_string($input->endorsements);
$proposals = $mysqli->escape_string($input->proposals);
$areas = $mysqli->escape_string($input->areas);
$registrations = $mysqli->escape_string($input->registrations);
$ballots = $mysqli->escape_string($input->ballots);

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
if ($registrations)
  $n += delete_publication($mysqli, 'registration');
if ($ballots)
  $n += delete_publication($mysqli, 'ballot');
if ($results) {
  query("DELETE FROM results");
  query("DELETE FROM participation");
  query("DELETE FROM corpus");
  query("DELETE FROM ballots");
  query("DELETE FROM registrations");
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
    SELECT id FROM registration UNION
    SELECT id FROM ballot)
EOT;
// FIXME: we should add "participation" and "vote" to the above list

query($query);
query("DELETE FROM citizen WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM endorsement WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM area WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM proposal WHERE id NOT IN (SELECT id FROM publication)");
// query("DELETE FROM participation WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM registration WHERE id NOT IN (SELECT id FROM publication)");
query("DELETE FROM ballot WHERE id NOT IN (SELECT id FROM publication)");
// query("DELETE FROM vote WHERE id NOT IN (SELECT id FROM publication)");


$result = query("SELECT MAX(id) AS `max` FROM publication");
if ($result) {
  $m = $result->fetch_assoc();
  $max = intval($m['max']) + 1;
  query("ALTER TABLE publication AUTO_INCREMENT=$max");
} else
  query("ALTER TABLE publication AUTO_INCREMENT=1");

die("{\"status\":\"Deleted $n publications.\"}");
 ?>
