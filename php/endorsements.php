<?php
function endorsements($mysqli, $key) {
  $query = "SELECT pc.fingerprint, pe.published, e.`revoke`, pc.`signature`, "
          ."c.familyName, c.givenNames, c.picture "
          ."FROM publication pe "
          ."INNER JOIN endorsement e ON e.id = pe.id "
          ."INNER JOIN publication pc ON pc.`signature` = e.endorsedSignature "
          ."INNER JOIN citizen c ON pc.id = c.id "
          ."WHERE pe.`key` = FROM_BASE64('$key') AND e.latest = 1 "
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
    $endorsements[] = $e;
  }
  $result->free();
  return $endorsements;
}
?>
