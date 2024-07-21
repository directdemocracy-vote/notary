<?php

require_once '../../vendor/autoload.php';
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/corpus.php';
require_once '../../php/sanitizer.php';
require_once '../../php/public_key.php';
require_once '../../php/blind-sign.php';

use Opis\JsonSchema\{
  Validator, Errors\ErrorFormatter
};

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

function check_app($publication, $vote=false) {
  global $mysqli;

  $appKey = sanitize_field($publication->appKey, 'base64', 'appKey');
  $result = $mysqli->query("SELECT id FROM participant WHERE `type`='app' and `key`=FROM_BASE64('$appKey==')");
  $participant = $result->fetch_assoc();
  if (!$participant)
    error("unknown app");
  $app = intval($participant['id']);
  $result->free();
  $app_signature = sanitize_field($publication->appSignature, 'base64', 'appSignature');
  $publication->appSignature = '';
  if ($vote) {
    if ($app !== 2) { # FIXME: the signature of the test app is currently broken
      $voteBytes = base64_decode("$publication->referendum==");
      $voteBytes .= pack('J', $publication->number);
      $voteBytes .= pack('J', $publication->area);
      $voteBytes .= base64_decode("$publication->ballot");
      $voteBytes .= $publication->answer;
      $publicKey = openssl_pkey_get_public(public_key($appKey));
      $details = openssl_pkey_get_details($publicKey);
      $n = gmp_import($details['rsa']['n'], 1, GMP_BIG_ENDIAN | GMP_MSW_FIRST);
      $e = gmp_import($details['rsa']['e'], 1, GMP_BIG_ENDIAN | GMP_MSW_FIRST);
      $error = blind_verify($n, $e, $voteBytes, base64_decode("$app_signature=="));
      if ($error !== '')
        error("failed to verify app signature: $error");
    }
  } else {
    $verify = openssl_verify($publication->signature, base64_decode("$app_signature=="), public_key($appKey), OPENSSL_ALGO_SHA256);
    if ($verify != 1) {
      $type = get_type(sanitize_field($publication->schema, 'url', 'schema'));
      error("wrong app signature for $type: $appKey");
    }
  }
  # restore original signature
  $publication->appSignature = $app_signature;
  return array($app, $app_signature);
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$publication = json_decode(file_get_contents("php://input"));
if (!$publication)
  error("unable to parse JSON post");
if (!isset($publication->schema))
  error("unable to read schema field");
$schema = sanitize_field($publication->schema, 'url', 'schema');
$key = sanitize_field($publication->key, 'base64', 'key');
$published = sanitize_field($publication->published, 'positive_int', 'published');
$signature = sanitize_field($publication->signature, 'base64', 'signature');
if (isset($publication->blindKey))
  $blindKey = sanitize_field($publication->blindKey, 'base64', 'signature');

# validate from json-schema
$schema_file = file_get_contents($schema);
$validator = new Validator();
$result = $validator->validate($publication, $schema_file);
if (!$result->isValid()) {
  $error = $result->error();
  $formatter = new ErrorFormatter();
  error(implode('. ', $formatter->formatFlat($error)) . '.');
}

# check field order (important for signature)
$schema_json = json_decode($schema_file, true);
$properties = array_keys((array)$schema_json['properties']);
$keys = array_keys((array)$publication);
$property_counter = 0;
$property_count = count($properties);
$count = count($keys);
$break = false;
for($i = 0; $i < $count; $i++) {
  while ($properties[$property_counter++] !== $keys[$i]) {
    if ($property_counter >= $property_count) {
      $break = true;
      break;
    }
  }
  if ($break)
    break;
}
if ($break && $i < $count)
  error("wrong property order for '$keys[$i]' property");
 
$now = time();  # UNIX time stamp (seconds)
$type = get_type($schema);
if ($type !== 'vote' && $published > $now + 60)  # allowing a 1 minute (60 seconds) error
  error("publication date in the future for $type: $published > $now");
if ($type === 'citizen') {
  $citizen = &$publication;
  $citizen_picture = substr($citizen->picture, strlen('data:image/jpeg;base64,'));
  $data = base64_decode($citizen_picture);
  try {
    $size = @getimagesizefromstring($data);
    if ($size['mime'] != 'image/jpeg')
      error("wrong picture MIME type: '$size[mime]' (expecting 'image/jpeg')");
    if ($size[0] != 150 || $size[1] != 200)
      error("wrong picture size: $size[0]x$size[1] (expecting 150x200)");
  } catch(Exception $e) {
    error("cannot determine picture size");
  }
}

$publication->signature = '';
if ($type !== 'vote' && isset($publication->appSignature)) {
  $app_signature = $publication->appSignature;
   $publication->appSignature = '';
}
$data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$verify = openssl_verify($data, base64_decode("$signature=="), public_key($key), OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("wrong signature for $type: key=$key\n" . json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
# restore original signatures if needed
$publication->signature = $signature;
if ($type !== 'vote' && isset($app_signature))
  $publication->appSignature = $app_signature;

$result = $mysqli->query("SELECT id, `type` FROM participant WHERE `key`=FROM_BASE64('$key==')") or error($mysqli->error);
$participant = $result->fetch_assoc();
if ($participant)
  $participant_id = intval($participant['id']);
else {
  if ($type !== 'citizen')
    error("unknown $type publisher"); 
  $mysqli->query("INSERT INTO participant(`type`, `key`) VALUES('citizen', FROM_BASE64('$key=='))") or error($mysqli->error);
  $participant_id = $mysqli->insert_id;
}
$version = intval(explode('/', $schema)[4]);
$query = "INSERT IGNORE INTO publication(`version`, `type`, participant, signature, published) "
        ."VALUES($version, '$type', $participant_id, FROM_BASE64('$signature=='), FROM_UNIXTIME($published))";
$mysqli->query($query) or error($mysqli->error);
if ($mysqli->affected_rows === 0)
  error("already existing publication");
$id = $mysqli->insert_id;

if ($type === 'citizen') {
  list($app, $app_signature) = check_app($citizen);
  $family_name = $mysqli->escape_string($publication->familyName);
  $given_names = $mysqli->escape_string($publication->givenNames);
  $locality = sanitize_field($citizen->locality, 'positive_int', 'locality');
  $query = "INSERT INTO citizen(publication, app, appSignature, familyName, givenNames, locality, picture) "
          ."VALUES($id, $app, FROM_BASE64('$app_signature=='), \"$family_name\", \"$given_names\", $locality, "
          ."FROM_BASE64('$citizen_picture'))";
} elseif ($type === 'certificate') {
  $certificate = &$publication;
  if (isset($certificate->appKey))
    list($app, $app_signature) = check_app($certificate);
  else {
    $app = '';
    $app_signature = '';
  }
  if (!property_exists($certificate, 'comment'))
    $certificate->comment = '';
  if (!property_exists($certificate, 'message'))
    $certificate->message = '';
  $p = sanitize_field($certificate->publication, 'base64', 'publication');
  $query = "SELECT id, `type`, REPLACE(REPLACE(TO_BASE64(signature), '\\n', ''), '=', '') AS signature FROM publication WHERE signature = FROM_BASE64('$p==')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $committed = $result->fetch_assoc();
  $result->free();
  if (!$committed)
    error("certified publication not found.");
  if ($committed['signature'] != $p)
    error("certified publication signature mismatch.");
  $publication_id = intval($committed['id']);
  if ($committed['type'] == 'proposal') {  # signing a petition
    $r = $mysqli->query("SELECT UNIX_TIMESTAMP(deadline) AS deadline FROM proposal WHERE publication=$publication_id") or die($mysqli->error);
    $p = $r->fetch_assoc();
    if (!$p)
      error("signed petition not found");
    $deadline = intval($p['deadline']);
    if ($certificate->published > $deadline)
      error("cannot sign petition after deadline passed");
    # increment the number of participants in a petition
    $committed_id = $committed['id'];
    $query = "UPDATE proposal SET participants=participants+1 WHERE proposal.publication=$committed_id AND proposal.type='petition'";
    $mysqli->query($query) or error($msqli->error);
    if ($mysqli->affected_rows === 0)
      die("failed to update participation in proposal $committed_id");
  }
  # mark other certificates on the same publication by the same participant as not the latest
  $mysqli->query("UPDATE certificate INNER JOIN publication ON publication.id = certificate.publication"
                ." SET certificate.latest = 0"
                ." WHERE certificate.certifiedPublication = $publication_id"
                ." AND publication.participant = $participant_id") or error($mysli->error);
  $ctype = $mysqli->escape_string($certificate->type);
  $message = $mysqli->escape_string($certificate->message);
  $comment = $mysqli->escape_string($certificate->comment);
  if ($app) {
    $app_fields = " app, appSignature,";
    $app_values = " $app, FROM_BASE64('$app_signature=='),";
  } else {
    $app_fields = '';
    $app_values = '';
  }
  # update citizen status if needed
  if ($ctype === 'report' && ($comment === 'deleted' || $comment === 'transferred' || $comment === 'updated'))
    $mysqli->query("UPDATE citizen SET status='$comment' WHERE publication=$publication_id") or error($mysqli->error);
  $query = "INSERT INTO certificate(publication,$app_fields `type`, `message`, comment, certifiedPublication, latest) "
          ."VALUES($id,$app_values \"$ctype\", \"$message\", \"$comment\", $publication_id, 1)";
} elseif ($type === 'proposal') {
  $proposal =&$publication;
  if (!isset($proposal->website))  # optional
    $website = '';
  else
    $website = sanitize_field($publication->website, 'url', 'website');
  if (!isset($proposal->question))  # optional
    $question = '';
  else
    $question = $mysqli->escape_string($publication->question);
  if (!isset($proposal->answers))  # optional
    $answers = array();
  else
    $answers = $publication->answers;
  $answers = implode("\n", $answers);
  $answers = $mysqli->escape_string($answers);
  $t = $mysqli->escape_string($proposal->type);
  $secret = ($proposal->secret) ? 1 : 0;
  $area = intval($publication->area);
  $title = $mysqli->escape_string($publication->title);
  $description = $mysqli->escape_string($publication->description);
  $deadline = sanitize_field($publication->deadline, 'positive_int', 'deadline');
  $trust = sanitize_field($publication->trust, 'positive_int', 'trust');
  $query = "SELECT area.id FROM area INNER JOIN publication ON publication.id=area.publication "
          ."INNER JOIN participant ON participant.id=publication.participant "
          ."WHERE area.id=$area AND participant.`key`=FROM_BASE64('$proposal->key==')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $area_publication = $result->fetch_assoc();
  $result->free();  if (!$area_publication)
    error("could not find area");
  $query = "INSERT INTO proposal(publication, area, title, description, question, answers, type, secret, deadline, trust, website, participants, corpus) "
          ."VALUES($id, $area, \"$title\", \"$description\", \"$question\", \"$answers\", \"$t\", $secret, FROM_UNIXTIME($deadline), $trust, \"$website\", 0, 0)";
} elseif ($type === 'participation') {
  $participation = &$publication;
  list($app, $app_signature) = check_app($participation);
  $referendum = sanitize_field($participation->referendum, 'base64', 'referendum');
  $area = sanitize_field($participation->area, 'positive_int', 'area');
  $result = $mysqli->query("SELECT id FROM publication WHERE type='proposal' AND signature=FROM_BASE64('$referendum==')") or error($mysqli->error);
  $proposal = $result->fetch_assoc();
  if (!$proposal)
    error('proposal for participation not found');
  $referendum_id = intval($proposal['id']);
  $query = "INSERT INTO participation(publication, app, appSignature, referendum, area) "
          ."VALUES($id, $app, FROM_BASE64('$app_signature=='), $referendum_id, $area)";
} elseif ($type === 'vote') {
  $vote = &$publication;
  list($app, $app_signature) = check_app($vote, true);
  $referendum = sanitize_field($vote->referendum, 'base64', 'referendum');
  $number = sanitize_field($vote->number, 'positive_int', 'number');
  $area = sanitize_field($vote->area, 'positive_int', 'area');
  $ballot = sanitize_field($vote->ballot, 'base64', 'ballot');
  $answer = $mysqli->escape_string($vote->answer);
  $result = $mysqli->query("SELECT id FROM publication WHERE `signature`=FROM_BASE64('$referendum==') AND `type`='proposal'") or error($mysqli->error);
  $referendum_publication = $result->fetch_assoc();
  $result->free();
  if (!$referendum_publication)
    error("referendum not found");
  $referendum_id = $referendum_publication['id'];
  $query = "INSERT INTO vote(publication, app, appSignature, referendum, number, area, ballot, answer) VALUES($id, "
          ."$app, "
          ."FROM_BASE64('$app_signature=='), "
          ."$referendum_id, "
          ."$number, "
          ."$area, "
          ."FROM_BASE64('$ballot'), "
          ."\"$answer\") "
          ."ON DUPLICATE KEY UPDATE appSignature=FROM_BASE64('$app_signature=='), number=$number, answer=\"$answer\";";
} elseif ($type === 'area') {
  $area_id = intval($publication->id);
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
  $name = $mysqli->escape_string($name);
  $local = $publication->local ? 1 : 0;
  $query = "INSERT INTO area(publication, id, name, polygons, local) VALUES($id, $area_id, \"$name\", $polygons, $local)";
} else
  error("unknown publication type.");
$mysqli->query($query) or error($mysqli->error);
$headers = getallheaders();
if (isset($headers['locality']) && isset($headers['locality-name']) && isset($headers['latitude']) && isset($headers['longitude'])) {
  $locality = intval($headers['locality']);
  $localityName = $mysqli->escape_string($headers['locality-name']);
  $latitude = floatval($headers['latitude']);
  $longitude = floatval($headers['longitude']);
  $query = "INSERT INTO locality(osm_id, location, name) "
          ."VALUES($locality, ST_PointFromText('POINT($longitude $latitude)'), \"$localityName\") "
          ."ON DUPLICATE KEY UPDATE location=ST_PointFromText('POINT($longitude $latitude)'), name=\"$localityName\"";
  $mysqli->query($query) or die($mysqli->error);
}
if ($type === 'proposal')
  update_corpus($mysqli, $id);
elseif ($type === 'vote') {
  $query = "INSERT INTO results(referendum, answer, `count`) VALUES($referendum_id, \"$answer\", 1) ON DUPLICATE KEY UPDATE `count`=`count`+1";
  $mysqli->query($query) or error($mysqli->error);
} elseif ($type === 'participation') {
  $query = "UPDATE proposal SET participants=participants+1 WHERE publication=$referendum_id";
  $mysqli->query($query) or error($mysqli->error);  
} elseif ($type === 'certificate' &&  $ctype === 'report' && $comment === 'transferred') {
  $fingerprint = sha1(base64_decode("$p=="));
  $filename = "../../transfers/$fingerprint";
  if (file_exists($filename))
    unlink($filename);
}
echo("{\"signature\":\"$signature\"}");
$mysqli->close();
?>
