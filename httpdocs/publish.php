<?php
function error($message) {
  die("{ \"error\": \"$message\" }");
}
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
# build a PHP variable from JSON sent using POST method
$publication = json_decode(file_get_contents("php://input"));
if (!$publication)
  error("Unable to parse JSON post");
if (!isset($publication->schema))
  error("Unable to read schema field");
$signature = base64_decode($publication->signature);
$key = $publication->key;
$publication->signature = '';
$data = json_encode($publication, JSON_UNESCAPED_SLASHES);
$verify = openssl_verify($data, $signature, $key, OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("Cannot verify signature ($verify)");
?>
