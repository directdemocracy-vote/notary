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
$now = intval(microtime(true) * 1000);  # milliseconds
$type = get_type($publication->schema);
if ($type != 'ballot' && $publication->published > $now + 60000)  # allowing a 1 minute error
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
} elseif ($type == 'registration') {
  if (isset($publication->station->signature)) {
    $station_signature = $publication->station->signature;
    $publication->station->signature = '';
    $data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (openssl_verify($data, base64_decode($station_signature), public_key($publication->station->key), OPENSSL_ALGO_SHA256)
        == -1)
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
    if (openssl_verify($data, base64_decode($station_signature), public_key($publication->station->key), OPENSSL_ALGO_SHA256)
      == -1)
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
if ($type == 'endorsement') {
  $endorsement = &$publication;
  if (!property_exists($endorsement, 'revoke'))
    $endorsement->revoked = $endorsement->expires;
  else
    $endorsement->revoked = $endorsement->revoke ? $endorsement->published : $endorsement->expires;
  $key = $endorsement->publication->key;
  $signature = $endorsement->publication->signature;
  if ($key == '')
    error("Empty key");
  if ($signature == '')
    error("Empty signature");
}
$query = "INSERT INTO publication(`schema`, `key`, signature, fingerprint, published, expires) "
        ."VALUES(\"$publication->schema\", \"$publication->key\", \"$publication->signature\", "
        ."SHA1(\"$publication->signature\"), $publication->published, $publication->expires)";
$mysqli->query($query) or error($mysqli->error);
$id = $mysqli->insert_id;

if ($type == 'citizen')
  $query = "INSERT INTO citizen(id, familyName, givenNames, picture, home) "
          ."VALUES($id, \"$citizen->familyName\", \"$citizen->givenNames\", "
          ."\"$citizen->picture\", POINT($citizen->longitude, $citizen->latitude))";
elseif ($type == 'endorsement') {
  if (!isset($endorsement->message))
    $endorsement->message = '';
  if (!isset($endorsement->comment))
    $endorsement->comment = '';
  $query = "SELECT id, `schema`, `key`, signature, expires FROM publication WHERE fingerprint=SHA1(\"$signature\")";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsed = $result->fetch_assoc();
  $result->free();
  if ($endorsed) {
    if ($endorsed['key'] != $key)
      error("endorsement key mismatch");
    if ($endorsed['signature'] != $signature)
      error("endorsement signature mismatch");
    $endorsed_expires = intval($endorsed['expires']);
    if ($endorsement->expires != $endorsed_expires)
      error("endorsement doesn't expire at the same time as publication: $endorsement->expires != $endorsed_expires");
  }
  $query = "INSERT INTO endorsement(id, publicationKey, publicationSignature, publicationFingerprint, "
          ."revoked, message, comment) VALUES($id, \"$key\", \"$signature\", SHA1(\"$signature\"), "
          ."$endorsement->revoked, \"$endorsement->message\", \"$endorsement->comment\")";
} elseif ($type == 'referendum') {
  $referendum =&$publication;
  if (!isset($referendum->website))  # optional
    $referendum->website = '';
  if (!isset($referendum->question))  # optional
    $referendum->question = '';
  if (!isset($referendum->answers))  # optional
    $referendum->answers = '';
  $query = "INSERT INTO referendum(id, trustee, area, title, description, question, answers, deadline, website) "
          ."VALUES($id, \"$referendum->trustee\", \"$referendum->area\", \"$referendum->title\", \"$referendum->description\", "
          ."\"$referendum->question\", \"$referendum->answers\", $referendum->deadline, \"$referendum->website\")";
} elseif ($type == 'registration')
  $query = "INSERT INTO registration(id, referendum, stationKey, stationSignature) "
          ."VALUES($id, \"$publication->referendum\", \"" . $publication->station->key
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
  $query = "INSERT INTO ballot(id, referendum,$station_names answer) "
          ."VALUES($id, \"$publication->referendum\",$station_values \"$publication->answer\")";
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
  $query = "INSERT INTO area(id, name, polygons) VALUES($id, \"$publication->name\", $polygons)";
} else
  error("unknown publication type");
$mysqli->query($query) or error($mysqli->error);
if ($type == 'endorsement')
  echo json_encode(endorsements($mysqli, $publication->key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
else {
  $fingerprint = sha1($publication->signature);
  echo("{\"fingerprint\":\"$fingerprint\"}");
}
$mysqli->close();
?>
