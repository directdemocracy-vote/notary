<?php
require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
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
$referendums = $mysqli->escape_string($input->referendums);
$areas = $mysqli->escape_string($input->areas);
$registrations = $mysqli->escape_string($input->registrations);
$ballots = $mysqli->escape_string($input->ballots);
$votes = $mysqli->escape_string($input->votes);

$results = $mysqli->escape_string($input->results);

$query = "";

function delete_publication($mysqli, $type) {
  $query = "DELETE publication, $type FROM publication INNER JOIN $type ON $type.id=publication.id";
  $mysqli->query($query) or error($mysqli->error);
  return $mysqli->affected_rows / 2;
}

$n = 0;
if ($citizens)
  $n += delete_publication($mysqli, 'citizen');
if ($endorsements)
  $n += delete_publication($mysqli, 'endorsement');
if ($referendums)
  $n += delete_publication($mysqli, 'referendum');
if ($areas)
  $n += delete_publication($mysqli, 'area');
if ($registrations)
  $n += delete_publication($mysqli, 'registration');
if ($ballots)
  $n += delete_publication($mysqli, 'ballot');
if ($votes)
  $n += delete_publication($mysqli, 'vote');
if ($results) {
  $query = "DELETE FROM results;"; # "DELETE FROM votes; DELETE FROM ballots; DELETE FROM registrations; DELETE FROM stations;";
  $mysqli->query($query) or error($mysqli->error);
}
$query = "SELECT MAX(id) AS `max` FROM publication";
$result = $mysqli->query($query) or error($mysqli->error);
if ($result) {
  $m = $result->fetch_assoc();
  $max = intval($m['max']) + 1;
  $mysqli->query("ALTER TABLE publication AUTO_INCREMENT=$max");
} else
  $mysqli->query("ALTER TABLE publication AUTO_INCREMENT=1");

die("{\"status\":\"Deleted $n publications.\"}");
 ?>
