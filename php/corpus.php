<?php
function update_corpus($mysqli, $id) {
  $accepted = <<<EOT
  SELECT pep.id FROM publication AS pep
  INNER JOIN endorsement AS e ON e.id=pep.id AND e.accepted=1
  WHERE pep.`key`=pc.`key` AND e.endorsedFingerprint=pp.fingerprint
  EOT;
  $count = <<<EOT
  SELECT COUNT(citizen.id) FROM citizen
  INNER JOIN publication AS pc ON pc.id=citizen.id
  INNER JOIN endorsement ON endorsement.endorsedFingerprint=pc.fingerprint AND endorsement.latest=1
  INNER JOIN publication AS pe ON pe.id=endorsement.id
  INNER JOIN proposal ON proposal.id=$id
  INNER JOIN publication AS pp on pp.id=proposal.id
  INNER JOIN publication AS pa ON pa.`signature`=proposal.area
  INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))
  INNER JOIN judge ON judge.url = proposal.judge AND pe.`key`=judge.`key`
  WHERE endorsement.`revoke`=0 OR (endorsement.`revoke`=1 AND EXISTS($accepted))
  EOT;
  $query = "UPDATE proposal SET corpus = ($count) WHERE proposal.id=$id";
  $mysqli->query($query) or die($mysqli->error);
  return;
}
?>