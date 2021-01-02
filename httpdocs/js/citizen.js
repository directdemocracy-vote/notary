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
  let content = document.getElementById('content');
  let xhttp = new XMLHttpRequest();
  xhttp.open('POST', '/citizen.php', true);
  xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhttp.send('fingerprint=' + encodeURIComponent(fingerprint));
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error) {
        console.log(answer.error);
        return;
      }
      let row = document.createElement('div');
      content.appendChild(row);
      row.classList.add('row');
      let col = document.createElement('div');
      row.appendChild(col);
      col.classList.add('col');
      col.style.maxWidth = '170px';
      let img = document.createElement('img');
      col.appendChild(img);
      img.src = answer.citizen.picture;
      col = document.createElement('div');
      row.appendChild(col);
      col.classList.add('col');
      const published = new Date(answer.citizen.published).toISOString().slice(0, 10);
      const expires = new Date(answer.citizen.expires).toISOString().slice(0, 10);
      const latitude = answer.citizen.latitude;
      const longitude = answer.citizen.longitude;
      const familyName = answer.citizen.familyName;
      const givenNames = answer.citizen.givenNames;
      col.innerHTML =
        `<div class="citizen-label">Family name:</div><div class="citizen-entry">${familyName}</div>` +
        `<div class="citizen-label">Given names:</div><div class="citizen-entry">${givenNames}</div>` +
        `<div class="citizen-label">Latitude, longitude:</div>` +
        `<div class="citizen-entry">${latitude}, ${longitude}</div>` +
        `<div><span class="citizen-label">Created:</span><b style="float:right">${published}</b></div>` +
        `<div><span class="citizen-label">Expires:</span><b style="float:right">${expires}</b></div>`;
      row = document.createElement('div');
      content.appendChild(row);
      row.id = 'map';
      row.style.width = '100%';
      row.style.height = '400px';
      let map = L.map('map', {
        dragging: false
      });
      map.whenReady(function() {
        setTimeout(() => {
          this.invalidateSize();
        }, 0);
      });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      marker = L.marker([latitude, longitude]).addTo(map);
      marker.bindPopup(latitude + ', ' + longitude);
      map.setView([latitude, longitude], 18);
      map.on('contextmenu', function(event) {
        return false;
      });
      let nominatim = new XMLHttpRequest();
      nominatim.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          const a = JSON.parse(this.responseText);
          console.log(a);
          const address = a.display_name;
          marker.setPopupContent(`<b>${familyName}</b> ${givenNames}<br>${address}`).openPopup();
        }
      };
      nominatim.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' +
        longitude + '&zoom=20', true);
      nominatim.send();
    }
  };
};
