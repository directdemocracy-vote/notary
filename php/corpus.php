<?php
function update_corpus($mysqli, $id) {
  $signed = <<<EOT
  SELECT pm.id FROM publication AS pm
  INNER JOIN certificate AS m ON m.id=pm.id AND m.type='sign'
  WHERE pm.`key`=pc.`key` AND m.publication=pp.signature
  EOT;
  $count = <<<EOT
  SELECT COUNT(citizen.id) FROM citizen
  INNER JOIN publication AS pc ON pc.id=citizen.id
  INNER JOIN certificate ON certificate.publication=pc.signature AND certificate.latest=1
  INNER JOIN publication AS pe ON pe.id=certificate.id
  INNER JOIN proposal ON proposal.id=$id
  INNER JOIN publication AS pp on pp.id=proposal.id
  INNER JOIN publication AS pa ON pa.`signature`=proposal.area
  INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))
  INNER JOIN participant AS judge ON judge.`type`='judge' AND judge.`key`=pp.`key` AND judge.`key`=pe.`key`
  WHERE certificate.`type`='endorse' OR (certificate.`type`='report' AND EXISTS($signed))
  EOT;
  $query = "UPDATE proposal SET corpus = ($count) WHERE proposal.id=$id";
  $mysqli->query($query) or die($mysqli->error);
  return;
}
?>
