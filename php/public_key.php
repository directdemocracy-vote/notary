<?php

function public_key($key) {
  $publicKey = "-----BEGIN PUBLIC KEY-----\n";
  $key = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA' . $key . 'IDAQAB';
  $l = strlen($key);
  for($i = 0; $i < $l; $i += 64)
    $publicKey .= substr($key, $i, 64) . "\n";
  $publicKey.= "-----END PUBLIC KEY-----";
  return $publicKey;
}

?>
