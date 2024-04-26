/* global L */

let geolocation = false;
let latitude = 0;
let longitude = 0;
let address = '';
let area = null;

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
    const judge = document.getElementById('judge-input').value.trim();
    window.open(`https://${judge}/propose.html?latitude=${latitude}&longitude=${longitude}`, '_blank');
  });

  document.getElementById('proposal-year').setAttribute('value', new Date().getFullYear());

  const tab = findGetParameter('tab');
  if (tab && tab !== 'citizens') {
    document.getElementById('citizens-tab').classList.remove('is-active');
    document.getElementById('citizens').style.display = 'none';
    document.getElementById(`${tab}-tab`).classList.add('is-active');
    document.getElementById(tab).style.display = 'block';
  }
  const me = findGetParameter('me') === 'true';
  let judge = findGetParameter('judge');
  if (judge) {
    if (judge.startsWith('https://'))
      judge = judge.substring(8);
    if (judge.includes('.'))
      document.getElementById('judge-input').setAttribute('value', judge);
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
  map.on('click', function(event) {
    latitude = event.latlng.lat;
    longitude = event.latlng.lng;
    updatePosition();
  });
  map.on('contextmenu', function(event) { return false; });
  document.getElementById('search-citizens').addEventListener('click', function(event) {
    const fieldset = document.getElementById('citizens-fieldset');
    fieldset.setAttribute('disabled', '');
    const searchCitizen = event.currentTarget;
    searchCitizen.classList.add('is-loading');
    const familyName = document.getElementById('family-name').value;
    const givenNames = document.getElementById('given-names').value;
    judge = document.getElementById('judge-input').value;
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
    fetchAndDisplayProposals(secret, open, query, latitude, longitude, year, 0, limit);
  }

  function fetchAndDisplayProposals(secret, open, query, latitude, longitude, year, offset, limit) {
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
        translator.translateElement(th, 'type');
        th = document.createElement('th');
        tr.appendChild(th);
        translator.translateElement(th, 'area');
        th = document.createElement('th');
        tr.appendChild(th);
        translator.translateElement(th, 'title');
        th = document.createElement('th');
        tr.appendChild(th);
        translator.translateElement(th, 'deadline');
        const tbody = document.createElement('tbody');
        table.appendChild(tbody);
        answer.proposals.forEach(function(proposal) {
          tr = document.createElement('tr');
          tbody.appendChild(tr);
          let td = document.createElement('td');
          translator.translateElement(td, proposal.secret ? 'referendum' : 'petition');
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
            fetchAndDisplayProposals(secret, open, query, latitude, longitude, year, offset - limit, limit);
          };
          section.appendChild(prev);
        }

        if (limit + offset < answer.number) {
          const next = document.createElement('button');
          next.textContent = 'Next';
          next.className = 'button is-info';
          next.style.float = 'right';
          next.onclick = () => {
            fetchAndDisplayProposals(secret, open, query, latitude, longitude, year, offset + limit, limit);
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
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&polygon_geojson=1&lat=${latitude}&lon=${longitude}&zoom=12&extratags=1&accept-language=${translator.language}`)
      .then(response => response.json())
      .then(answer => {
        address = answer.display_name;
        if (area)
          area.remove();
        area = L.geoJSON(answer.geojson).addTo(map);
        let displayName = answer.display_name;
        if (displayName.startsWith(answer.name + ', '))
          displayName = displayName.substring(answer.name.length + 2);
        document.getElementById('commune-address').textContent = displayName;
        const n = document.getElementById('commune-name');
        n.textContent = answer.name;
        n.removeAttribute('data-i18n');
        document.getElementById('active-citizens').textContent = 0;
        document.getElementById('inactive-citizens').textContent = 0;
        let population = document.getElementById('population');
        // to estimate the population we use in this order:
        // 1. custom sources if available (depending on country), or
        // 2. wikidata or
        // 3. OSM data
        if (!answer.extratags.hasOwnProperty('wikidata')) {
          if (!answer.extratags.hasOwnProperty('population')) {
            population.textContent = '?';
            translator.translate(population, 'population-not-found');
            population.removeAttribute('href');
          } else {
            population.textContent = parseInt(answer.extratags.population);
            translator.translateElement(population, 'population-from-osm');
            population.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${answer.osm_id}&class=boundary`;
          }
          if (answer.extratags.hasOwnProperty('wikipedia')) {
            const colon = answer.extratags.wikipedia.indexOf(':');
            const wikipediaLanguage = answer.extratags.wikipedia.substring(0, colon);
            const wikipediaPage = answer.extratags.wikipedia.substring(colon + 1);
            n.href = `https://${wikipediaLanguage}.wikipedia.org/wiki/${wikipediaPage}`;
            translator.translateElement(n, 'wikipedia-page');
          } else {
            n.removeAttribute('href');
            translator.translateElement(n, 'wikipedia-page-not-found');
          }
        } else 
          fetch(`https://www.wikidata.org/w/rest.php/wikibase/v0/entities/items/${answer.extratags.wikidata}`)
            .then(response => response.json())
            .then(answer => {
              if (answer.hasOwnProperty('sitelinks')) {
                const wiki = translator.language + 'wiki';
                if (answer.sitelinks[wiki].hasOwnProperty('url')) {
                  n.href = answer.sitelinks[wiki].url;
                  translator.translateElement(n, 'wikipedia-page');
                } else {
                  n.removeAttribute('href');
                  translator.translateElement(n, 'wikipedia-page-not-found');
                }
              }
              if (answer.statements.hasOwnProperty('P771') { // Swiss municipality code
                const code = parseInt(answer.statements.P771[0].value.content);
                console.log('code = ' + code);
                fetch(`/api/CH-population.php?municipality=${code}`)
                  .then(response => response.json())
                  .then(answer => {
                    translator.translateElement(population, 'population-from-ofs-ch')
                    population.textContent = answer.population;
                    population.href = answer.url;
                  });
              }
              let p = '?';
              if (answer.statements.hasOwnProperty('P1082') { // population
                let rank = 'deprecated';
                for(let pop of answer.statements.P1082) {
                  if (rank === 'deprecated' || (rank === 'normal' && pop.rank === 'preferred')) {
                    p = parseInt(pop.value.content.amount);
                    rank = pop.rank;
                  }
                }
              }
              population.textContent = p;
              if (p === '?') {
                translator.translateElement(population, 'population-not-found');
                population.removeAttribute('href');
              } else {
                translator.translateElement(population, 'population-from-wikidata');
                population.href = `https://www.wikidata.org/wiki/${answer.id}`;
              }
            });
      });
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
  const section = document.getElementById('proposal-results');
  section.style.display = (name === 'proposals' && section.childElementCount !== 0) ? '' : 'none';
}
