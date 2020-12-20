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
    console.log('Missing fingerprint GET argument');
    return;
  }
  document.getElementById('fingerprint').value = fingerprint;
  const rectangle = document.getElementById('fingerprint-group').getBoundingClientRect();
  let size = rectangle.right - rectangle.left;
  if (size > 512)
    size = 512;
  let qr = new QRious({
    element: document.getElementById('qr-code'),
    value: fingerprint,
    level: 'M',
    size,
    padding: 0
  });
  document.getElementById('copy-button').addEventListener('click', function(event) {
    let input = document.getElementById('fingerprint');
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand("copy");
    input.setSelectionRange(0, 0);
    input.blur();
    message = document.getElementById('copy-message');
    message.innerHTML = "copied!";
    setTimeout(function() {
      message.innerHTML = '';
    }, 1000);
  });
  let geolocation = false;
  let latitude = 0;
  let longitude = 0;
  let referendum = null;
  if (navigator.geolocation) navigator.geolocation.getCurrentPosition(getGeolocationPosition);

  let xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200 && geolocation == false) {
      coords = this.responseText.split(',');
      getGeolocationPosition({
        coords: {
          latitude: coords[0],
          longitude: coords[1]
        }
      });
    } else if (this.status == 429) { // quota exceeded
      console.log(this.responseText);
    }
  };
  xhttp.open("GET", "https://ipinfo.io/loc", true);
  xhttp.send();

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
  }
  let map = L.map('latlongmap').setView([latitude, longitude], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.whenReady(function() {
    setTimeout(() => {
      this.invalidateSize();
    }, 0);
  });
  const greenIcon = new L.Icon({
    iconUrl: '//cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: '//cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
  let marker = L.marker([latitude, longitude]).addTo(map);
  map.on('click', onMapClick);
  map.on('contextmenu', function(event) {
    return false;
  });

  function onMapClick(e) {
    marker.setLatLng(e.latlng);
    latitude = e.latlng.lat;
    longitude = e.latlng.lng;
    updatePosition();
  }

  function updatePosition() {
    marker.setLatLng([latitude, longitude]);
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        a = JSON.parse(this.responseText);
        document.getElementById("address").href = '/participants.html?fingerprint=' + fingerprint + '&osmid=' + a.osm_id +
          '&address=' + encodeURIComponent(a.display_name) + '&title=' + encodeURIComponent(referendum.title);
        document.getElementById("address").innerHTML = a.display_name;
      }
    };
    xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' + longitude +
      '&zoom=10', true);
    xhttp.send();
  }

  xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
    if (this.status == 200) {
      referendum = JSON.parse(this.responseText);
      if (referendum.error)
        console.log('publisher error', JSON.stringify(referendum.error));
      else {
        const first_equal = referendum.area.indexOf('=');
        const first_newline = referendum.area.indexOf('\n');
        let area_name = referendum.area.substr(first_equal + 1, first_newline - first_equal);
        let area_type = referendum.area.substr(0, first_equal);
        const area_array = referendum.area.split('\n');
        let area_query = '';
        area_array.forEach(function(argument) {
          const eq = argument.indexOf('=');
          let type = argument.substr(0, eq);
          if (type === 'village')
            type = 'city';
          const name = argument.substr(eq + 1);
          if (type)
            area_query += type + '=' + encodeURIComponent(name) + '&';
        });
        area_query = area_query.slice(0, -1);
        let area_url;
        if (!area_type) {
          area_type = 'world';
          area_name = 'Earth';
          area_url = 'https://en.wikipedia.org/wiki/Earth';
        } else if (area_type == 'union')
          area_url = 'https://en.wikipedia.org/wiki/European_Union';
        else {
          area_url = 'https://nominatim.openstreetmap.org/search.php?' + area_query + '&polygon_geojson=1';
          population_url = 'https://nominatim.openstreetmap.org/search.php?' + area_query +
            '&polygon_geojson=1&format=json&extratags=1';
          let xhttp = new XMLHttpRequest();
          xhttp.onload = function() {
            let population = document.getElementById('population');
            if (this.status == 200) {
              const r = JSON.parse(this.responseText);
              if (r.length) {
                const response = r[0];
                if (response.hasOwnProperty('osm_id')) {
                  const url = 'https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=' + response.osm_id;
                  if (response.hasOwnProperty('extratags') && response.extratags.hasOwnProperty('population'))
                    population.innerHTML = `<a target="_blank" href="${url}">${response.extratags.population}</a>`;
                  else
                    population.innerHTML = `<a target="_blank" href="${url}">N/A</a>`;
                  if (response.hasOwnProperty('geojson')) {
                    L.geoJSON(response.geojson).addTo(map);
                    const bb = response.boundingbox;
                    map.fitBounds([[bb[0], bb[2]], [bb[1], bb[3]]]);
                  }
                } else population.innerHTML = '?';
              } else
                population.innerHTML = '?';
            } else
              population.innerHTML = '&times;';
          };
          xhttp.open('GET', population_url, true);
          xhttp.send();
        }
        const answers = referendum.answers.split('\n');
        const answer_count = answers.length;
        let results = [];
        for (i = 0; i < answers.length; i++)
          results.push(referendum.count ? referendum.count[i] : 0);
        const total = results.reduce((a, b) => a + b, 0);
        answers_table = '<table class="table table-bordered"><thead class="thead-light"><tr>';
        const colors = ['primary', 'danger', 'success', 'warning', 'info', 'secondary', 'light', 'dark'];
        const width = Math.round(100 / (answer_count + 2));
        answers.forEach(function(answer) {
          answers_table += '<th width="' + width + '%" scope="col" class="text-center">' + answer + '</th>';
        });
        answers_table += '<th width="' + width +
          '%" scope="col" class="text-center font-italic font-weight-normal" style="color:blue">void</th>' +
          '<th width="' + width +
          '%" scope="col" class="text-center font-italic font-weight-normal" style="color:blue">rejected</th>' +
          '</tr></thead><tbody><tr>';
        let color_count = 0;
        let count = 0;
        answers.forEach(function(answer) {
          const percent = (total == 0) ? 0 : Math.round(10000 * results[count] / total) / 100;
          answers_table +=
            '<td><div class="progress"><div id="answer-percent-' + count + '" ' +
            'class="progress-bar progress-bar-striped bg-' + colors[color_count++] +
            '" role="progressbar" ' +
            'style="width:' + percent + '%" aria-valuemin="0" aria_valuemax="100">' + percent + ' %' +
            '</div></div></td>';
          if (color_count == colors.length)
            color_count = 0;
          count++;
        });
        answers_table += '<td class="text-center">N/A</td><td class="text-center">N/A</td>';
        count = 0;
        answers_table += '</tr><tr>';
        answers.forEach(function(answer) {
          answers_table += '<td class="text-center">' + results[count] + '</td>';
          count++;
        });
        answers_table += '<td class="text-center">' + referendum.void + '</td>';
        answers_table += '<td class="text-center">' + referendum.rejected + '</td>';
        answers_table += '</tr></tbody></table>';
        const participation = (referendum.corpus > 0) ? (Math.round(10000 * referendum.participation / referendum.corpus) /
          100) + '%' : 'N/A';
        document.getElementById('content').innerHTML = '<h2>' + referendum.title + '</h2>' +
          '<div><small><b>Deadline:</b> ' + new Date(referendum.deadline).toLocaleString() +
          ' &mdash; <b>Area:</b> <a target="_blank" href="' + area_url + '">' + area_name +
          '</a> (' + area_type + ')' + '</small></div><br><div><p>' + referendum.description + '</p></div><div><p><b>' +
          referendum.question + '</b><p></div>' + answers_table +
          '<div>estimated population: <span id="population">&hellip;</span> &mdash; corpus: ' + referendum.corpus +
          ' &mdash; participation: <span id="participation">' + referendum.participation + '</span> (' + participation +
          ')</div>';
        setTimeout(updatePosition, 500);
      }
    }
  };
  xhttp.open('POST', '/counting.php', true);
  xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhttp.send('fingerprint=' + fingerprint);
};
