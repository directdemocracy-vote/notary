<?php
# This API entry returns a proposal or a list of proposals corresponding to the parameters of the request.
# Each proposal contains the entries of the proposal message, plus three entries:
# - areas: this field contains the area name as an array of strings.
# - participants: the current number of citizien who voted/signed the referendum/petition
# - corpus: the total number of possible participants (based on the home location of citizens endoresed by the judge)
# The input parameter sets are either:
# 1. - search: text to be searched in area, title and description
#    - secret: either 0 for petitions, 1 for referendums or 2 for both.
#    - open: either 0 for closed, 1 for open or 2 for both
#    - latitude, longitude and radius: circle intersecting with the area of the referendum.
#    - limit (optional, default to 1): maximum number of proposals in the returned list
#    - year (optional): year of the deadline of the proposal
#   In this case, the result is a list of proposals for which is deadline is not yet passed, ordered by
#   participation = participants / corpus
# 2. - fingerprint: return a single proposal corresponding to the specified fingerprint
#    - citizen (optional): signature of the citizen (to check judge endorsement, latitude and longitude)
#   In case latitude and longitude are provided the answer contains an extra boolean field named inside
#   which is true only if the point given by latitude and longitude is inside the proposal area
# 3. - fingerprints: returns a list of proposals corresponding to the specified coma separated fingerprints

# TODO: implement participants and corpus

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
$fingerprint = parameter('fingerprint');
$fingerprints = parameter('fingerprints');
if (isset($fingerprints))
  $fingerprints = explode(',', $fingerprints);

# check the parameter sets
if (isset($search) && isset($secret) && isset($open) && isset($latitude) && isset($longitude) && isset($radius)) {
  if (isset($fingerprints) || isset($fingerprint))
    error('The fingerprint or fingerprints parameter should not be set together with the search, secret, open, latitude, longitude and radius parameters.');
} elseif (isset($fingerprint) && isset($fingerprints))
  error('You cannot set both fingerprint and fingerprints parameters.');
elseif (!isset($fingerprint) && !isset($fingerprints))
  error('Missing parameters.');

function set_types(&$proposal) {
  $proposal['schema'] = 'https://directdemocracy.vote/json-schema/' . $proposal['version'] . '/' . $proposal['type'] . '.schema.json';
  unset($proposal['version']);
  unset($proposal['type']);
  settype($proposal['published'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
  $proposal['areas'] = explode("\n", $proposal['areas']);
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
  #die($query);
  die(json_encode($proposals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

$query_base = "SELECT "
             ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
             ."REPLACE(TO_BASE64(publication.`key`), '\\n', '') AS `key`, "
             ."REPLACE(TO_BASE64(publication.signature), '\\n', '') AS signature, "
             ."UNIX_TIMESTAMP(publication.published) AS published, "
             ."proposal.judge, "
             ."REPLACE(TO_BASE64(proposal.area), '\\n', '') AS area, "
             ."proposal.title, proposal.description, "
             ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
             ."area.name AS areas "
             ."FROM proposal "
             ."LEFT JOIN publication ON publication.id = proposal.id "
             ."LEFT JOIN publication AS area_p ON proposal.area = area_p.signature "
             ."LEFT JOIN area ON area.id = area_p.id ";

if (isset($fingerprint)) {
  $query = "SELECT "
          ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
          ."REPLACE(TO_BASE64(publication.`key`), '\\n', '') AS `key`, "
          ."REPLACE(TO_BASE64(publication.signature), '\\n', '') AS signature, "
          ."UNIX_TIMESTAMP(publication.published) AS published, "
          ."proposal.judge, "
          ."REPLACE(TO_BASE64(proposal.area), '\\n', '') AS area, "
          ."proposal.title, proposal.description, "
          ."proposal.question, proposal.answers, proposal.secret, proposal.deadline, proposal.website, "
          ."area.name AS areas "
          ."FROM proposal "
          ."LEFT JOIN publication ON publication.id = proposal.id "
          ."LEFT JOIN publication AS area_p ON proposal.area = area_p.signature "
          ."LEFT JOIN area ON area.id = area_p.id "
          ."WHERE SHA1(publication.signature) = '$fingerprint'";
  $result = $mysqli->query($query) or die($mysqli->error);
  $proposal = $result->fetch_assoc();
  $result->free();
  if (isset($latitude)) {
    $area = $proposal['area'];
    $query = "SELECT area.id FROM area "
            ."LEFT JOIN publication ON publication.id = area.id "
            ."WHERE publication.signature=FROM_BASE64('$area') "
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
  return_results("$query_base WHERE SHA1(publication.signature) IN $list");
} else {  # assuming search/secret/latitude/longitude/radius parameters
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
    $open = "proposal.deadline <= NOW() AND ";
  else # assuming 1
    $open = "proposal.deadline > NOW() AND ";
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
          ."YEAR(FROM_UNIXTIME(proposal.deadline / 1000)) = $year "
          ."AND ST_Intersects(area.polygons, ST_Buffer(POINT($longitude, $latitude), $radius)) "
          ."LIMIT $limit";
  return_results($query);
}
?>
