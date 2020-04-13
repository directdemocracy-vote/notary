<?php

require_once '../vendor/autoload.php';
require_once '../php/database.php';
require_once '../php/endorsements.php';

use Opis\JsonSchema\{
  IMediaType, MediaTypeContainer, Schema, Validator, ValidationResult, ValidationError
};

class MimeType implements IMediaType {
  public function validate(string $data, string $type): bool {
    if ($type == 'image/jpeg') {
      $header = 'data:image/jpeg;base64,';
      if (substr($data, 0, strlen($header)) != $header)
        return false;
      $data = base64_decode(substr($data, strlen($header)));
      try {
        $image = @imagecreatefromstring($data);
        return $image !== false;
      } catch(Exception $e) {
        return false;
      }
    }
    return false;
  }
}

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

function delete_citizen($mysqli, $key) {
  $query = "SELECT id FROM publication WHERE `key`='$key' AND `schema` LIKE '%citizen.schema.json'";
  $result = $mysqli->query($query) or error($mysqli->error);
  while ($p = $result->fetch_assoc()) {  # there should be only one
    $mysqli->query("DELETE FROM publication WHERE id=$p[id]") or error($mysqli->error);
    $mysqli->query("DELETE FROM citizen WHERE id=$p[id]") or error($mysqli->error);
  }
  $result->free();
  # delete any endorsement of the deleted citizen card
  $query = "SELECT id FROM endorsement WHERE publicationKey='$key'";
  $result = $mysqli->query($query) or error($mysqli->error);
  while ($p = $result->fetch_assoc()) {
    $mysqli->query("DELETE FROM publication WHERE id=$p[id]") or error($mysqli->error);
    $mysqli->query("DELETE FROM endorsement WHERE id=$p[id]") or error($mysqli->error);
  }
  $result->free();
}

function delete_older_endorsements($mysqli, $key, $published, $endorsedKey, $endorsedSignature) {
  $query = "DELETE p, e FROM publication p JOIN endorsement e ON e.id = p.id WHERE p.`key` = '$key' "
          ."AND p.published < $published AND e.publicationKey = '$endorsedKey' "
          ."AND e.publicationSignature = '$endorsedSignature'";
  $mysqli->query($query) or error($mysqli->error);
}

function delete_publication($mysqli, $key, $signature) {
  $query = "SELECT id, `schema` FROM publication WHERE `key`='$key' AND signature='$signature'";
  $result = $mysqli->query($query) or error($mysqli->error);
  $p = $result->fetch_assoc();
  if ($p) {
    $mysqli->query("DELETE FROM publication WHERE id=$p[id]") or error($mysqli->error);
    $type = get_type($p['schema']);
    $mysqli->query("DELETE FROM $type WHERE id=$p[id]") or error($mysqli->error);
  }
  $result->free();
}

