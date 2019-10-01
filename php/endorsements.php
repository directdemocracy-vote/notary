<?php
function endorsements($mysqli, $key) {
  $query = "SELECT pc.fingerprint, pe.published, pe.expires, e.revoke, pc.`key`, pc.`signature`, "
          ."c.familyName, c.givenNames, c.picture, c.latitude, c.longitude FROM "
          ."publication pe INNER JOIN endorsement e ON pe.id = e.id, "
          ."publication pc INNER JOIN citizen c ON pc.id = c.id "
          ."WHERE pe.`key` = '$key' AND pc.`key` = e.publicationKey "
          ."AND pc.`signature` = e.publicationSignature "
          ."ORDER BY e.revoke ASC, c.familyName, c.givenNames";
  $result = $mysqli->query($query);
  if (!$result)
    return "{\"error\":\"$mysqli->error\"}";
  $endorsements = array();
  while($e = $result->fetch_assoc()) {
    settype($e['published'], 'int');
    settype($e['expires'], 'int');
    settype($e['latitude'], 'int');
    settype($e['longitude'], 'int');
    settype($e['revoke'], 'bool');
    $endorsements[] = $e;
  }
  $result->free();
  return json_encode($endorsements, JSON_UNESCAPED_SLASHES);
}
?>
