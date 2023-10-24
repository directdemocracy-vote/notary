function findGetParameter(parameterName, result) {
  location.search.substr(1).split("&").forEach(function(item) {
    const tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  if (localStorage.getItem('password')) {
    document.getElementById('logout-div').innerHTML = `<a id="logout">logout</a>`;
    document.getElementById('logout').addEventListener('click', function(event) {
      document.getElementById('logout-div').innerHTML = ``;
      localStorage.removeItem('password');
    });
    // document.getElementById('')
  }
  let judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  document.getElementById('judge').value = judge.substring(8);
  const fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  if (!fingerprint && !signature) {
    console.error('Missing fingerprint or signature GET argument.');
    return;
  }
  const content = document.getElementById('content');
  const body = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${encodeURIComponent(fingerprint)}`;
  fetch('api/citizen.php', {method: 'POST', headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: body})
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      const published = publishedDate(answer.citizen.published);
      const givenNames = answer.citizen.givenNames;
      const familyName = answer.citizen.familyName;
      const latitude = answer.citizen.latitude;
      const longitude = answer.citizen.longitude;
      document.getElementById('picture').src = answer.citizen.picture;
      document.getElementById('given-names').innerHTML = givenNames;
      document.getElementById('family-name').innerHTML = familyName;
      document.getElementById('home').innerHTML = `<a href="https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}&zoom=12" target="_blank">${latitude}, ${longitude}</a>`;
      document.getElementById('created').innerHTML = published;
      const map = L.map('map');
      map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      marker = L.marker([latitude, longitude]).addTo(map);
      marker.bindPopup(`<b>${givenNames} ${familyName}</b><br>[${latitude}, ${longitude}]`);
      map.setView([latitude, longitude], 18);
      map.on('contextmenu', function(event) {return false;});
      fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=20`)
        .then(response => response.json())
        .then(answer => {
          const address = answer.display_name;
          marker.setPopupContent(`<b>${givenNames} ${familyName}</b><br>${address}`).openPopup();
        });
      document.getElementById('reload').addEventListener('click', function(event) {
        event.currentTarget.setAttribute('disabled', '');
        event.currentTarget.classList.add('is-loading');
        judge = 'https://' + document.getElementById('judge').value;
        const reputation = document.getElementById('reputation');
        reputation.innerHTML = '...';
        reputation.style.color = 'black';
        loadReputation();
        updateJudgeEndorsements();
      });

      function loadReputation() {
        fetch(`${judge}/api/reputation.php?key=${encodeURIComponent(answer.citizen.key)}`)
          .then((response) => {
            if (document.getElementById('judge-endorsements').innerHTML != '<b>...</b>')
              enableJudgeReloadButton();
            return response.json();
          })
          .then((answer) => {
            if (answer.error) {
              reputation.style.color = 'red';
              reputation.innerHTML = answer.error;
            } else {
              reputation.style.color = answer.endorsed ? 'green' : 'red';
              reputation.innerHTML = `${answer.reputation}`;
            }
          });
      }

      loadReputation();

      function updateJudgeEndorsements() {
        let div = document.getElementById('judge-endorsements');
        div.innerHTML = '<b>...</b>';
        const payload = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
        fetch(`/api/endorsements.php?${payload}&judge=${judge}`)
          .then(response => {
            if (reputation.innerHTML != '..')
              enableJudgeReloadButton();
            return response.json();
          })
          .then(answer => {
            if (answer.error) {
              console.error(answer.error);
              return;
            }
            div.innerHTML = '';
            for(const endorsement of answer.endorsements) {
              const block = document.createElement('div');
              div.appendChild(block);
              const d = new Date(parseInt(endorsement.published * 1000));
              const action = endorsement.revoke ? 'Revoked' : 'Endorsed';
              const latest = parseInt(endorsement.latest) === 1;
              const color = endorsement.revoke ? 'red' : 'green';
              const icon = endorsement.revoke ? 'xmark_seal_fill' : 'checkmark_seal_fill';
              block.innerHTML = `<p style="width:100%"><i class="icon f7-icons margin-right" style="color:${color};font-size:110%">${icon}</i>`
                              + `${latest ? '<b>' : ''}${action}${latest ? '</b>' : ''} on: ${d.toLocaleString()}</p>`;
            }
          });
      }

      updateJudgeEndorsements();

      function enableJudgeReloadButton() {
        let button = document.getElementById('reload');
        button.removeAttribute('disabled');
        button.classList.remove('is-loading');
      }

      function deg2rad(deg) {
        return deg * Math.PI / 180;
      }

      function distanceFromLatitudeLongitude(lat1, lon1, lat2, lon2) {
        const R = 6370986; // Radius of the Earth in m
        const dLat = deg2rad(lat2 - lat1);
        const dLon = deg2rad(lon2 - lon1);
        const a =
          Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
          Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const d = R * c;
        return d;
      }

      function addEndorsement(endorsement, name) {
        const columns = document.getElementById(name);
        const column = document.createElement('div');
        columns.appendChild(column);
        column.style.overflow = 'hidden';
        const img = document.createElement('img');
        column.appendChild(img);
        img.src = endorsement.picture;
        img.style.float = 'left';
        img.style.marginRight = '10px';
        img.style.marginBottom = '10px';
        img.style.width = '75px';
        const div = document.createElement('div');
        column.appendChild(div);
        div.classList.add('media-content');
        const content = document.createElement('div');
        div.appendChild(content);
        content.style.minWidth = '250px';
        const label = (endorsement.revoke) ? '<span style="font-weight:bold;color:red">Revoked</span>' : 'Endorsed';
        const published = publishedDate(endorsement.published);
        const distance = Math.round(distanceFromLatitudeLongitude(latitude, longitude, endorsement.latitude, endorsement.longitude));
        content.innerHTML =
          `<a href="/citizen.html?signature=${encodeURIComponent(endorsement.signature)}"><b>${endorsement.givenNames}<br>` +
          `${endorsement.familyName}</b></a><br><small>Distance: ${distance} m.<br>${label}: ${published}</small>`;
      }

      function publishedDate(seconds) {
        return new Date(seconds * 1000).toISOString().slice(0, 10);
      }

      let count = 0;
      answer.citizen_endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      document.getElementById('endorsed-by-header').innerHTML = answer.citizen_endorsements.length ? `Endorsed by ${count} / ${answer.citizen_endorsements.length}:` : `Not endorsed by anyone.`;
      answer.citizen_endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement, 'endorsed-by');
      });
      count = 0;
      answer.endorsements.forEach(function(endorsement) {
        if (!endorsement.revoke)
          count++;
      });
      document.getElementById('has-endorsed-header').innerHTML = answer.endorsements.length ? `Has endorsed ${count} / ${answer.endorsements.length}:` : `Has not endorsed anyone.`;
      answer.endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement, 'has-endorsed');
      });
    });
};
