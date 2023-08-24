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

    fetch(`/api/publication.php?fingerprint=b43dc844ce9fab3a669a2c40875a9cee97ffdb76`)
      .then((response) => response.json())
      .then((answer) => {
        if (answer.error) {
          console.log(`Cannot get petition: ${answer.error}`);
          return;
        }
        document.getElementById('title').innerHTML = answer.title;
        document.getElementById('description').innerHTML = answer.description;
        document.getElementById('deadline').innerHTML = new Date(answer.deadline).toLocaleString();
        document.getElementById('judge').innerHTML = `<a href="${answer.judge}" target="_blank">${answer.judge}</a>`;
        fetch(`/api/publication.php?fingerprint=${CryptoJS.SHA1(answer.area).toString()}`)
          .then((response) => response.json())
          .then((area) => {
            if (area.error) {
              console.log(`cannot get area: ${area.error}`);
              return;
            }
            document.getElementById('area-name').innerHTML = 'Area: ' + area.name.split("\n")[0].split('=')[1];
            let map = L.map('area');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
              attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);
            map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
            map.on('contextmenu', function(event) { return false; });
            L.geoJSON({type: 'MultiPolygon', coordinates: area.polygons}).addTo(map);      
            let maxLon = -1000;
            let minLon = 1000;
            let maxLat = -1000;
            let minLat = 1000;
            area.polygons.forEach(function(polygons) {
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
      });


    function closeModal() {
      document.getElementById('modal').classList.remove('is-active');
    }
    document.getElementById('modal-cancel-button').addEventListener('click', closeModal);
    document.getElementById('modal-close-button').addEventListener('click', closeModal);
    document.getElementById('modal-ok-button').addEventListener('click', closeModal);
    document.getElementById('sign-button').addEventListener('click', function() {
      const qr = new QRious({
        value: fingerprint,
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
