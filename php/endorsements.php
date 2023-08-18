<?php
function endorsements($mysqli, $key) {
  $query = "SELECT pc.id, pc.fingerprint, pe.published, e.`revoke`, pc.`key`, pc.`signature`, "
          ."c.familyName, c.givenNames, ST_Y(home) AS latitude, ST_X(home) AS longitude, c.picture "
          ."FROM publication pe "
          ."INNER JOIN endorsement e ON e.id = pe.id "
          ."INNER JOIN publication pc ON pc.`signature` = e.endorsedSignature "
          ."INNER JOIN citizen c ON pc.id = c.id "
          ."WHERE pe.`key` = '$key' "
          ."ORDER BY pe.published DESC";
  $result = $mysqli->query($query);
  if (!$result)
    return array('error' => $mysqli->error);
  $endorsements = array();
  $already = array();
  while($e = $result->fetch_assoc()) {
    if (!in_array($e['id'], $already) {
      $already[] = $e['id'];
      unset($e['id']);
      settype($e['published'], 'int');
      $e['revoke'] = (intval($e['revoke']) == 1);
      settype($e['latitude'], 'float');
      settype($e['longitude'], 'float');
      $endorsements[] = $e;
    }
  }
  $result->free();
  return $endorsements;
}
?>
