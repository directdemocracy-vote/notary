<?php

require_once '../vendor/autoload.php';
require_once '../php/database.php';

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
  $keywordArgs = json_encode($error->keywordArgs(), JSON_UNESCAPED_SLASHES);
  error("{\"keyword\":\"$keyword\",\"keywordArgs\":$keywordArgs}");
}
$published = strtotime($publication->published);
$expires = strtotime($publication->expires);
$now = time();
if ($published > $now + 60)  # allowing a 1 minute error
  error("Publication date in the future: $publication->published");
if ($expires < $now)
  error("Expiration date in the past: $publication->expires");
$type = get_type($publication->schema);
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
}
$signature = base64_decode($publication->signature);
$key = $publication->key;
$signature_copy = $publication->signature;
$publication->signature = '';
$data = json_encode($publication, JSON_UNESCAPED_SLASHES);
$verify = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("Wrong signature");
$publication->signature = $signature_copy;
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
$query = "INSERT INTO publication(`schema`, `key`, signature, fingerprint, published, expires) "
        ."VALUES('$publication->schema', '$publication->key', '$publication->signature', "
        ."SHA1('$publication->signature'), '$publication->published', '$publication->expires')";
$mysqli->query($query) or error($mysqli->error);
$id = $mysqli->insert_id;
if ($type == 'citizen') {
  $query = "INSERT INTO citizen(id, familyName, givenNames, picture, latitude, longitude) "
          ."VALUES($id, '$citizen->familyName', '$citizen->givenNames', "
          ."'$citizen->picture', $citizen->latitude, $citizen->longitude)";
  $mysqli->query($query) or error($mysqli->error);
} elseif ($type == 'endorsement') {
  $endorsement = &$publication;
  if (!isset($endorsement->message))
    $endorsement->message = '';
  if (!isset($endorsement->comment))
    $endorsement->comment = '';
  $key = $endorsement->publication->key;
  $signature = $endorsement->publication->signature;
  $query = "SELECT id, `schema`, `key`, signature, expires FROM publication WHERE fingerprint=SHA1('$signature')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsed = $result->fetch_assoc();
  if ($endorsed) {
    if ($endorsed['key'] != $key)
      error("endorsement key mismatch");
    if ($endorsed['signature'] != $signature)
      error("endorsement signature mismatch");
    $endorsement_expires = strtotime($endorsement->expires);
    $endorsed_expires = strtotime($endorsed['expires']);
    if ($endorsement_expires > $endorsed_expires);
      error("endorsement expires after publication");
    if ($endorsement->revoke) {
      if ($endorsement_expires != $endorsed_expires)
        error("revoke endorsement don't expire at the same time as publication");
      $i = $endorsed->id;
      $query = "DELETE FROM publication WHERE id=$i";
      $mysqli->query($query) or error($mysqli->error);
      $t = get_type($endorsed->schema);
      $query = "DELETE FROM `$t` WHERE id=$i";
      $mysqli->query($query) or error($mysqli->error);
    }
  }
  $query = "INSERT INTO endorsement(id, publicationKey, publicationSignature, publicationFingerprint, "
          ."`revoke`, message, comment) VALUES($id, '$key', '$signature', SHA1('$signature'), "
          ."'$endorsement->revoke', '$endorsement->message', '$endorsement->comment')";
  $mysqli->query($query) or error($mysqli->error);
}
echo("{\"$type\":\"$id\"}");
$mysqli->close();
?>
