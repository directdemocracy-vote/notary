<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");
$municipality = intval($_GET['municipality']);
$data = file_get_contents('CH-cc-f-01.02.03.01.csv');
$rows = explode("\n", $data);
$population = -1;
foreach($rows as &$row) {
  $details = explode(",", $row);
  if (intval($details[0]) == $municipality) {
    $population = intval($details[1]);
    break;
  }
}
die("{\"population\":$population,\"url\":\"https://www.bfs.admin.ch/bfs/en/home/statistics/population.assetdetail.31345244.html\"}");
?>