function delete_all_publications($mysqli, $key) {
  $query = "SELECT id, `schema` FROM publication WHERE `key`='$key'";
  $result = $mysqli->query($query) or error($mysqli->error);
  while($p = $result->fetch_assoc()) {
    $mysqli->query("DELETE FROM publication WHERE id=$p[id]") or error($mysqli->error);
    $type = get_type($p['schema']);
    $mysqli->query("DELETE FROM $type WHERE id=$p[id]") or error($mysqli->error);
  }
  $result->free();
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
$schema_text = file_get_contents($publication->schema);
$schema = Schema::fromJsonString($schema_text);
$mediaTypes = new MediaTypeContainer();
$mimeType = new MimeType();
$mediaTypes->add("image/jpeg", $mimeType);
$validator = new Validator();
$validator->setMediaType($mediaTypes);
$result = $validator->schemaValidation($publication, $schema);
if (!$result->isValid()) {
  $error = $result->getFirstError();
  $keyword = $error->keyword();
  $keywordArgs = json_encode($error->keywordArgs(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  error("{\"keyword\":\"$keyword\",\"keywordArgs\":$keywordArgs}");
}
$now = floatval(microtime(true) * 1000);  # milliseconds
$type = get_type($publication->schema);
if ($type != 'vote' && $type != 'ballot' && $publication->published > $now + 60000)  # allowing a 1 minute error
  error("Publication date in the future for $type: $publication->published > $now");
if ($publication->expires < $now - 60000)  # allowing a 1 minute error
  error("Expiration date in the past: $publication->expires < $now");
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
} elseif ($type == 'registration' || $type == 'ballot') {
  if (isset($publication->station->signature)) {
    $station_signature = $publication->station->signature;
    $publication->station->signature = '';
    $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (openssl_verify($data, base64_decode($station_signature), public_key($publication->station->key), OPENSSL_ALGO_SHA256)
        == -1)
      error("Wrong station signature for $type");
    unset($publication->station->signature);
  }
}
$signature = $publication->signature;
$publication->signature = '';
$data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$verify = openssl_verify($data, base64_decode($signature), public_key($publication->key), OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("Wrong signature");

# restore original signatures
$publication->signature = $signature;
if (isset($station_signature))
  $publication->station->signature = $station_signature;
if (isset($citizen_signature))
  $publication->citizen->signature = $citizen_signature;

$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
if ($type == 'citizen')  # delete any previous citizen card with same key to replace it
  delete_citizen($mysqli, $citizen->key);
elseif ($type == 'endorsement') {
  $endorsement = &$publication;
  if (!property_exists($endorsement, 'revoke'))
    $endorsement->revoke = false;
  $key = $endorsement->publication->key;
  $signature = $endorsement->publication->signature;
  if ($key == '')
    error("Empty key");
  if ($signature == '')
    error("Empty signature");
  delete_older_endorsements($mysqli, $endorsement->key, $endorsement->published, $key, $signature);
  if ($endorsement->revoke && $endorsement->key == $key) {  # revoking my own stuff
    $query = "SELECT id, `schema` FROM publication WHERE `key`='$key' AND signature='$signature'";
    $result = $mysqli->query($query) or error($mysqli->error);
    $p = $result->fetch_assoc();
    if ($p) {
      $t = get_type($p['schema']);
      if ($t === 'citizen')  # revoking my private key
        delete_all_publications($mysqli, $key);
      else  # revoking only one publication
        delete_publication($mysqli, $key, $signature);
    }
    $result->free();
  }
}
$query = "INSERT INTO publication(`schema`, `key`, signature, fingerprint, published, expires) "
        ."VALUES('$publication->schema', '$publication->key', '$publication->signature', "
        ."SHA1('$publication->signature'), $publication->published, $publication->expires)";
$mysqli->query($query) or error($mysqli->error);
$id = $mysqli->insert_id;


# UPDATE `citizen` SET `home` = ST_PointFromText(CONCAT('POINT(', longitude, ' ', latitude, ')')) WHERE `citizen`.`id` = 95;

if ($type == 'citizen')
  $query = "INSERT INTO citizen(id, familyName, givenNames, picture, home) "
          ."VALUES($id, '$citizen->familyName', '$citizen->givenNames', "
          ."'$citizen->picture', ST_PointFromText('POINT($citizen->latitude, $citizen->longitude)')";
elseif ($type == 'endorsement') {
  if (!isset($endorsement->message))
    $endorsement->message = '';
  if (!isset($endorsement->comment))
    $endorsement->comment = '';
  $query = "SELECT id, `schema`, `key`, signature, expires FROM publication WHERE fingerprint=SHA1('$signature')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsed = $result->fetch_assoc();
  if ($endorsed) {
    if ($endorsed['key'] != $key)
      error("endorsement key mismatch");
    if ($endorsed['signature'] != $signature)
      error("endorsement signature mismatch");
    $endorsed_expires = floatval($endorsed['expires']);
    if ($endorsement->expires > $endorsed_expires)
      error("endorsement expires after publication: $endorsement_expires > $endorsed_expires");
    if ($endorsement->revoke && $endorsement->expires != $endorsed_expires)
      error("revoke endorsement don't expire at the same time as publication");
  }
  $query = "INSERT INTO endorsement(id, publicationKey, publicationSignature, publicationFingerprint, "
          ."`revoke`, message, comment) VALUES($id, '$key', '$signature', SHA1('$signature'), "
          ."'$endorsement->revoke', '$endorsement->message', '$endorsement->comment')";
} elseif ($type == 'referendum') {
  $referendum =&$publication;
  if (!isset($referendum->website))  # optional
    $referendum->website = '';
  $query = "INSERT INTO referendum(id, trustee, area, title, description, question, answers, deadline, website) "
          ."VALUES($id, \"$referendum->trustee\", \"$referendum->area\", \"$referendum->title\", \"$referendum->description\", "
          ."\"$referendum->question\", \"$referendum->answers\", $referendum->deadline, \"$referendum->website\")";
} elseif ($type == 'registration' || $type == 'ballot')
  $query = "INSERT INTO $type(id, referendum, stationKey, stationSignature) "
          ."VALUES($id, \"$publication->referendum\", \"" . $publication->station->key
          ."\", \"" . $publication->station->signature . "\")";
elseif ($type == 'vote')
  $query = "INSERT INTO vote(id, answer) VALUES($id, \"$publication->answer\")";
elseif ($type == 'area') {
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
  $query = "INSERT INTO area(id, name, polygons) VALUES($id, \"$publication->name\", $polygons)";
} else
  error("unknown publication type");
$mysqli->query($query) or error($mysqli->error . "\n\n" . $query);
if ($type == 'endorsement')
  echo json_encode(endorsements($mysqli, $publication->key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
else {
  $fingerprint = sha1($publication->signature);
  echo("{\"fingerprint\":\"$fingerprint\"}");
}
$mysqli->close();
?>
