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
  const title = findGetParameter('title');
  if (!title) {
    console.log('Missing title GET argument.');
    return;
  }
  const address = findGetParameter('address');
  if (!address) {
    console.log('Missing address GET argument.');
    return;
  }
  let content = document.getElementById('content');
  let h2 = document.createElement('h2');
  content.appendChild(h2);
  h2.innerHTML = title;
  let h4 = document.createElement('h4');
  content.appendChild(h4);
  h4.innerHTML = address;
  let xhttp = new XMLHttpRequest();
  const polygon_url = 'https://nominatim.openstreetmap.org/details.php?osmtype=R&osmid=' + osm_id +
    '&class=boundary&addressdetails=1&hierarchy=0&group_hierarchy=1&polygon_geojson=1&format=json';
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      console.log(answer);
      let geometry = answer.geometry;
      let data = {
        fingerprint: fingerprint,
        polygon: answer.geometry.coordinates
      };
      console.log(answer.geometry.coordinates);
      for (let i = 0; i < answer.geometry.coordinates.length; i++) {
        console.log(answer.geometry.coordinates[i][0] + ', ' + answer.geometry.coordinates[i][1]);
      }
      answer.geometry.coordinates.forEach(function(c) {
        console.log(c[0] + ', ' + c[1]);
      });
      xhttp.onload = function() {
        if (this.status == 200) {
          let answer = JSON.parse(this.responseText);
          if (answer.error)
            console.log('publisher error', JSON.stringify(answer.error));
          else {
            console.log(answer);
          }
        }
      };
      // xhttp.open('POST', '/participants.php', true);
      // xhttp.send(JSON.stringify(data));
    }
  };
  xhttp.open('GET', polygon_url);
  xhttp.send();
};
