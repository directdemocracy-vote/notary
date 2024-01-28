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
require_once '../../php/sanitizer.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$search = $mysqli->escape_string($_GET["search"]);
$secret = sanitize_field($_GET["secret"], "(0|1|2)", "secret");
$open = sanitize_field($_GET["open"], "(0|1|2)", "open");
$latitude = sanitize_field($_GET["latitude"], "float", "latitude");
$longitude = sanitize_field($_GET["longitude"], "float", "longitude");
$radius = sanitize_field($_GET["radius"], "positive_float", "radius") / 100000;
$offset = sanitize_field($_GET["offset"], "positive_int", "offset");
$limit = sanitize_field($_GET["limit"], "positive_int", "limit");
$year = sanitize_field($_GET["year"], "year", "year");

# check the parameter sets
if (!isset($search) || !isset($secret) || !isset($open) || !isset($latitude) || !isset($longitude) || !isset($radius))
  error('Missing parameters.');

if (!isset($offset))
  $offset = 0;
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

$query_common_part = "FROM proposal "
                    ."LEFT JOIN publication ON publication.id = proposal.publication "
                    ."LEFT JOIN publication AS area_p ON proposal.area = area_p.id "
                    ."LEFT JOIN area ON area.publication = area_p.id "
                    ."LEFT JOIN participant ON participant.id = publication.participant AND participant.type='judge' "
                    ."LEFT JOIN webservice ON webservice.participant=participant.id "
                    ."WHERE $secret$open$search"
                    ."YEAR(proposal.deadline) = $year "
                    ."AND ST_Intersects(area.polygons, ST_Buffer(POINT($longitude, $latitude), $radius))";
$query = "SELECT "
        ."CONCAT('https://directdemocracy.vote/json-schema/', publication.`version`, '/', publication.`type`, '.schema.json') AS `schema`, "
        ."REPLACE(REPLACE(TO_BASE64(participant.`key`), '\\n', ''), '=', '') AS `key`, "
        ."REPLACE(REPLACE(TO_BASE64(publication.signature), '\\n', ''), '=', '') AS signature, "
        ."UNIX_TIMESTAMP(publication.published) AS published, "
        ."REPLACE(REPLACE(TO_BASE64(proposal.area), '\\n', ''), '=', '') as area, "
        ."proposal.title, proposal.description, "
        ."proposal.question, proposal.answers, proposal.type, proposal.secret, UNIX_TIMESTAMP(proposal.deadline) AS deadline, proposal.trust, proposal.website, "
        ."area.name AS areas, "
        ."webservice.url AS judge "
        .$query_common_part
        ."LIMIT $offset, $limit";

$result = $mysqli->query($query) or die($mysqli->error);
$proposals = array();
while ($proposal = $result->fetch_assoc()) {
  settype($proposal['published'], 'int');
  settype($proposal['secret'], 'bool');
  settype($proposal['deadline'], 'int');
  settype($proposal['trust'], 'int');
  settype($proposal['participation'], 'int');
  settype($proposal['corpus'], 'int');
  $proposal['areas'] = explode("\n", $proposal['areas']);
  $proposals[] = $proposal;
}
$result->free();

$query = "SELECT "
        ."COUNT(*) AS number_of_proposals "
        .$query_common_part;

$result = $mysqli->query($query) or die($mysqli->error);
$number = $result->fetch_assoc() or die($mysqli->error);
$mysqli->close();
$answer = array();
$answer['proposals'] = $proposals;
$answer['number'] = $number['number_of_proposals'];
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
