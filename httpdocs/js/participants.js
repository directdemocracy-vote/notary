function findGetParameter(parameterName) {
  let result = null;
  let tmp = [];
  location.search.substr(1).split("&").forEach(function(item) {
    tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  const fingerprint = findGetParameter('fingerprint');
  if (!fingerprint) {
    console.log('Missing fingerprint GET argument.');
    return;
  }
  const osm_id = findGetParameter('osm_id');
  if (!osm_id) {
    console.log('Missing osm_id GET argument.');
    return;
  }
  let xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error)
        console.log('publisher error', JSON.stringify(referendum.error));
      else {
        let list = '<ul>';
        console.log(answer);
        answer.hierarchy.forEach(function(place) {
          console.log(place.localname, place.osm_id);
          if (answer.osm_type === 'R' && place.class === 'boundary' && place.type === 'administrative')
            list += '<li><a href="/participants.html?fingerprint=' + fingerprint + '&osm_id=' + place.osm_id + '">' + place.localname + '</a></li>';
        });
        list += '</ul>';
        document.getElementById('content').innerHTML = list;
      }
    }
  };
  xhttp.open('GET',
    `https://nominatim.openstreetmap.org/details.php?osmtype=R&osmid=${osm_id}&class=boundary&hierarchy=1&format=json`,
    true);
  xhttp.send();
};
