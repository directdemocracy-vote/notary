<?php

require_once '../../vendor/autoload.php';
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/corpus.php';

use Opis\JsonSchema\{
  Validator, Errors\ErrorFormatter
};

function error($message) {
  die("{\"error\":$message}");
}

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
$validator = new Validator();
$result = $validator->validate($publication, file_get_contents($publication->schema));
if (!$result->isValid()) {
  $error = $result->error();
  $formatter = new ErrorFormatter();
  error(implode(". ", $formatter->formatFlat($error)) . ".");
}
$now = intval(microtime(true) * 1000);  # milliseconds
$type = get_type($publication->schema);
if ($type != 'ballot' && $publication->published > $now + 60000)  # allowing a 1 minute error
  error("Publication date in the future for $type: $publication->published > $now");
if ($type == 'citizen') {
  $citizen = &$publication;
  $data = base64_decode(substr($citizen->picture, strlen('data:image/jpeg;base64,')));
  try {
    $size = @getimagesizefromstring($data);
    if ($size['mime'] != 'image/jpeg')
      error("Wrong picture MIME type: '$size[mime]' (expecting 'image/jpeg')");
    if ($size[0] != 150 || $size[1] != 200)
      error("Wrong picture size: $size[0]x$size[1] (expecting 150x200)");
  } catch(Exception $e) {
    error("Cannot determine picture size");
  }
} elseif ($type == 'registration') {
  if (isset($publication->station->signature)) {
    $station_signature = $publication->station->signature;
    $publication->station->signature = '';
    $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (openssl_verify($data, base64_decode($station_signature), public_key($publication->station->key), OPENSSL_ALGO_SHA256) == -1)
      error("Wrong station signature for registration");
    unset($publication->station->signature);
  }
} elseif ($type == 'ballot') {
  $signature = $publication->signature;
  if ($signature !== '') {
    $publication->signature = '';
    $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (openssl_verify($data, base64_decode($signature), public_key($publication->key), OPENSSL_ALGO_SHA256) == -1)
      error("Wrong signature for ballot");
  }
  if (isset($publication->station)) {
    if (!isset($publication->station->signature))
      error("Missing station signature for ballot");
    $station_signature = $publication->station->signature;
    $publication->station->signature = '';
    $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (openssl_verify($data, base64_decode($station_signature), public_key($publication->station->key), OPENSSL_ALGO_SHA256) == -1)
      error("Wrong station signature for ballot");
    $publication->station->signature = $station_signature;
  }
  $publication->signature = $signature;
}
if ($type != 'ballot') {
  $signature = $publication->signature;
  $publication->signature = '';
  $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $verify = openssl_verify($data, base64_decode($signature), public_key($publication->key), OPENSSL_ALGO_SHA256);
  if ($verify != 1)
    error("Wrong signature for $type");
  # restore original signatures if needed
  $publication->signature = $signature;
  if (isset($station_signature))
    $publication->station->signature = $station_signature;
  if (isset($citizen_signature))
    $publication->citizen->signature = $citizen_signature;
}

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
$query = "INSERT INTO publication(`schema`, `key`, `signature`, fingerprint, published) "
        ."VALUES(\"$publication->schema\", \"$publication->key\", \"$publication->signature\", "
        ."SHA1(\"$publication->signature\"), $publication->published)";
$mysqli->query($query) or error($mysqli->error);
$id = $mysqli->insert_id;

if ($type == 'citizen')
  $query = "INSERT INTO citizen(id, familyName, givenNames, picture, home) "
          ."VALUES($id, \"$citizen->familyName\", \"$citizen->givenNames\", "
          ."\"$citizen->picture\", POINT($citizen->longitude, $citizen->latitude))";
