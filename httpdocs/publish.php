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
$p = strrpos($publication->schema, '/', 13);
$type = substr($publication->schema, $p + 1, strlen($publication->schema) - $p - 13);  # remove the .schema.json suffix
if ($type == 'card') {
  $card = &$publication;
  $data = base64_decode(substr($publication->picture, strlen('data:image/jpeg;base64,')));
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
$publication->signature = '';
$data = json_encode($publication, JSON_UNESCAPED_SLASHES);
$verify = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("Wrong signature");
$mysqli = new mysqli($database_host, $database_username, $database_password, $database_name);
if ($mysqli->connect_errno)
  error("Failed to connect to MySQL database: $mysqli->connect_error ($mysqli->connect_errno)");
$mysqli->set_charset('utf8mb4');
if ($type == 'card') {
  $query = "INSERT INTO card(schema, key, signature, published, expires, familyName, givenNames, picture, latitude, longitude) "
          ."VALUES('$card->schema', '$card->key', '$card->signature', '$card->published', '$card->expires', "
          ."'$card->familyName', '$card->givenNames', '$card->picture', $card->latitude, $card->longitude)";
  $mysqli->query($query);
}
echo("{ \"published\": \"$mysqli->insert_id\" }");
$mysqli->close();
?>
