function findGetParameter(parameterName) {
  let result;
  location.search.substr(1).split("&").forEach(function(item) {
    const tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  let fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  if (!fingerprint && ! signature) {
    console.error('Missing fingerprint or signature GET argument');
    return;
  }
  if (!fingerprint)
    fingerprint = CryptoJS.SHA1(CryptoJS.enc.Base64.parse(signature)).toString();

  const payload = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
  fetch(`/api/proposal.php?${payload}`)
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(`Cannot get petition: ${answer.error}`);
        return;
      }
      document.getElementById('title').innerHTML = answer.title;
      document.getElementById('description').innerHTML = answer.description;
      if (answer.secret) {
        document.getElementById('question-block').style.display = '';
        document.getElementById('question').textContent = answer.question;
        document.getElementById('answers-block').style.display = '';
        document.getElementById('answers').textContent = answer.answers.join(' / ');
      }
      const deadline = new Date(answer.deadline * 1000);
      const published = new Date(answer.published * 1000);
      const now = new Date();
      document.getElementById('deadline').innerHTML = `<span style="color:#${ deadline < now ? 'a00' : '0a0'}">${deadline.toLocaleString()}</span>`;
      document.getElementById('published').textContent = published.toLocaleString();
      document.getElementById('judge').innerHTML = `<a target="_blank" href="${answer.judge}">${answer.judge}</a>`;
      document.querySelector('.subtitle').textContent = (answer.secret) ? 'referendum' : 'petition';
      document.getElementById('modal-title').textContent = (answer.secret) ? 'Vote at this referendum' : 'Sign this petition';
      const actionButton = document.getElementById('action-button');
      actionButton.textContent = (answer.secret) ? 'Vote' : 'Sign';
      if (deadline < now)
        actionButton.textContent = 'Closed';
      else
        actionButton.removeAttribute('disabled');
      const corpus = answer.corpus;
      const participants = answer.secret ? 'Voters' : 'Signatures';
      const participation = corpus == 0 ? 0 : Math.round(10000 * answer.participants / corpus) / 100;
      const line = `Corpus: <a target="_blank" href="participants.html?${payload}&corpus=1">` +
                   `${corpus}</a> &mdash; ` +
                   `${participants}: <a target="_blank" href="participants.html?${payload}">` +
                   `${answer.participants}</a> &mdash; ` +
                   `Participation: ${participation}%`;
      document.getElementById('result').innerHTML = line;
      areaName = document.getElementById('area-name');
      const areas = answer.areas[0].split('=');
      let query = '';
      answer.areas.forEach(function(line) {
        const eq = line.indexOf('=');
        let type = line.substr(0, eq);
        if (['village', 'town', 'municipality'].includes(type))
          type = 'city';
        const name = line.substr(eq + 1);
        if (type)
          query += type + '=' + encodeURIComponent(name) + '&';
      });
      areaName.innerHtml = `Area: ${areas[1]}`;
      query = query.slice(0, -1);
      if (!areas[0])
        areaName.innerHTML = `Area: <a target="_blank" href="https://en.wikipedia.org/wiki/Earth">Earth</a>`;
      else if (areas[0] == 'union')
        areaName.innerHTML = `Area: <a target="_blank" href="https://en.wikipedia.org/wiki/European_Union">European Union</a>`;
      else {
        fetch(`https://nominatim.openstreetmap.org/search.php?${query}&format=json&extratags=1`)
          .then(response => response.json())
          .then(answer => {
            if (answer.length) {
              const response = answer[0];
              if (response.hasOwnProperty('osm_id')) {
                const url = 'https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=' + response.osm_id;
                if (response.hasOwnProperty('extratags') && response.extratags.hasOwnProperty('population')) {
                  const corpus_percent = Math.round(10000 * corpus / parseFloat(response.extratags.population)) / 100;
                  population = `<a target="_blank" href="${url}">${response.extratags.population}</a> with a ` +
                               `<a target="_blank" href="participants.html?${payload}&corpus=1">` +
                               `corpus</a> of ${corpus_percent}%`;
                } else
                  population = `<a target="_blank" href="${url}">N/A</a>`;
                areaName.innerHtml = `Area: ${areas[1]} (estimated population: ${population})`;
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
  document.getElementById('modal-close-button').addEventListener('click', closeModal);
  document.getElementById('action-button').addEventListener('click', function() {
    let binaryFingerprint = '';
    for(let i = 0; i < 40; i+=2)
      binaryFingerprint += String.fromCharCode(parseInt(fingerprint.slice(i, i + 2), 16));
    const qr = new QRious({
      value: binaryFingerprint,
      level: 'L',
      size: 512,
      padding: 0
    });
    const div = document.createElement('div');
    const img = document.createElement('img');
    div.appendChild(img);
    img.src = qr.toDataURL();
    div.classList.add('content', 'has-text-centered');
    const field = document.createElement('div');
    div.appendChild(field);
    field.classList.add('field', 'has-addons');
    let control = document.createElement('div');
    field.appendChild(control);
    control.classList.add('control');
    control.style.width = '100%';
    const input = document.createElement('input');
    control.appendChild(input);
    input.classList.add('input');
    input.setAttribute('readonly', '');
    input.setAttribute('value', fingerprint);
    control = document.createElement('div');
    field.appendChild(control);
    const a = document.createElement('a');
    control.appendChild(a);
    a.classList.add('button', 'is-info');
    a.textContent = 'Copy';
    const message = document.createElement('div');
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
    const content = document.getElementById('modal-content');
    content.innerHTML = '';
    content.appendChild(div);
    document.getElementById('modal').classList.add('is-active');
  });
};
