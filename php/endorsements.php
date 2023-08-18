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
  while($e = $result->fetch_assoc()) {
    settype($e['published'], 'int');
    $e['revoke'] = (intval($e['revoke']) == 1);
    settype($e['latitude'], 'float');
    settype($e['longitude'], 'float');
    $id = $e['id'];
    unset($e['id']);
    $endorsements[$id] = $e;
  }
  $result->free();
  $e = array();
  foreach ($endorsements as $endorsement)
    $e[] = $endorsement;
  return $e;
}
?>
