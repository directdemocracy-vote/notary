/* global L */

let geolocation = false;
let latitude = 0;
let longitude = 0;
let slider = 10;
let radius = slider * slider * slider;
let address = '';
let markers = [];

function findGetParameter(parameterName) {
  let result;
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  if (localStorage.getItem('password')) {
    const a = document.createElement('a');
    a.setAttribute('id', 'logout');
    a.textContent = 'logout';
    document.getElementById('logout-div').appendChild(a);
    document.getElementById('logout').addEventListener('click', function(event) {
      document.getElementById('logout-div').textContent = '';
      localStorage.removeItem('password');
    });
  }
  document.getElementById('proposal').addEventListener('click', function() {
    const judge = document.getElementById('judge').value.trim();
    window.open(`https://${judge}/propose.html?latitude=${latitude}&longitude=${longitude}`, '_blank');
  });

  document.getElementById('proposal-year').setAttribute('value', new Date().getFullYear());

  let tab = findGetParameter('tab');
  if (tab) {
    document.getElementById('citizens-tab').classList.remove('is-active');
    document.getElementById('citizens').style.display = 'none';
    document.getElementById(`${tab}-tab`).classList.add('is-active');
    document.getElementById(tab).style.display = 'block';
  }
  let judge = findGetParameter('judge');
  if (judge) {
    if (judge.startsWith('https://'))
      judge = judge.substring(8);
    if (judge.includes('.'))
      document.getElementById('judge').setAttribute('value', judge);
  }
  latitude = parseFloat(findGetParameter('latitude'));
  longitude = parseFloat(findGetParameter('longitude'));
  let zoom;
  if (isNaN(latitude) || isNaN(longitude)) {
    latitude = 0;
    longitude = 0;
    zoom = 2;
    if (navigator.geolocation)
      navigator.geolocation.getCurrentPosition(getGeolocationPosition);
    fetch('https://ipinfo.io/loc')
      .then(response => {
        if (response.status === 429)
          console.error('quota exceeded');
        return response.text();
      })
      .then(answer => {
        if (!geolocation) {
          let coords = answer.split(',');
          getGeolocationPosition({ coords: { latitude: coords[0], longitude: coords[1] } });
        }
      });
  } else
    zoom = 12;
  const map = L.map('map').setView([latitude, longitude], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.whenReady(function() { setTimeout(() => { this.invalidateSize(); }, 0); });
  const greenIcon = new L.Icon({
    iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
  const marker = L.marker([latitude, longitude]).addTo(map).bindPopup(latitude + ',' + longitude).on('click', updateLabel);
  const circle = L.circle([latitude, longitude], {
    color: 'red',
    opacity: 0.4,
    fillColor: '#f03',
    fillOpacity: 0.2,
    radius: radius
  }).addTo(map);
  marker.setPopupContent(`<div style="text-align:center" id="address">${address}</div>` +
    `<div><input type="range" min="5" max="100" value="${slider}" class="slider" id="range"></div>` +
    `<div style="text-align:center;color:#999" id="position">` +
    `(${latitude}, ${longitude} &plusmn; ${Math.round(radius / 100) / 10} km</div></center>`).openPopup();
  document.getElementById('range').addEventListener('input', rangeChanged);
  map.on('click', function(event) {
    marker.setLatLng(event.latlng).openPopup();
    circle.setLatLng(event.latlng);
    latitude = event.latlng.lat;
    longitude = event.latlng.lng;
    updateLabel();
    updatePosition();
    const range = document.getElementById('range');
    range.setAttribute('value', slider);
    range.addEventListener('input', rangeChanged);
  });
  map.on('contextmenu', function(event) { return false; });
  updatePosition();
  document.getElementById('search-citizens').addEventListener('click', function(event) {
    const fieldset = document.getElementById('citizens-fieldset');
    fieldset.setAttribute('disabled', '');
    const searchCitizen = event.currentTarget;
    searchCitizen.classList.add('is-loading');
    const familyName = document.getElementById('family-name').value;
    const givenNames = document.getElementById('given-names').value;
    judge = document.getElementById('judge').value;
    let parameters = `latitude=${latitude}&longitude=${longitude}&radius=${radius}`;
    if (judge && document.getElementById('endorsed-by-judge').checked)
      parameters += `&judge=https://${encodeURIComponent(judge)}`;
    if (familyName)
      parameters += `&familyName=${encodeURIComponent(familyName)}`;
    if (givenNames)
      parameters += `&givenNames=${encodeURIComponent(givenNames)}`;
    fetch(`/api/citizens.php?${parameters}`)
      .then((response) => response.json())
      .then((answer) => {
        markers.forEach(function(marker) { map.removeLayer(marker); });
        markers = [];
        answer.forEach(function(citizen) {
          const name = `${citizen.givenNames} ${citizen.familyName}`;
          const label = '<div style="text-align:center">' +
            `<a target="_blank" href="/citizen.html?signature=${encodeURIComponent(citizen.signature)}">` +
            `<img src="${citizen.picture}" width="60" height="80"><br>${name}</a></div>`;
          markers.push(L.marker([citizen.latitude, citizen.longitude], { icon: greenIcon }).addTo(map).bindPopup(label));
        });
        fieldset.removeAttribute('disabled');
        searchCitizen.classList.remove('is-loading');
      });
  });

  document.getElementById('proposal-referendum').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-petition').checked = true;
    searchProposals();
  });

  document.getElementById('proposal-petition').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-referendum').checked = true;
    searchProposals();
  });

  document.getElementById('proposal-open').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-closed').checked = true;
    searchProposals();
  });

  document.getElementById('proposal-closed').addEventListener('change', function(event) {
    if (!event.currentTarget.checked)
      document.getElementById('proposal-open').checked = true;
    searchProposals();
  });

  document.getElementById('search-proposals').addEventListener('click', searchProposals);

  function searchProposals() {
    const query = document.getElementById('proposal-query').value;
    const r = document.getElementById('proposal-referendum').checked;
    const p = document.getElementById('proposal-petition').checked;
    const secret = (r && p) ? 2 : (r ? 1 : 0);
    const o = document.getElementById('proposal-open').checked;
    const c = document.getElementById('proposal-closed').checked;
    const open = (c && o) ? 2 : (o ? 1 : 0);
    const year = document.getElementById('proposal-year').value;
    const limit = 10;
    fetchAndDisplayProposals(secret, open, query, latitude, longitude, radius, year, 0, limit);
  }

  function fetchAndDisplayProposals(secret, open, query, latitude, longitude, radius, year, offset, limit) {
    const fieldset = document.getElementById('proposals-fieldset');
    fieldset.setAttribute('disabled', '');
    const searchProposal = document.getElementById('search-proposals');
    searchProposal.classList.add('is-loading');
    fetch('/api/proposals.php' +
      `?secret=${secret}` +
      `&open=${open}` +
      `&search=${encodeURIComponent(query)}` +
      `&latitude=${latitude}` +
      `&longitude=${longitude}` +
      `&radius=${radius}` +
      `&year=${year}` +
      `&offset=${offset}` +
      `&limit=${limit}`)
      .then(response => response.json())
      .then(answer => {
        fieldset.removeAttribute('disabled');
        searchProposal.classList.remove('is-loading');
        const section = document.getElementById('proposal-results');
        section.style.display = '';
        section.innerHTML = '';
        if (answer.number === 0) {
          const div = document.createElement('div');
          div.textContent = 'No result found, try to refine your search.';
          section.appendChild(div);
          return;
        }
        const table = document.createElement('table');
        section.appendChild(table);
        table.classList.add('table', 'is-bordered', 'is-striped', 'is-narrow', 'is-hoverable', 'is-fullwidth');
        const thead = document.createElement('thead');
        table.appendChild(thead);
        let tr = document.createElement('tr');
        thead.appendChild(tr);
        let th = document.createElement('th');
        tr.appendChild(th);
        th.textContent = 'Type';
        th = document.createElement('th');
        tr.appendChild(th);
        th.textContent = 'Area';
        th = document.createElement('th');
        tr.appendChild(th);
        th.textContent = 'Title';
        th = document.createElement('th');
        tr.appendChild(th);
        th.textContent = 'Deadline';
        const tbody = document.createElement('tbody');
        table.appendChild(tbody);
        answer.proposals.forEach(function(proposal) {
          tr = document.createElement('tr');
          tbody.appendChild(tr);
          let td = document.createElement('td');
          td.textContent = `${proposal.secret ? 'Referendum' : 'Petition'}`;
          tr.appendChild(td);
          td = document.createElement('td');
          td.textContent = proposal.areas[0].split('=')[1];
          tr.appendChild(td);
          td = document.createElement('td');
          td.textContent = proposal.title;
          tr.appendChild(td);
          td = document.createElement('td');
          const deadline = new Date(proposal.deadline * 1000);
          const now = new Date();
          td.innerHTML = `<span style="color:#${deadline < now ? 'a00' : '0a0'}">${deadline.toLocaleString()}</span>`;
          tr.appendChild(td);
          tr.addEventListener('click', function() {
            const url = `/proposal.html?signature=${encodeURIComponent(proposal.signature)}`;
            window.open(url, '_blank').focus();
          });
        });

        if (offset > 0) {
          const prev = document.createElement('button');
          prev.textContent = 'Previous';
          prev.className = 'button is-info';
          prev.onclick = () => {
            fetchAndDisplayProposals(secret, open, query, latitude, longitude, radius, year, offset - limit, limit);
          };
          section.appendChild(prev);
        }

        if (limit + offset < answer.number) {
          const next = document.createElement('button');
          next.textContent = 'Next';
          next.className = 'button is-info';
          next.style.float = 'right';
          next.onclick = () => {
            fetchAndDisplayProposals(secret, open, query, latitude, longitude, radius, year, offset + limit, limit);
          };
          section.appendChild(next);
        }
      });
  }

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }

  function updatePosition() {
    marker.setLatLng([latitude, longitude]);
    circle.setLatLng([latitude, longitude]);
    fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=10`)
      .then(response => response.json())
      .then(answer => {
        address = answer.display_name;
        updateLabel();
      });
  }

  function rangeChanged(event) {
    slider = event.currentTarget.value;
    radius = slider * slider * slider;
    circle.setRadius(radius);
    updateLabel();
  }

  function updateLabel() {
    document.getElementById('address').textContent = address;
    document.getElementById('position').innerHTML = `(${latitude}, ${longitude}) &plusmn; ${Math.round(radius / 100) / 10} km`;
  }
};

function openTab(event, name) {
  let contentTab = document.getElementsByClassName('content-tab');
  for (let i = 0; i < contentTab.length; i++)
    contentTab[i].style.display = 'none';
  const tab = document.getElementsByClassName('tab');
  for (let i = 0; i < tab.length; i++)
    tab[i].classList.remove('is-active');
  document.getElementById(name).style.display = 'block';
  event.currentTarget.classList.add('is-active');
}
