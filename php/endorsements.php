<?php
function endorsements($mysqli, $key) {
  $now = intval(microtime(true) * 1000);  # milliseconds
  $query = "SELECT pc.fingerprint, MAX(pe.published) AS published, e.revoked, pc.`key`, pc.`signature`, "
          ."c.familyName, c.givenNames, ST_Y(home) AS latitude, ST_X(home) AS longitude, c.picture "
          ."FROM publication pe "
          ."INNER JOIN endorsement e ON e.id = pe.id "
          ."INNER JOIN publication pc ON pc.`signature` = e.publication "
          ."INNER JOIN citizen c ON pc.id = c.id "
          ."WHERE pe.`key` = '$key' "
          ."GROUP BY c.id "
          ."ORDER BY pe.published DESC";
  $result = $mysqli->query($query);
  if (!$result)
    return array('error' => $mysqli->error);
  $endorsements = array();
  while($e = $result->fetch_assoc()) {
    settype($e['published'], 'int');
    $e['revoke'] = intval($e['revoked']) < $now;
    unset($e['revoked']);
    settype($e['latitude'], 'float');
    settype($e['longitude'], 'float');
    $endorsements[] = $e;
  }
  $result->free();
  return $endorsements;
}
?>
