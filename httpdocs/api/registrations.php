<?php
require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$proposal = $mysqli->escape_string(get_string_parameter('proposal'));
if (!$proposal)
  die("Missing proposal argument");

$now = intval(microtime(true) * 1000);  # milliseconds
$query = "SELECT publication.`version`, TO_BASE64(publication.`key`) AS `key`, TO_BASE64(publication.signature) AS signature, UNIX_TIMESTAMP(publication.published), "
        ."registration.proposal, TO_BASE64(registration.stationKey) AS stationKey, TO_BASE64(registration.stationSignature) AS stationSignature "
        ."FROM registration LEFT JOIN publication ON publication.id=registration.id "
        ."WHERE registration.proposal = FROM_BASE64('$proposal') AND published <= NOW()";
$result = $mysqli->query($query) or error($mysqli->error);
$registrations = [];
if ($result) {
  while ($registration = $result->fetch_assoc()) {
    $registration['schema'] = 'https://directdemocracy.vote/json-schema/' . $registration['version'] . '/registration.schema.json';
    unset($registration['version']);
    $registration['published'] = intval($registration['published']);
    $station_key = $registration['stationKey'];
    $station_signature = $registration['stationSignature'];
    unset($registration['stationKey']);
    unset($registration['stationSignature']);
    $registration['station'] = array('key' => $station_key, 'signature' => $station_signature);
    array_push($registrations, $registration);
  }
  $result->free();
}
$query = "SELECT publication.`version`, TO_BASE64(publication.`key`) AS `key`, TO_BASE64(publication.signature) AS signature, UNIX_TIMESTAMP(publication.published), "
        ."TO_BASE64(ballot.proposal) AS proposal, TO_BASE64(ballot.stationKey) AS stationKey, TO_BASE64(ballot.stationSignature) AS stationSignature "
        ."FROM ballot LEFT JOIN publication ON publication.id=ballot.id "
        ."WHERE ballot.proposal = FROM_BASE64('$proposal')"; // AND published <= NOW()"; FIXME
$result = $mysqli->query($query) or error($mysqli->error);
$ballots = [];
if ($result) {
  while ($ballot = $result->fetch_assoc()) {
    $ballot['schema'] = 'https://directdemocracy.vote/json-schema/' . $ballot['version'] . '/ballot.schema.json';
    unset($registration['version']);
    $ballot['published'] = intval($ballot['published']);
    $station_key = $ballot['stationKey'];
    $station_signature = $ballot['stationSignature'];
    unset($ballot['stationKey']);
    unset($ballot['stationSignature']);
    $ballot['station'] = array('key' => $station_key, 'signature' => $station_signature);
    array_push($ballots, $ballot);
  }
  $result->free();
}
$mysqli->close();
$response = array('registrations' => $registrations, 'ballots' => $ballots);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
