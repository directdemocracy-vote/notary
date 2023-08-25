<?php
# This API entry returns a proposal or a list of proposals corresponding to the parameters of the request.
# Each proposal contains the entries of the proposal message, plus two entries:
# - participants: the current number of citizien who voted/signed the referendum/petition
# - corpus: the total number of possible participants (based on the home location of citizens endoresed by the judge)
# The input parameter sets are either:
# 1. - secret: either false (for petitions) or true (for referendums)
#    - latitude and longitude: the area of the proposal must include this position
#    - limit: maximum number of proposals in the returned list
#   In this case, the result is a list of proposals for which is deadline is not yet passed, ordered by
#   participation = participants / corpus
# 2. - fingerprint: return a single proposal corresponding to the specified fingerprint
#    - latitude and longitude (optional): the area of the proposal must include this position
#   In case latitude and longitude are provided the answer contains an extra boolean field named inside
#   which is true only if the point given by latitude and longitude is inside the proposal area
# 3. - fingerprints: returns a list of proposals corresponding to the specified coma separated fingerprints

# TODO: implement participation and corpus

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

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("{\"error\":\"Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)\"}");
$mysqli->set_charset('utf8mb4');
$secret = parameter('secret', 'bool');
if (isset($secret))
  $secret = $secret ? 1 : 0;
$latitude = parameter('latitude', 'float');
$longitude = parameter('latitude', 'float');
$limit = parameter('limit', 'int');
$fingerprint = parameter('fingerprint');
$fingerprints = parameter('fingerprints');
if (isset($fingerprints))
  $fingerprints = explode(',', $fingerprints);

# check the parameter sets
if (isset($secret) || isset($latitude) || isset($longitude) || isset($limit)) {
  if (isset($fingerprints))
    error('The fingerprints parameter should not be set together with the secret, latitude, longitude or limit parameters.');
  if (!isset($latitude))
    error('Missing latitude parameter.');
  if (!isset($longitude))
    error('Missing longitude parameter.');
  if (isset($fingerprint)) {
    if (isset($secret) || isset($limit))
      error('The fingerprint parameter should not be set together with the secret or limit parameters.');
  } else {
    if (!isset($secret))
      error('Missing secret parameter.');
    if (!isset($limit))
      error('Missing limit parameter.');
  }
} elseif (isset($fingerprint) && isset($fingerprints))
  error('You cannot set both fingerprint and fingerprints parameters.');
elseif (!isset($fingerprint) && !isset($fingerprints))
  error('Missing parameters.');

function set_types(&$proposal) {
  settype($proposal['published'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
}

function return_results($query) {
  global $mysqli;
  $result = $mysqli->query($query) or die($mysqli->error);
  while ($proposal = $result->fetch_assoc()) {
    $proposal['participation'] = 0;
    $proposal['corpus'] = 0;
    set_types($proposal);
    $proposals[] = $proposal;
  }
  $result->free();
  $mysqli->close();
  die(json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$query_base = "SELECT "
             ."publication.schema, publication.key, publication.signature, publication.published, "
             ."proposal.judge, proposal.area, proposal.title, proposal.description, "
             ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website "
             ."FROM proposal "
             ."LEFT JOIN publication ON publication.id = proposal.id ";

if (isset($fingerprint)) {
  $query = "$query_base WHERE publication.fingerprint = '$fingerprint'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  set_types($proposal);
  $proposal['participation'] = 0;
  $proposal['corpus'] = 0;
  $mysqli->close();
  if (isset($latitude)) {
    $area = $proposal['area'];
    $query = "SELECT area.id "
            ."INNER JOINT publication ON publication.id = area.id "
            ."WHERE publication.fingerprint=SHA1($area) "
            ."AND ST_Contains(area.polygons, POINT($longitude, $latitude))";
    $result = $mysql->query($query) or error($mysqli->error);
    $found = $result->fetch_assoc();
    $proposal['inside'] = ($found) ? true : false;
  }
  $json = json_encode($proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  die($json);
} elseif (isset($fingerprints)) {
  $proposals = array();
  if (isset($fingerprints)) {
    $list = '(';
    foreach($fingerprints as $fingerprint)
      $list .= "\"$fingerprint\",";
    $list = substr($list, 0, -1).')';
  }
  return_results("$query_base WHERE publication.fingerprint IN $list");
} else {  # assuming secret/latitude/longitude/limit parameters
  $now = intval(microtime(true) * 1000);
  return_results($query_base
    ."LEFT JOIN publication AS area_p ON area_p.fingerprint=SHA1(proposal.area) "
    ."LEFT JOIN area ON area.id=area_p.id "
    ."WHERE secret=$secret "
    ."AND proposal.deadline > $now "
    ."AND ST_Contains(area.polygons, POINT($longitude, $latitude)) "
    ."LIMIT $limit");
}
?>
