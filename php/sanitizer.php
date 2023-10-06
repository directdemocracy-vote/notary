<?php

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

function sanitize_field($methods, $type, $name) {
  $variable = null;
  if ($methods === 'get') {
    if (isset($_GET[$name]))
      $variable = $_GET[$name];
  } elseif ($methods === 'post') {
    if (isset($_POST[$name]))
      $variable = $_POST[$name];
  } else
    $variable = $methods; // for case like $registration->citizen

  if (!isset($variable))
    return null;

  switch ($type) {
    case 'string':
      $variable = sanitize_string($variable, $name);
      break;
    case 'year':
      $variable = intval($variable);
      if ($variable > 9999 or $variable < 2023)
        error("$name should be between 2023 and 9999");
      break;
    case 'int_options':
      $variable = intval($variable);
      if ($variable < 0 or $variable > 2)
        error("$name should be between 0 and 2");
      break;
    case 'base_64':
      $variable = sanitize_string($variable, $name);

      $str = base64_decode($variable, true);
      if ($str === false)
        error("Bad characters in base 64 variable $name.");
      else {
        $b64 = base64_encode($str);
        if ($variable !== $b64)
          error("Invalid base 64 variable $name.");
      }
      break;
    case 'hex':
      $variable = sanitize_string($variable, $name);
      if (!ctype_xdigit($variable))
        error("Variable $name is not in hexadecimal format.");
      break;
    case 'float':
      $variable = floatval($variable);
      break;
    case 'positive_float':
      $variable = floatval($variable);
      if ($variable < 0)
        error("Variable $name should be positive.");
      break;
    case 'positive_int':
      $variable = intval($variable);
      if ($variable < 0)
        error("Variable $name should be positive.");
      break;
    case 'url':
      $variable = sanitize_string($variable, $name);
      $variable = filter_var($variable, FILTER_SANITIZE_URL);
      if (!filter_var($variable, FILTER_VALIDATE_URL))
           error("$name is not a valid URL");
      break;
    default:
      error("Unknown type: $type");
  }
  return $variable;
}

function sanitize_string($variable, $name) {
  global $mysqli;

  if (!is_string($variable))
    error("Error: $name should be a string");
  $variable = strip_tags($variable); // Remove html tags, not enough to prevent XSS but will avoid to store too much trash
  $variable = htmlspecialchars($variable); // Convert html special character to prevent XSS
  $variable = $mysqli->escape_string($variable);

  return $variable;
}
?>