elseif ($type == 'endorsement') {
  $endorsement = &$publication;
  if (!property_exists($endorsement, 'revoke'))
    $endorsement->revoke = false;
  if (!property_exists($endorsement, 'message'))
    $endorsement->message = '';
  if (!property_exists($endorsement, 'comment'))
    $endorsement->comment = '';
  $query = "SELECT id, `schema`, `signature` FROM publication WHERE fingerprint=SHA1(\"$endorsement->endorsedSignature\")";
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
                ." WHERE endorsement.endorsedFingerprint=SHA1('$endorsement->endorsedSignature')"
                ." AND publication.`key`='$publication->key'") or error($mysli->error);
  if (str_ends_with($endorsed['schema'], '/proposal.schema.json')) {  # signing a petition
    # increment the number of participants in a petition if the citizen is located inside the petition area and is endorsed by the petition judge
    $endorsed_id = $endorsed['id'];
    $key = $endorsement->key;
    $query = "UPDATE proposal "
            ."INNER JOIN publication AS pc ON pc.`key`='$key' "
            ."INNER JOIN citizen ON citizen.id=pc.id "
            ."INNER JOIN publication AS pa ON pa.`signature`=proposal.area "
            ."INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home))) "
            ."INNER JOIN judge ON judge.url=proposal.judge "
            ."INNER JOIN publication AS pe ON pe.`key`=judge.`key` "
            ."INNER JOIN endorsement ON endorsement.id = pe.id AND endorsement.`revoke`=0 AND endorsement.latest=1 AND endorsement.endorsedFingerprint=pc.fingerprint "
            ."SET participants=participants+1 "
            ."WHERE proposal.id=$endorsed_id AND proposal.`secret`=0";
    $mysqli->query($query) or error($msqli->error);
    $accepted = $mysqli->affected_rows;
  } else
    $accepted = 0;
  $revoke = $endorsement->revoke ? 1 : 0;
  $query = "INSERT INTO endorsement(id, endorsedFingerprint, `revoke`, `message`, comment, endorsedSignature, latest, accepted) "
          ."VALUES($id, SHA1(\"$endorsement->endorsedSignature\"), $revoke, \"$endorsement->message\", \"$endorsement->comment\", "
          ."\"$endorsement->endorsedSignature\", 1, $accepted)";
} elseif ($type == 'proposal') {
  $proposal =&$publication;
  if (!isset($proposal->website))  # optional
    $proposal->website = '';
  if (!isset($proposal->question))  # optional
    $proposal->question = '';
  if (!isset($proposal->answers))  # optional
    $proposal->answers = array();
  $answers = implode("\n", $proposal->answers);
  $query = "INSERT INTO proposal(id, judge, area, title, description, question, answers, deadline, website, participants, corpus) "
          ."VALUES($id, \"$proposal->judge\", \"$proposal->area\", \"$proposal->title\", \"$proposal->description\", "
          ."\"$proposal->question\", \"$answers\", $proposal->deadline, \"$proposal->website\", 0, 0)";
} elseif ($type == 'registration')
  $query = "INSERT INTO registration(id, proposal, stationKey, stationSignature) "
          ."VALUES($id, \"$publication->proposal\", \"" . $publication->station->key
          ."\", \"" . $publication->station->signature . "\")";
elseif ($type == 'ballot') {
  if (!isset($publication->answer)) # optional
    $publication->answer = '';
  if (isset($publication->station)) {
    $station_names = " stationKey, stationSignature,";
    $station_values = ' "'.$publication->station->key.'", "'.$publication->station->signature.'",';
  } else {
    $station_names = "";
    $station_values = "";
  }
  $query = "INSERT INTO ballot(id, proposal,$station_names answer) "
          ."VALUES($id, \"$publication->proposal\",$station_values \"$publication->answer\")";
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
  $name = implode("\n", $publication->name);
  $query = "INSERT INTO area(id, name, polygons) VALUES($id, \"$name\", $polygons)";
} else
  error("Unknown publication type.");
$mysqli->query($query) or error($mysqli->error);
if ($type == 'proposal')
  update_corpus($mysqli, $id);
if ($type == 'endorsement')
  echo json_encode(endorsements($mysqli, $publication->key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
else {
  $fingerprint = sha1($publication->signature);
  echo("{\"fingerprint\":\"$fingerprint\"}");
}
$mysqli->close();
?>
