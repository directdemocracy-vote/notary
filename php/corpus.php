<?php
function update_corpus($mysqli, $publication) {
  $signed = <<<EOT
  SELECT pm.id FROM publication AS pm
  INNER JOIN certificate AS m ON m.publication=pm.id AND m.type='sign'
  WHERE pm.participant=pc.participant AND m.publication=pp.signature
  EOT;
  $count = <<<EOT
  SELECT COUNT(citizen.publication) AS `count` FROM citizen
  INNER JOIN publication AS pc ON pc.id=citizen.publication
  INNER JOIN certificate ON certificate.certifiedPublication=pc.id AND certificate.latest=1
  INNER JOIN publication AS pe ON pe.id=certificate.publication
  INNER JOIN proposal ON proposal.publication=$publication
  INNER JOIN publication AS pp on pp.id=proposal.publication
  INNER JOIN participant AS judge ON judge.`type`='judge' AND judge.id=pp.participant AND judge.id=pe.participant
  INNER JOIN publication AS pa ON pa.participant=judge.id
  INNER JOIN area ON area.publication=pa.id AND area.id=proposal.area AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))
  WHERE certificate.`type`='trust' OR (certificate.`type`='report' AND EXISTS($signed))
  EOT;
  $query = "UPDATE proposal SET corpus = ($count) WHERE proposal.publication=$publication";
  $mysqli->query($query) or die($mysqli->error);
  return;
}
?>
