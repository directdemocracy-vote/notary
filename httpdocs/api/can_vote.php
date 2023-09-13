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
  die("Missing referendum argument.");
$citizen_key = $mysqli->escape_string(get_string_parameter('citizen'));
if (!$citizen_key)
  die("Missing citizen argument.");

$query = "SELECT judge FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`=\"$referendum\"";
$result = $mysqli->query($query) or die($mysqli->error);
$r = $result->fetch_assoc();
$result->free();
if (!$r)
  die('Referendum not found.');
$judge = $r['judge'];

# check if citizen is endorsed by an app
$query = "SELECT publication.`key` FROM publication "
        ."INNER JOIN endorsement ON endorsement.id=publication.id "
        ."INNER JOIN webservice ON webservice.type='app' AND webservice.`key`=publication.`key` "
        ."INNER JOIN publication AS pc ON pc.`key`='$citizen_key' AND pc.`signature`=endorsement.endorsedSignature";
$result = $mysqli->query($query) or die($mysqli->error);
$endorsement = $result->fetch_assoc();
$result->free();
if (!$endorsement)
  die("Citizen not endorsed by any app");
# check if this app is endorsed by the judge

FIXME...

die("yes");
?>
