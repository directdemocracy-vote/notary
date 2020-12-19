<?php
function endorsements($mysqli, $key) {
  $query = "SELECT pc.fingerprint, pe.published, pe.expires, e.revoked, pc.`key`, pc.`signature`, "
          ."c.familyName, c.givenNames, ST_Y(home) AS latitude, ST_X(home) AS longitude, c.picture FROM "
          ."publication pe INNER JOIN endorsement e ON pe.id = e.id, "
          ."publication pc INNER JOIN citizen c ON pc.id = c.id "
          ."WHERE pe.`key` = '$key' AND pc.`key` = e.publicationKey "
          ."AND pc.`signature` = e.publicationSignature "
          ."ORDER BY e.revoke ASC, c.familyName, c.givenNames";
  $result = $mysqli->query($query);
  if (!$result)
    return array('error' => $mysqli->error);
  $endorsements = array();
  while($e = $result->fetch_assoc()) {
    settype($e['published'], 'int');
    settype($e['expires'], 'int');
    settype($e['revoke'], 'bool');
    settype($e['latitude'], 'float');
    settype($e['longitude'], 'float');
    $endorsements[] = $e;
  }
  $result->free();
  return $endorsements;
}
?>
