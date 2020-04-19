<?php
require_once '../php/database.php';

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

$query = "SELECT trustee, area FROM referendum LEFT JOIN publication ON publication.id=referendum.id "
        ."WHERE publication.`key`='$referendum'";
$result = $mysqli->query($query) or die($mysqli->error);
$r = $result->fetch_assoc();
$result->free();
if (!$r)
  die('Referendum not found');
$trustee = $r['trustee'];
$area = $r['area'];

# check if citizen is endorsed by trustee
$query = "SELECT `revoke` FROM endorsement LEFT JOIN publication ON publication.id=endorsement.id "
        ."WHERE publication.`key`='$referendum' AND endorsement.publicationKey='$citizen' "
        ."ORDER BY publication.published DESC LIMIT 1";
$result = $mysqli->query($query) or die($mysqli->error);
$endorsement = $result->fetch_assoc();
$result->free();
if (!$endorsement)
  die('Citizen not endorsed by trustee' . ": " . $query);
if ($endorsement['revoke'] == 1)
  die('Citizen revokey by trustee');

# check if citizen's home is inside the referendum area
$query = "SELECT ST_Y(home) AS latitude, ST_X(home) AS longitude FROM citizen "
        ."LEFT JOIN publication ON publication.id=citizem.id WHERE publication.`key`='$citizen'";
$result = $mysqli->query($query) or die($mysqli->error);
$citizen = $result->fetch_assoc();
$result->free();
if (!$citizen)
  die('Citizen not found');
$latitude = $citizen['latitude'];
$longitude = $citizen['longitude'];

$query = "SELECT id FROM area LEFT JOIN publication ON publication.id=area.id "
        ." WHERE publication.`key`='$trustee' AND area.name='$area'";
$result = $mysqli->query($query) or die($mysqli->error);
$a = $result->fetch_assoc();
$result->free();
if (!$a)
  die('Area not found');
$area = $a['id'];

$query = "SELECT id FROM area WHERE area.id=$area AND ST_Contains(polygons, POINT($longitude, $latitude))";
$result = $mysqli->query($query) or die($mysqli->error);
$a = $result->fetch_assoc();
$result->free();
if (!$a)
  die("Citizen's home not in referendum area");

die("yes");
?>
