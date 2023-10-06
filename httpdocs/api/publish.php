<?php

require_once '../../vendor/autoload.php';
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/corpus.php';
require_once '../../php/sanitizer.php';

use Opis\JsonSchema\{
  Validator, Errors\ErrorFormatter
};

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

function public_key($key) {
  $public_key = "-----BEGIN PUBLIC KEY-----\n";
  $l = strlen($key);
  for($i = 0; $i < $l; $i += 64)
    $public_key .= substr($key, $i, 64) . "\n";
  $public_key.= "-----END PUBLIC KEY-----";
  return $public_key;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$publication = json_decode(file_get_contents("php://input"));
if (!$publication)
  error("Unable to parse JSON post");
if (!isset($publication->schema))
  error("Unable to read schema field");
$schema = sanitize_field($publication->schema, 'string', 'schema');
$key = sanitize_field($publication->key, "base_64", "key");
$published = sanitize_field($publication->published, 'positive_int', 'published');
$signature = sanitize_field($publication->signature, "base_64", "signature");
if (isset($publication->blindKey))
  $blindKey = sanitize_field($publication->blindKey, "base_64", "signature");
if (isset($publication->encryptedVote))
$encryptedVote = sanitize_field($publication->encryptedVote, "base_64", "signature");

$validator = new Validator();
$result = $validator->validate($publication, file_get_contents($schema));
if (!$result->isValid()) {
  $error = $result->error();
  $formatter = new ErrorFormatter();
  error(implode(". ", $formatter->formatFlat($error)) . ".");
}
$now = time();  # UNIX time stamp (seconds)
$type = get_type($schema);
if ($type != 'ballot' && $published > $now + 60)  # allowing a 1 minute (60 seconds) error
  error("Publication date in the future for $type: $published > $now");
if ($type == 'citizen') {
  $citizen = &$publication;
  $citizen_picture = substr($citizen->picture, strlen('data:image/jpeg;base64,'));
  $data = base64_decode($citizen_picture);
  try {
    $size = @getimagesizefromstring($data);
    if ($size['mime'] != 'image/jpeg')
      error("Wrong picture MIME type: '$size[mime]' (expecting 'image/jpeg')");
    if ($size[0] != 150 || $size[1] != 200)
      error("Wrong picture size: $size[0]x$size[1] (expecting 150x200)");
  } catch(Exception $e) {
    error("Cannot determine picture size");
  }
}
if ($type != 'ballot') {
  $publication->signature = '';
  $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $verify = openssl_verify($data, base64_decode($signature), public_key($key), OPENSSL_ALGO_SHA256);
  if ($verify != 1)
    error("Wrong signature for $type");
  # restore original signatures if needed
  $publication->signature = $signature;
  if (isset($station_signature))
    $publication->station->signature = $station_signature;
  if (isset($citizen_signature))
    $publication->citizen->signature = $citizen_signature;
}

$version = intval(explode('/', $schema)[4]);
$query = "INSERT INTO publication(`version`, `type`, `key`, signature, published) "
        ."VALUES($version, '$type', FROM_BASE64('$key'), FROM_BASE64('$signature'), FROM_UNIXTIME($published))";
$mysqli->query($query) or error($mysqli->error);
$id = $mysqli->insert_id;

if ($type == 'citizen')
  $query = "INSERT INTO citizen(id, familyName, givenNames, picture, home) "
          ."VALUES($id, \"$citizen->familyName\", \"$citizen->givenNames\", "
          ."FROM_BASE64(\"$citizen_picture\"), POINT($citizen->longitude, $citizen->latitude))";
elseif ($type == 'endorsement') {
  $endorsement = &$publication;
  if (!property_exists($endorsement, 'revoke'))
    $endorsement->revoke = false;
  if (!property_exists($endorsement, 'message'))
    $endorsement->message = '';
  if (!property_exists($endorsement, 'comment'))
    $endorsement->comment = '';
  $query = "SELECT id, `type`, REPLACE(TO_BASE64(signature), '\\n', '') AS signature FROM publication WHERE signature = FROM_BASE64('$endorsement->endorsedSignature')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsed = $result->fetch_assoc();
  $result->free();
  if (!$endorsed)
    error("Endorsed signature not found: $endorsement->endorsedSignature");
  if ($endorsed['signature'] != $endorsement->endorsedSignature)
    error("Endorsed signature mismatch.");
  # mark other endorsements of the same participant by the same endorser as not the latest
  $mysqli->query("UPDATE endorsement INNER JOIN publication ON publication.id = endorsement.id"
                ." SET endorsement.latest = 0"
                ." WHERE endorsement.endorsedSignature = FROM_BASE64('$endorsement->endorsedSignature')"
                ." AND publication.`key` = FROM_BASE64('$key')") or error($mysli->error);
  if ($endorsed['type'] == 'proposal') {  # signing a petition
    # increment the number of participants in a petition if the citizen is located inside the petition area and is endorsed by the petition judge
    $endorsed_id = $endorsed['id'];
    $query = "UPDATE proposal "
            ."INNER JOIN publication AS pc ON pc.`key`=FROM_BASE64('$key') "
            ."INNER JOIN citizen ON citizen.id=pc.id "
            ."INNER JOIN publication AS pa ON pa.`signature`=proposal.area "
            ."INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home))) "
            ."INNER JOIN webservice AS judge ON judge.`type`='judge' AND judge.url=proposal.judge "
            ."INNER JOIN publication AS pe ON pe.`key`=judge.`key` "
            ."INNER JOIN endorsement ON endorsement.id = pe.id AND endorsement.`revoke`=0 AND endorsement.latest=1 AND endorsement.endorsedSignature=pc.signature "
            ."SET participants=participants+1 "
            ."WHERE proposal.id=$endorsed_id AND proposal.`secret`=0";
    $mysqli->query($query) or error($msqli->error);
    $accepted = $mysqli->affected_rows;
  } else
    $accepted = 0;
  $revoke = $endorsement->revoke ? 1 : 0;
  $query = "INSERT INTO endorsement(id, `revoke`, `message`, comment, endorsedSignature, latest, accepted) "
          ."VALUES($id, $revoke, \"$endorsement->message\", \"$endorsement->comment\", FROM_BASE64('$endorsement->endorsedSignature'), 1, $accepted)";
} elseif ($type == 'proposal') {
  $proposal =&$publication;
  if (!isset($proposal->website))  # optional
    $proposal->website = '';
  if (!isset($proposal->question))  # optional
    $proposal->question = '';
  if (!isset($proposal->answers))  # optional
    $proposal->answers = array();
  $answers = implode("\n", $proposal->answers);
  $secret = ($proposal->secret) ? 1 : 0;
  $query = "INSERT INTO proposal(id, judge, area, title, description, question, answers, secret, deadline, website, participants, corpus) "
          ."VALUES($id, \"$proposal->judge\", FROM_BASE64('$proposal->area'), \"$proposal->title\", \"$proposal->description\", "
          ."\"$proposal->question\", \"$answers\", $secret, $proposal->deadline, \"$proposal->website\", 0, 0)";
} elseif ($type == 'registration')
  $query = "INSERT INTO registration(id, blindKey, encryptedVote) "
          ."VALUES($id, FROM_BASE64('$blindKey'), FROM_BASE64('$encryptedVote'))";
elseif ($type == 'ballot') {
  if (!isset($publication->answer)) # optional
    $answer = '';
  else
    $answer = sanitize_field($publication->answer, 'string', 'answer');

  if (isset($publication->station)) {
    $station_key = sanitize_field($publication->station->key, "base_64", "station_key");
    $station_signature = sanitize_field($publication->station->signature, "base_64", "station_signature");
    $station_names = " stationKey, stationSignature,";
    $station_values = " FROM_BASE64('$station_key'), FROM_BASE64('$station_signature'),";
  } else {
    $station_names = "";
    $station_values = "";
  }
  $publication_proposal = sanitize_field($publication->proposal, "base_64", "station_signature");
  $query = "INSERT INTO ballot(id, proposal,$station_names answer) "
          ."VALUES($id, FROM_BASE64('$publication_proposal'),$station_values \"$answer\")";
} elseif ($type == 'area') {
  $polygons = 'ST_GeomFromText("MULTIPOLYGON(';
  $t1 = false;
  foreach($publication->polygons as $polygon1) {
    if ($t1)
      $polygons .= ', ';
    $polygons .= '(';
    $t1 = true;
    $t2 = false;
    foreach($polygon1 as $polygon2) {
      if ($t2)
        $polygons .= ', ';
      $polygons .= '(';
      $t2 = true;
      $t3 = false;
      foreach($polygon2 as $coordinates) {
        if ($t3)
          $polygons .= ', ';
        $t3 = true;
        $polygons .= $coordinates[0] . ' ' . $coordinates[1];
      }
      $polygons .= ')';
    }
    $polygons .= ')';
  }
  $polygons .= ')")';
  $polygons = sanitize_field($publication->polygons, 'string', 'polygons');
  $name = sanitize_field($publication->name, 'string', 'name');
  $name = implode("\n", $publication->name);
  $query = "INSERT INTO area(id, name, polygons) VALUES($id, \"$name\", $polygons)";
} else
  error("Unknown publication type.");
$mysqli->query($query) or error($mysqli->error);
if ($type == 'proposal')
  update_corpus($mysqli, $id);
if ($type == 'endorsement')
  echo json_encode(endorsements($mysqli, $key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
else
  echo("{\"signature\":\"$signature\"}");
$mysqli->close();
?>
