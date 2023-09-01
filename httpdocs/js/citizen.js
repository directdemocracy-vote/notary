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
  fetch('api/citizen.php', {method: 'POST', headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: `fingerprint=${encodeURIComponent(fingerprint)}`})
    .then((response) => response.json())
    .then((answer) => {
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
      fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=20`)
        .then((response) => response.json())
        .then((answer) => {
          const address = answer.display_name;
          marker.setPopupContent(`<b>${givenNames} ${familyName}</b><br>${address}`).openPopup();
        });
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
        fetch(`${judge}/api/reputation.php?key=${encodeURIComponent(answer.citizen.key)}`)
          .then((response) => response.json())
          .then((answer) => {
            if (answer.error) {
              reputation.style.color = 'red';
              reputation.innerHTML = answer.error;
            } else {
              reputation.style.color = answer.endorsed ? 'green' : 'red';
              reputation.innerHTML = `&bull; <a target="_blank" href="endorsements.html?fingerprint=${fingerprint}&judge=${judge}">${answer.reputation}</a>`;
            }
            let button = document.getElementById('reload');
            button.removeAttribute('disabled');
            button.classList.remove('is-loading');
          });
      }

      loadReputation();

      function addEndorsement(endorsement, name) {
        let columns = document.getElementById(name);        
        let column = document.createElement('div');
        columns.appendChild(column);
        column.style.overflow = 'hidden';
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
        const label = (endorsement.revoke) ? '<span style="font-weight:bold;color:red">Revoked</span>' : 'Endorsed';
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
}
