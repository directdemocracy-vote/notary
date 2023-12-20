<?php
function update_corpus($mysqli, $id) {
  $signed = <<<EOT
  SELECT pm.id FROM publication AS pm
  INNER JOIN commitment AS m ON m.id=pm.id AND m.type='sign'
  WHERE pm.`key`=pc.`key` AND m.publication=pp.signature
  EOT;
  $count = <<<EOT
  SELECT COUNT(citizen.id) FROM citizen
  INNER JOIN publication AS pc ON pc.id=citizen.id
  INNER JOIN commitment ON commitment.publication=pc.signature AND commitment.latest=1
  INNER JOIN publication AS pe ON pe.id=commitment.id
  INNER JOIN proposal ON proposal.id=$id
  INNER JOIN publication AS pp on pp.id=proposal.id
  INNER JOIN publication AS pa ON pa.`signature`=proposal.area
  INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))
  INNER JOIN webservice AS judge ON judge.`type`='judge' AND judge.`key`=pp.`key` AND judge.`key`=pe.`key`
  WHERE commitment.`type`='endorse' OR (commitment.`type`='report' AND EXISTS($signed))
  EOT;
  $query = "UPDATE proposal SET corpus = ($count) WHERE proposal.id=$id";
  $mysqli->query($query) or die($mysqli->error);
  return;
}
?>
