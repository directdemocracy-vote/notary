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
  const osmid = findGetParameter('osmid');
  if (!osmid) {
    console.log('Missing osmid GET argument.');
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
  h2.innerHTML = `<a href="/referendum.html?fingerprint=${fingerprint}">${title}</a>`;
  let h4 = document.createElement('h4');
  content.appendChild(h4);
  h4.innerHTML =
    `<a href="https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${osmid}" target="_blank">${address}</a>`;
  let xhttp = new XMLHttpRequest();
  const polygon_url = 'https://nominatim.openstreetmap.org/details.php?osmtype=R&osmid=' + osmid +
    '&class=boundary&addressdetails=1&hierarchy=0&group_hierarchy=1&polygon_geojson=1&format=json';
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
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
      xhttp.onload = function() {
        if (this.status == 200) {
          let answer = JSON.parse(this.responseText);
          if (answer.error)
            console.log('publisher error', JSON.stringify(answer.error));
          else {
            const n = answer.length;
            if (n === 0) {
              let title = document.createElement('p');
              content.appendChild(title);
              title.innerHTML = "No participants here.";
            } else
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
                text.innerHTML = "<small>Created: " + new Date(answer[i].published).toLocaleDateString() + '<br>' +
                  "Expires: " + new Date(answer[i].expires).toLocaleDateString() + '</small><br>' + "Voted: <b>" +
                  new Date(answer[i].voted).toLocaleString().slice(-3) + '</b>';
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
