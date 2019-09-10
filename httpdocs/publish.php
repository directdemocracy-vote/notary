<?php

require_once '../vendor/autoload.php';

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
        if ($image !== false) {
          $size = @getimagesizefromstring($data);
          if ($type != $size['mime'])
            return false;
          if ($size[0] != 150 || $size[1] != 200)
            return false;
          return true;
        } else
          return false;
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
$p = strrpos($publication->schema, '/', 12);
$type = substr($publication->schema, $p + 1, strlen($publication->schema) - $p - 12);  # remove the .schema.json suffix
$signature = base64_decode($publication->signature);
$key = $publication->key;
$publication->signature = '';
$data = json_encode($publication, JSON_UNESCAPED_SLASHES);
$verify = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("Wrong signature");
echo("{ \"published\": \"$type $p\" }");
?>
