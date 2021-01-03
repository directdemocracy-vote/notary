function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    let tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  let trustee = findGetParameter('trustee', 'https://trustee.directdemocracy.vote');
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
        `<div><span class="citizen-label">Created:</span> <b>${published}</b></div>` +
        `<div><span class="citizen-label">Expires:</span> <b>${expires}</b></div>`;
      row = document.createElement('div');
      content.appendChild(row);
      row.id = 'map';
      row.style.width = '100%';
      row.style.height = '400px';
      let map = L.map('map', {
        dragging: false,
        scrollWheelZoom: false
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
      marker.bindPopup(`<b>${familyName}</b> ${givenNames}<br>[${latitude}, ${longitude}]`);
      map.setView([latitude, longitude], 18);
      map.on('contextmenu', function(event) {
        return false;
      });
      map.dragging.disable();
      let xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          const a = JSON.parse(this.responseText);
          const address = a.display_name;
          marker.setPopupContent(`<b>${familyName}</b> ${givenNames}<br>${address}`).openPopup();
        }
      };
      xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' + longitude +
        '&zoom=20', true);
      xhttp.send();

      row = document.createElement('div');
      content.appendChild(row);
      row.classList.add('row');
      row.classList.add('mt-4');
      row.innerHTML =
        `<h4>Reputation: <span id="reputation">...</span></h4>
<div class="input-group mb-3">
  <span class="input-group-text" id="protocol">https://</span>
  <input id="trustee" type="text" class="form-control" placeholder="https://trustee.directdemocracy.vote"
   aria-label="Trustee URL" aria-describedby="protocol" value="${trustee.substring(8)}">
   <button class="btn btn-outline-secondary" type="button" id="reload"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
  <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
  <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
</svg></button>
</div>`;
      document.getElementById('reload').addEventListener('click', function(event) {
        trustee = 'https://' + document.getElementById('trustee').value;
        document.getElementById('reputation').innerHTML = '...';
        loadReputation();
      });

      function loadReputation() {
        const url = trustee + '/reputation.php?key=' + encodeURIComponent(answer.citizen.key);
        xhttp = new XMLHttpRequest(); // get reputation from trustee
        xhttp.open('GET', url, true);
        xhttp.send();
        xhttp.onload = function() {
          let reputation = document.getElementById('reputation');
          if (this.status == 200) {
            let answer = JSON.parse(this.responseText);
            if (answer.error) {
              reputation.style.color = 'red';
              reputation.innerHTML = answer.error;
              return;
            }
            reputation.style.color = answer.endorsed ? 'blue' : 'red';
            reputation.innerHTML = answer.reputation;
          } else {
            reputation.style.color = 'red';
            reputation.innerHTML = this.statusText + ' (' + this.status + ')';
          }
        };
      }

      loadReputation();

      function addEndorsement(endorsement) {
        let card = document.createElement('div');
        content.appendChild(card);
        card.classList.add('card');
        card.classList.add('mb-1');
        let label;
        if (endorsement.revoke) {
          card.classList.add('revoked');
          label = "Revoked on";
        } else
          label = "Endorsed on";
        const published = new Date(endorsement.published).toISOString().slice(0, 10);
        let expires;
        if (endorsement.hasOwnProperty('expires') && !endorsement.revoke)
          expires = `<br>Expires on ` + new Date(endorsement.expires).toISOString().slice(0, 10);
        else
          expires = '';
        card.innerHTML =
          `<div class="card-body"><div class="row"><div class="col-3"><img style="width:75px" ` +
          `src="${endorsement.picture}"></div><div class="col-9">` +
          `<a href="/citizen.html?fingerprint=${endorsement.fingerprint}"<b>${endorsement.familyName}</b> ` +
          `${endorsement.givenNames}</a><br><small>${label} ${published}${expires}</small></div></div></div>`;
      }

      row = document.createElement('div');
      content.appendChild(row);
      row.classList.add('row');
      row.classList.add('mt-4');
      let count = 0;
      answer.citizen_endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      row.innerHTML = count ? `<h4>Endorsed by ${count}:</h4>` : `<h4>Not endorsed by anyone</h4>`;
      answer.citizen_endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement);
      });
      row = document.createElement('div');
      content.appendChild(row);
      row.classList.add('row');
      row.classList.add('mt-4');
      count = 0;
      answer.endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      row.innerHTML = count ? `<h4>Endorsing ${count}:</h4>` : `<h4>Not endorsing anyone</h4>`;
      answer.endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement);
      });
    }
  };
};
