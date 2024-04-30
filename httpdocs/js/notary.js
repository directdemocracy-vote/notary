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

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }

  function updatePosition() {
    document.getElementById('commune-address').textContent = '...';
    const n = document.getElementById('commune-name');
    n.removeAttribute('data-i18n');
    n.removeAttribute('href');
    n.textContent = '...';
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&polygon_geojson=1&lat=${latitude}&lon=${longitude}&zoom=12&extratags=1&accept-language=${translator.language}`)
      .then(response => response.json())
      .then(nominatim => {
        const osmId = nominatim.osm_id;
        address = nominatim.display_name;
        if (area)
          area.remove();
        area = L.geoJSON(nominatim.geojson).addTo(map);
        let displayName = nominatim.display_name;
        if (displayName.startsWith(nominatim.name + ', '))
          displayName = displayName.substring(nominatim.name.length + 2);
        document.getElementById('commune-address').textContent = displayName;
        n.textContent = nominatim.name;
        document.getElementById('active-citizens').textContent = '...';
        document.getElementById('inactive-citizens').textContent = '...';
        document.getElementById('referendums').textContent = '...';
        document.getElementById('petitions').textContent = '...';
        let population = document.getElementById('population');
        population.textContent = '...';
        population.title = '';
        population.removeAttribute('href');
        // to estimate the population we use in this order:
        // 1. custom sources if available (depending on country), or
        // 2. wikidata or
        // 3. OSM data
        if (!nominatim.hasOwnProperty('extratags') || nominatim.extratags === null) {
          population.textContent = '?';
          translator.translateElement(population, 'population-not-provided-by-osm');
          population.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${osmId}&class=boundary`;
          n.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${osmId}&class=boundary`;
          translator.translateElement(n, 'wikipedia-page-not-provided-by-osm');
        } else if (!nominatim.extratags.hasOwnProperty('wikidata')) {
          if (!nominatim.extratags.hasOwnProperty('population')) {
            population.textContent = '?';
            translator.translateElement(population, 'population-not-found');
            population.removeAttribute('href');
          } else {
            population.textContent = parseInt(nominatim.extratags.population);
            translator.translateElement(population, 'population-from-osm');
            population.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${osmId}&class=boundary`;
          }
          if (nominatim.extratags.hasOwnProperty('wikipedia')) {
            const colon = nominatim.extratags.wikipedia.indexOf(':');
            const wikipediaLanguage = nominatim.extratags.wikipedia.substring(0, colon);
            const wikipediaPage = nominatim.extratags.wikipedia.substring(colon + 1);
            n.href = `https://${wikipediaLanguage}.wikipedia.org/wiki/${wikipediaPage}`;
            translator.translateElement(n, 'wikipedia-page');
          } else {
            n.removeAttribute('href');
            translator.translateElement(n, 'wikipedia-page-not-found');
          }
        } else 
          fetch(`https://www.wikidata.org/w/rest.php/wikibase/v0/entities/items/${nominatim.extratags.wikidata}`)
            .then(response => response.json())
            .then(wikidata => {
              function populationFromP1082(statements) {
                let p = -1;
                if (statements.hasOwnProperty('P1082')) {
                  let rank = 'deprecated';
                  for(let pop of statements.P1082) {
                    if (rank === 'deprecated' || (rank === 'normal' && pop.rank === 'preferred')) {
                      p = parseInt(pop.value.content.amount);
                      rank = pop.rank;
                    }
                  }
                }
                return p;
              }
              if (wikidata.hasOwnProperty('sitelinks')) {
                const wiki = translator.language + 'wiki';
                if (wikidata.sitelinks.hasOwnProperty(wiki) && wikidata.sitelinks[wiki].hasOwnProperty('url')) {
                  n.href = wikidata.sitelinks[wiki].url;
                  translator.translateElement(n, 'wikipedia-page');
                } else {
                  n.removeAttribute('href');
                  translator.translateElement(n, 'wikipedia-page-not-found');
                }
              }
              if (wikidata.statements.hasOwnProperty('P771')) { // Swiss municipality code
                const code = parseInt(wikidata.statements.P771[0].value.content);
                fetch(`/api/CH-population.php?municipality=${code}`)
                  .then(response => response.json())
                  .then(ch => {
                    translator.translateElement(population, 'population-from-ofs-ch')
                    if (ch.population !== -1) {
                      population.textContent = ch.population;
                      population.href = ch.url;
                    } else {
                      p = populationFromP1082(wikidata.statements);
                      console.log(nominatim);
                      console.log(nominatim.extratags);
                      console.log(nominatim.extratags.population);
                      console.log(p);
                      if (p === -1 && nominatim.hasOwnProperty('extratags') && nominatim.extratags.hasOwnProperty('population')) {
                        p = nominatim.extratags.population;
                        population.href = `https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=${osmId}&class=boundary`;
                      } else
                        population.href = `https://www.wikidata.org/wiki/${wikidata.id}`;
                      population.textContent = p;
                      translator.translateElement(population, p === '?' ? 'population-not-found' : 'population-from-wikidata');
                    }
                  });
                return;
              }
              p = populationFromP1082(wikidata.statements);
              population.textContent = p;
              population.href = `https://www.wikidata.org/wiki/${wikidata.id}`;
              translator.translateElement(population, p === '?' ? 'population-not-found' : 'population-from-wikidata');
            });
        const judge = document.getElementById('judge-input').value.trim();
        fetch(`/api/commune.php?commune=${osmId}&judge=https://${judge}`)
          .then(response => response.json())
          .then(answer => {
            const active = parseInt(answer['active-citizens']);
            const inactive = parseInt(answer['inactive-citizens']);
            const referendums = parseInt(answer['referendums']);
            const petitions = parseInt(answer['petitions']);
            const a = document.getElementById('active-citizens');
            a.textContent = active;
            if (active === 0)
              a.removeAttribute('href');
            else
              a.href = `/citizens.html?commune=${osmId}&type=active&judge=https://${judge}`;
            const i = document.getElementById('inactive-citizens');
            i.textContent = inactive;
            if (inactive === 0)
              i.removeAttribute('href');
            else
              i.href = `/citizens.html?commune=${osmId}&type=inactive&judge=https://${judge}`;
            const r = document.getElementById('referendums');
            r.textContent = referendums;
            const p = document.getElementById('petitions');
            p.textContent = petitions;
          });
      });
  }
};
