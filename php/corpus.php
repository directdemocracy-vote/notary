<?php
function update_corpus($mysqli, $id) {
  $query = "UPDATE proposal SET corpus = "
          "(SELECT COUNT(citizen.id) FROM citizen "
          ."INNER JOIN publication AS pc ON pc.id=citizen.id "
          ."INNER JOIN endorsement ON endorsement.endorsedFingerprint=pc.fingerprint "
          ."INNER JOIN publication AS pe ON pe.id=endorsement.id "
          ."INNER JOIN proposal ON proposal.id=$id "
          ."INNER JOIN publication AS pa ON pa.`key`=proposal.area "
          ."INNER JOIN area ON area.id=pa.id "
          ."INNER JOIN judge ON judge.url = proposal.judge "
          ."WHERE endorsement.latest=1 AND endorsement.`revoke`=0 AND pe.`key`=judge.`key` "
          ."AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home))))";
  $mysqli->query($query) or die($mysqli->error);
  return;
}
?>