<?php
# This API entry returns a proposal or a list of proposals corresponding to the parameters of the request.
# Each proposal contains the entries of the proposal message, plus three entries:
# - area: instead of being the area signature, this field contains the area name as an array of strings.
# - participants: the current number of citizien who voted/signed the referendum/petition
# - corpus: the total number of possible participants (based on the home location of citizens endoresed by the judge)
# The input parameter sets are either:
# 1. - search: text to be searched in area, title and description
#    - type: either 1 for petitions or 2 for referendums or 3 for both.
#    - latitude and longitude: point inside the referendum area.
#    - limit (optional, default to 1): maximum number of proposals in the returned list
#    - year (optional): year of the deadline of the proposal
#   In this case, the result is a list of proposals for which is deadline is not yet passed, ordered by
#   participation = participants / corpus
# 2. - fingerprint: return a single proposal corresponding to the specified fingerprint
#    - citizen (optional): signature of the citizen (to check judge endorsement, latitude and longitude)
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
$search = parameter('search');
$type = parameter('type', 'int');
$latitude = parameter('latitude', 'float');
$longitude = parameter('longitude', 'float');
$limit = parameter('limit', 'int');
$year = parameter('year', 'int');
$fingerprint = parameter('fingerprint');
$fingerprints = parameter('fingerprints');
if (isset($fingerprints))
  $fingerprints = explode(',', $fingerprints);

# check the parameter sets
if (isset($search) && isset($type) && isset($latitude) && isset($longitude)) {
  if (isset($fingerprints) || isset($fingerprint))
    error('The fingerprint or fingerprints parameter should not be set together with the search, type, latitude and longitude parameters.');
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
  $name = $proposal['name'];
  unset($proposal['name']);
  $proposal['area'] = explode("\n", $name);
}

function return_results($query) {
  global $mysqli;
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposals = array();
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
             ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
             ."area.name "
             ."FROM proposal "
             ."LEFT JOIN publication ON publication.id = proposal.id "
             ."LEFT JOIN publication AS area_p ON proposal.area = area_p.signature "
             ."LEFT JOIN area ON area.id = area_p.id ";

if (isset($fingerprint)) {
  $query = "$query_base WHERE publication.fingerprint = '$fingerprint'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  if (isset($latitude)) {
    $area = $proposal['area'];
    $query = "SELECT area.id FROM area "
            ."LEFT JOIN publication ON publication.id = area.id "
            ."WHERE publication.fingerprint=SHA1('$area') "
            ."AND ST_Contains(area.polygons, POINT($longitude, $latitude))";
    $result = $mysqli->query($query) or error($mysqli->error);
    $proposal['inside'] = $result->fetch_assoc() ? true : false;
    $result->free();
  }
  $mysqli->close();
  set_types($proposal);
  $proposal['participation'] = 0;
  $proposal['corpus'] = 0;
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
} else {  # assuming search/type/latitude/longitude parameters
  if (!isset($limit))
    $limit = 1;
  if (!isset($year))
     $year = date("Y");
  if ($type == 1)
    $secret = 'secret = 0 AND ';
  elseif ($type == 2)
    $secret == 'secret = 1 AND ';
  else # assuming 3
    $secret = '';
  if ($search !== '')
    $search = '(title LIKE "%$search%" OR description LIKE "%$search%") AND ';
  $now = intval(microtime(true) * 1000);
  return_results($query_base
    ."WHERE $secret$search"
    ."YEAR(FROM_UNIXTIME(proposal.deadline / 1000)) = $year "
    ."AND ST_Contains(area.polygons, POINT($longitude, $latitude)) "
    ."LIMIT $limit");
}
?>
