<?php
# This API entry returns a proposal or a list of proposals corresponding to the parameters of the request.
# Each proposal contains the entries of the proposal message, plus one entry:
# - areas: this field contains the area name as an array of strings.
# The input parameter sets are:
# - search: text to be searched in area, title and description.
# - secret: either 0 for petitions, 1 for referendums or 2 for both.
# - open: either 0 for closed, 1 for open or 2 for both.
# - latitude, longitude and radius: circle intersecting with the area of the referendum.
# - limit (optional, default to 1): maximum number of proposals in the returned list.
# - year (optional): year of the deadline of the proposal.

require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function parameter($parameter, $type='') {
  global $mysqli;

  if (isset($_POST[$parameter]))
    $value = $_POST[$parameter];
  elseif (isset($_GET[$parameter]))
    $value = $_GET[$parameter];
  else
    return null;
  if ($type === 'float')
    return floatval($value);
  elseif ($type === 'int')
    return intval($value);
  elseif ($type === 'bool')
    return (strcasecmp($value, 'true') === 0);
  else
    return $mysqli->escape_string($value);
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$search = parameter('search');
$secret = parameter('secret', 'int');
$open = parameter('open', 'int');
$latitude = parameter('latitude', 'float');
$longitude = parameter('longitude', 'float');
$radius = parameter('radius', 'float') / 100000;
$limit = parameter('limit', 'int');
$year = parameter('year', 'int');

die ("variable: $search $secret $open $latitude $longitude $radius $limit $year");
# check the parameter sets
if (!isset($search) || !isset($secret) || !isset($open) || !isset($latitude) || !isset($longitude) || !isset($radius))
  error('Missing parameters.');

if (!isset($limit))
  $limit = 1;
if (!isset($year))
    $year = date("Y");
if ($secret == 2)
  $secret = '';
else # assuming 0 or 1
  $secret = "proposal.secret = $secret AND ";

if ($open == 2)
  $open = '';
elseif ($open == 0)
  $open = "FROM_UNIXTIME(proposal.deadline) <= NOW() AND ";
else # assuming 1
  $open = "FROM_UNIXTIME(proposal.deadline) > NOW() AND ";
if ($search !== '')
  $search = "(title LIKE \"%$search%\" OR description LIKE \"%$search%\") AND ";
$query = "SELECT "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(TO_BASE64(publication.`key`), '\\n', '') AS `key`, "
        ."REPLACE(TO_BASE64(publication.signature), '\\n', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."proposal.judge, "
        ."REPLACE(TO_BASE64(proposal.area), '\\n', '') as area, "
        ."proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
        ."area.name AS areas "
        ."FROM proposal "
        ."LEFT JOIN publication ON publication.id = proposal.id "
        ."LEFT JOIN publication AS area_p ON proposal.area = area_p.signature "
        ."LEFT JOIN area ON area.id = area_p.id "
        ."WHERE $secret$open$search"
        ."YEAR(FROM_UNIXTIME(proposal.deadline)) = $year "
        ."AND ST_Intersects(area.polygons, ST_Buffer(POINT($longitude, $latitude), $radius)) "
        ."LIMIT $limit";

$result = $mysqli->query($query) or die($mysqli->error);
$proposals = array();
while ($proposal = $result->fetch_assoc()) {
  settype($proposal['published'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
  $proposal['areas'] = explode("\n", $proposal['areas']);
  $proposals[] = $proposal;
}
$result->free();
$mysqli->close();
die(json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
