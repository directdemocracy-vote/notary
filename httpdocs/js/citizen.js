function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    let tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  let judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  document.getElementById('judge').value = judge.substring(8);
  const fingerprint = findGetParameter('fingerprint');
  if (!fingerprint) {
    console.log('Missing fingerprint GET argument.');
    return;
  }
  let content = document.getElementById('content');
  let xhttp = new XMLHttpRequest();
  xhttp.open('POST', '/api/citizen.php', true);
  xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhttp.send('fingerprint=' + encodeURIComponent(fingerprint));
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error) {
        console.log(answer.error);
        return;
      }
      const published = new Date(answer.citizen.published).toISOString().slice(0, 10);
      const givenNames = answer.citizen.givenNames;
      const familyName = answer.citizen.familyName;
      const latitude = answer.citizen.latitude;
      const longitude = answer.citizen.longitude;
      document.getElementById('picture').src = answer.citizen.picture;
      document.getElementById('given-names').innerHTML = givenNames;
      document.getElementById('family-name').innerHTML = familyName;
      document.getElementById('home').innerHTML = `${latitude}, ${longitude}`;
      document.getElementById('created').innerHTML = published;
      let map = L.map('map');
      map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      marker = L.marker([latitude, longitude]).addTo(map);
      marker.bindPopup(`<b>${familyName}</b> ${givenNames}<br>[${latitude}, ${longitude}]`);
      map.setView([latitude, longitude], 18);
      // map.on('contextmenu', function(event) {return false;});
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

      /*
      document.getElementById('reload').addEventListener('click', function(event) {
        judge = 'https://' + document.getElementById('judge').value;
        document.getElementById('reputation').innerHTML = '...';
        loadReputation();
      });
      */
      function loadReputation() {
        const url = judge + '/api/reputation.php?key=' + encodeURIComponent(answer.citizen.key);
        xhttp = new XMLHttpRequest(); // get reputation from judge
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
        card.innerHTML =
          `<div class="card-body"><div class="row"><div class="col-3"><img style="width:75px" ` +
          `src="${endorsement.picture}"></div><div class="col-9">` +
          `<a href="/citizen.html?fingerprint=${endorsement.fingerprint}"<b>${endorsement.familyName}</b> ` +
          `${endorsement.givenNames}</a><br><small>${label} ${published}</small></div></div></div>`;
      }
/*
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
      */
    }
  };
};
