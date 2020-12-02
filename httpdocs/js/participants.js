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
      let polygons;
      let type = geometry.type.toLowerCase();
      if (type === 'polygon')
        polygons = [geometry.coordinates];
      else if (type === 'multipolygon')
        polygons = geometry.coordinates;
      let participation = {
        fingerprint: fingerprint,
        polygons: polygons
      };
      /*
      let coords = answer.geometry.coordinates[0];
      console.log(coords);
      console.log(coords.length);
      for (let i = 0; i < coords.length; i++) {
        console.log(coords[i][0] + ', ' + coords[i][1]);
      }
      coords.forEach(function(c) {
        console.log(c[0] + ', ' + c[1]);
      });
      */
      xhttp.onload = function() {
        if (this.status == 200) {
          let answer = JSON.parse(this.responseText);
          if (answer.error)
            console.log('publisher error', JSON.stringify(answer.error));
          else {
            console.log(answer);
            const n = answer.length;
            for (let i = 0; i < n; i++) {
              let card = document.createElement('div');
              content.appendChild(card);
              card.classList.add('card');
              card.style.marginTop = '10px';
              let clearfix = document.createElement('clearfix');
              card.appendChild(clearfix);
              let img = document.createElement('img');
              clearfix.appendChild(img);
              img.classList.add('float-left');
              img.style.width = '25%';
              img.style.margin = '10px';
              img.src = answer[i].picture;
              img.alt = answer[i].familyName + ' ' + answer[i].givenNames;
              let title = document.createElement('h5');
              title.style.marginTop = '15px';
              clearfix.appendChild(title);
              title.classList.add('card-title');
              title.innerHTML = answer[i].familyName + ' ' + answer[i].givenNames;
              let text = document.createElement('p');
              clearfix.appendChild(text);
              text.classList.add('card-text');
              text.innerHTML = "Created: " + new Date(answer[i].published).toISOString().slice(0, 10) + '<br>' +
                "Expires: " + new Date(answer[i].expires).toISOString().slice(0, 10) + '<br>' + "Reputation: ";
            }
          }
        }
      };
      xhttp.open('POST', '/participants.php', true);
      xhttp.send(JSON.stringify(participation));
    }
  };
  xhttp.open('GET', polygon_url);
  xhttp.send();
};
