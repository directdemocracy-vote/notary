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
    zoom = 11;
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

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }

  function updatePosition() {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&polygon_geojson=1&lat=${latitude}&lon=${longitude}&zoom=11&extratags=1&accept-language=${translator.language}`)
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
        document.getElementById('referendums').textContent = 0;
        document.getElementById('petitions').textContent = 0;
        let population = document.getElementById('population');
        population.textContent = '...';
        population.title = '';
        population.removeAttribute('href');
        // to estimate the population we use in this order:
        // 1. custom sources if available (depending on country), or
        // 2. wikidata or
        // 3. OSM data
        if (!answer.hasOwnProperty('extratags') || answer.extratags === null) {
          population.textContent = '?';
          translator.translateElement(population, 'population-not-provided-by-osm');
          population.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${answer.osm_id}&class=boundary`;
          n.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${answer.osm_id}&class=boundary`;
          translator.translateElement(n, 'wikipedia-page-not-provided-by-osm');
        } else if (!answer.extratags.hasOwnProperty('wikidata')) {
          if (!answer.extratags.hasOwnProperty('population')) {
            population.textContent = '?';
            translator.translateElement(population, 'population-not-found');
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
              if (answer.statements.hasOwnProperty('P771')) { // Swiss municipality code
                const code = parseInt(answer.statements.P771[0].value.content);
                fetch(`/api/CH-population.php?municipality=${code}`)
                  .then(response => response.json())
                  .then(answer => {
                    translator.translateElement(population, 'population-from-ofs-ch')
                    population.textContent = answer.population != -1 ? answer.population : 'N/A';
                    population.href = answer.url;
                  });
                return;
              }
              let p = '?';
              if (answer.statements.hasOwnProperty('P1082')) { // population
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
