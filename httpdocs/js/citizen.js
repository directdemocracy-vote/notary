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
      document.getElementById('home').innerHTML = `<a href="https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}&zoom=12" target="_blank">${latitude}, ${longitude}</a>`;
      document.getElementById('created').innerHTML = published;
      let map = L.map('map');
      map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      marker = L.marker([latitude, longitude]).addTo(map);
      marker.bindPopup(`<b>${givenNames} ${familyName}</b><br>[${latitude}, ${longitude}]`);
      map.setView([latitude, longitude], 18);
      map.on('contextmenu', function(event) {return false;});
      let xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          const a = JSON.parse(this.responseText);
          const address = a.display_name;
          marker.setPopupContent(`<b>${givenNames} ${familyName}</b><br>${address}`).openPopup();
        }
      };
      xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' + longitude +
        '&zoom=20', true);
      xhttp.send();

      document.getElementById('reload').addEventListener('click', function(event) {
        event.currentTarget.setAttribute('disabled', '');
        event.currentTarget.classList.add('is-loading');
        judge = 'https://' + document.getElementById('judge').value;
        let reputation = document.getElementById('reputation');
        reputation.innerHTML = '...';
        reputation.style.color = 'black';
        loadReputation();
      });
      
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
            } else {
              reputation.style.color = answer.endorsed ? 'green' : 'red';
              reputation.innerHTML = answer.reputation;
            }
          } else {
            reputation.style.color = 'red';
            reputation.innerHTML = this.statusText + ' (' + this.status + ')';
          }
          let button = document.getElementById('reload');
          button.removeAttribute('disabled');
          button.classList.remove('is-loading');
        };
      }

      loadReputation();

      function addEndorsement(endorsement, name) {
        let columns = document.getElementById(name);        
        let column = document.createElement('div');
        columns.appendChild(column);
        let img = document.createElement('img');
        column.appendChild(img);
        img.src = endorsement.picture;
        img.style.float = 'left';
        img.style.marginRight = '10px';
        img.style.marginBottom = '10px';
        img.style.width = '75px';
        let div = document.createElement('div');
        column.appendChild(div);
        div.classList.add('media-content');
        let content = document.createElement('div');
        div.appendChild(content);
        content.style.minWidth = '250px';
        const label = (endorsement.revoke) ? "Revoked on" : "Endorsed on";
        const published = new Date(endorsement.published).toISOString().slice(0, 10);
        content.innerHTML =
          `<a href="/citizen.html?fingerprint=${endorsement.fingerprint}"><b>${endorsement.givenNames}<br>` +
          `${endorsement.familyName}</b></a><br><small>${label}<br>${published}</small>`;
      }
      let count = 0;
      answer.citizen_endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      document.getElementById('endorsed-by-header').innerHTML = count ? `Endorsed by ${count}:` : `Not endorsed by anyone.`;
      answer.citizen_endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement, 'endorsed-by');
      });
      count = 0;
      answer.endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      document.getElementById('has-endorsed-header').innerHTML = count ? `Has endorsed ${count}:` : `Has not endorsed anyone.`;
      answer.endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement, 'has-endorsed');
      });
    }
  };
};
