<?php
require_once '../../php/database.php';
require_once '../../php/sanitizer.php';

$registration = json_decode(file_get_contents("php://input"));
$referendum = sanitize_field($registration->referendum, "base64", "referendum");
if (!$referendum)
  die("Missing referendum argument");
$citizen = sanitize_field($registration->citizen, "base64", "citizen");
if (!$citizen)
  die("Missing citizen argument");

$query = "SELECT judge FROM proposal "
        ."LEFT JOIN publication ON publication.id=proposal.id AND publication.`key`='$referendum' "
        ."WHERE proposal.secret=1";
$result = $mysqli->query($query) or die($mysqli->error);
$r = $result->fetch_assoc();
$result->free();
if (!$r)
  die("Referendum not found.");
$judge = $r['judge'];

# check if citizen is endorsed by the judge
$query = "SELECT endorsement.`revoke` FROM endorsement "
        ."INNER JOIN publication ON publication.id=endorsement.id "
        ."INNER JOIN webservice ON webservice.`key`=publication.`key` AND webservice.url='$judge' AND webservice.type='judge' "
        ."INNER JOIN publication AS pc ON pc.`key`='$citizen' AND pc.`signature`=endorsement.endorsedSignature "
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
