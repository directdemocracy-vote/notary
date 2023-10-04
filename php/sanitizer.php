<?php
function sanitize_field($variable, $type, $name) {
  global $mysqli;

  switch ($type) {
    case 'string':
      if (!is_string($variable))
        die("Error: $name should be a string");
      $variable = strip_tags($variable); // Remove html tags, not enough to prevent XSS but will avoid to store too much trash
      $variable = htmlspecialchars($variable); // Convert html special character to prevent XSS
      $variable = $mysqli->escape_string($variable);
      break;

    case 'year':
      $variable = intval($variable);
      if ($variable > 9999 or $variable < 2023)
        die("Error: $name should be between 2023 and 9999");

    case 'int_options':
      $variable = intval($variable);
      if ($variable < 0 or $variable > 2)
        die("Error: $name should be between 0 and 2");
  }
  return $variable;
}
?>
