<?php
require_once '../../php/database.php';

function get_string_parameter($name) {
  if (isset($_GET[$name]))
    return $_GET[$name];
  if (isset($_POST[$name]))
    return $_POST[$name];
  return FALSE;
}
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  die("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');

$referendum = $mysqli->escape_string(get_string_parameter('referendum'));
if (!$referendum)
  die("Missing referendum argument");
$citizen = $mysqli->escape_string(get_string_parameter('citizen'));
if (!$citizen)
  die("Missing citizen argument");

$query = "SELECT judge FROM proposal "
        ."LEFT JOIN publication ON publication.id=proposal.id AND publication.fingerprint='$referendum' "
        ."WHERE proposal.secret=1";
$result = $mysqli->query($query) or die($mysqli->error);
$r = $result->fetch_assoc();
$result->free();
if (!$r)
  die('Referendum not found.');
$judge = $r['judge'];

# check if citizen is endorsed by the judge
$query = "SELECT endorsement.`revoke` FROM endorsement "
        ."INNER JOIN publication ON publication.id=endorsement.id "
        ."INNER JOIN webservice ON webservice.`key`=publication.`key` AND webservice.url='$judge' AND webservice.type='judge' "
        ."INNER JOIN publication AS pc ON pc.fingerprint='$citizen' AND pc.`signature`=endorsement.endorsedSignature "
        ."WHERE endorsement.latest=1";
$result = $mysqli->query($query) or die($mysqli->error);
$endorsement = $result->fetch_assoc();
$result->free();
if (!$endorsement)
  die("Citizen not endorsed by the judge of the referendum");
if ($endorsement['revoke'] !== '0')
  die("Citizen revoked by the judge of the referendum");
# otherwise we are all good
# Note: the geographic location of the citizen with respect to the referendum is already checked by the app
#       the app should have endorsed the citizen during the integrity check (checked by the judge before endorsing the citizen)
#       the judge should have endorsed the app
die("Yes");
?>