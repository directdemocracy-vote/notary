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

    fetch(`/private/proposal.php?fingerprint=${fingerprint}`)
      .then((response) => response.json())
      .then((answer) => {
        if (answer.error) {
          console.log(`Cannot get petition: ${answer.error}`);
          return;
        }
        document.getElementById('title').innerHTML = answer.title;
        document.getElementById('description').innerHTML = answer.description;
        const deadline = new Date(answer.deadline);
        const now = new Date();
        document.getElementById('deadline').innerHTML = `<span style="color:#${ deadline < now ? 'a00' : '0a0'}">${deadline.toLocaleString()}</span>`;
        document.getElementById('judge').innerHTML = `<a href="${answer.judge}" target="_blank">${answer.judge}</a>`;
        let signButton = document.getElementById('sign-button');
        if (deadline < now)
          signButton.innerHTML = 'Closed';
        else
          signButton.removeAttribute('disabled');

        areaName = document.getElementById('area-name');
        const first_equal = answer.name.indexOf('=');
        const first_newline = answer.name.indexOf('\n');
        let area_name = answer.name.substr(first_equal + 1, first_newline - first_equal);
        let area_type = answer.name.substr(0, first_equal);
        let area_query = '';
        answer.name.forEach(function(line) {
          const eq = line.indexOf('=');
          let type = line.substr(0, eq);
          if (['village', 'town', 'municipality'].includes(type))
            type = 'city';
          const name = line.substr(eq + 1);
          if (type)
            area_query += type + '=' + encodeURIComponent(name) + '&';
        });
        areaName.innerHTML = `Area: ${area_name}`;
        area_query = area_query.slice(0, -1);
        let area_url;
        if (!area_type)
          areaName.innerHTML = `Area: <a href="https://en.wikipedia.org/wiki/Earth" target="_blank">Earth</a>`;
        else if (area_type == 'union')
          areaName.innerHTML = `Area: <a href="https://en.wikipedia.org/wiki/European_Union" target="_blank">European Union</a>`;
        else {
          area_url = `https://nominatim.openstreetmap.org/search.php?${area_query}&polygon_geojson=1`;
          fetch(`https://nominatim.openstreetmap.org/search.php?${area_query}&format=json&extratags=1`)
            .then((response) => response.json())
            .then((answer) => {
              if (answer.length) {
                const response = answer[0];
                if (response.hasOwnProperty('osm_id')) {
                  const url = 'https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=' + response.osm_id;
                  if (response.hasOwnProperty('extratags') && response.extratags.hasOwnProperty('population'))
                    population = `<a target="_blank" href="${url}">${response.extratags.population}</a>`;
                  else
                    population = `<a target="_blank" href="${url}">N/A</a>`;
                  areaName.innerHTML = `Area: ${area_name} (estimated population: ${population})`;
                }
              }
            });
        }

        let map = L.map('area');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
        map.on('contextmenu', function(event) { return false; });
        L.geoJSON({type: 'MultiPolygon', coordinates: answer.polygons}).addTo(map);      
        let maxLon = -1000;
        let minLon = 1000;
        let maxLat = -1000;
        let minLat = 1000;
        answer.polygons.forEach(function(polygons) {
          polygons.forEach(function(polygon) {
            polygon.forEach(function(point) {
              if (point[0] > maxLon)
                maxLon = point[0];
              else if (point[0] < minLon)
                minLon = point[0];
              if (point[1] > maxLat)
                maxLat = point[1];
              else if (point[1] < minLat)
                minLat = point[1];
            });
          });
        });
        map.fitBounds([[minLat, minLon], [maxLat, maxLon]]);

      });


    function closeModal() {
      document.getElementById('modal').classList.remove('is-active');
    }
    document.getElementById('modal-cancel-button').addEventListener('click', closeModal);
    document.getElementById('modal-close-button').addEventListener('click', closeModal);
    document.getElementById('modal-ok-button').addEventListener('click', closeModal);
    document.getElementById('sign-button').addEventListener('click', function() {
    let binaryFingerprint = '';
      for(let i = 0; i < 40; i+=2)
        binaryFingerprint += String.fromCharCode(parseInt(fingerprint.slice(i, i + 2), 16));  
      const qr = new QRious({
        value: binaryFingerprint,
        level: 'L',
        size: 512,
        padding: 0
      });
      let div = document.createElement('div');
      let img = document.createElement('img');
      div.appendChild(img);
      img.src = qr.toDataURL();
      div.classList.add('content', 'has-text-centered');
      let field = document.createElement('div');
      div.appendChild(field);
      field.classList.add('field', 'has-addons');
      let control = document.createElement('div');
      field.appendChild(control);
      control.classList.add('control');
      control.style.width = '100%';
      let input = document.createElement('input');
      control.appendChild(input);
      input.classList.add('input');
      input.setAttribute('readonly', '');
      input.setAttribute('value', fingerprint);
      control = document.createElement('div');
      field.appendChild(control);
      let a = document.createElement('a');
      control.appendChild(a);
      a.classList.add('button', 'is-info');
      a.innerHTML = 'Copy';
      let message = document.createElement('div');
      div.appendChild(message);
      message.innerHTML = 'From the <i>directdemocracy</i> app, scan this QR code or copy and paste it.';
      a.addEventListener('click', function() {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand("copy");
        input.setSelectionRange(0, 0);
        input.blur();
        message.innerHTML = 'Copied in clipboard! You can now paste in the <i>directdemocracy</i> app.';
      });
      document.getElementById('modal-title').innerHTML = 'Sign this petition';
      let content = document.getElementById('modal-content');
      content.innerHTML = '';
      content.appendChild(div);
      document.getElementById('modal-footer').style.display = 'none';
      document.getElementById('modal').classList.add('is-active');
    });
}
