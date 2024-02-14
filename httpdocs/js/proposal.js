/* global L, QRious */
import {encodeBase128} from './base128.js';

function findGetParameter(parameterName) {
  let result;
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = async function() {
  if (localStorage.getItem('password')) {
    let a = document.createElement('a');
    a.setAttribute('id', 'logout');
    a.textContent = 'logout';
    document.getElementById('logout-div').appendChild(a);
    document.getElementById('logout').addEventListener('click', function(event) {
      document.getElementById('logout-div').textContent = '';
      localStorage.removeItem('password');
      document.getElementById('panel-heading').removeChild(document.getElementById('delete-link'));
    });
    a = document.createElement('a');
    a.classList.add('level-right');
    a.setAttribute('id', 'delete-link');
    translator.translateElement(a, 'delete');
    a.addEventListener('click', function(event) {
      const h = localStorage.getItem('password');
      fetch('/api/developer/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + h + '&type=proposal&signature=' + encodeURIComponent(signature)
      })
        .then(response => response.text())
        .then(response => {
          if (response === 'OK')
            window.location.replace('https://notary.directdemocracy.vote');
          else
            console.error('Cannot delete proposal: ' + response);
        });
    });
    document.getElementById('panel-heading').appendChild(a);
  }
  let fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  if (!fingerprint && !signature) {
    console.error('Missing fingerprint or signature GET argument');
    return;
  }
  if (!fingerprint) {
    const binaryString = atob(signature);
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++)
      bytes[i] = binaryString.charCodeAt(i);
    const bytesArray = await crypto.subtle.digest('SHA-1', bytes);
    fingerprint = Array.from(new Uint8Array(bytesArray), byte => ('0' + (byte & 0xFF).toString(16)).slice(-2)).join('');
  }
  const payload = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
  fetch(`/api/proposal.php?${payload}`)
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(`Cannot get petition: ${answer.error}`);
        return;
      }
      document.getElementById('title').textContent = answer.title;
      document.getElementById('description').textContent = answer.description;
      let total = 0;
      if (answer.secret) {
        document.getElementById('question-block').style.display = '';
        document.getElementById('question').textContent = answer.question;
        document.getElementById('answers-block').style.display = '';
        const table = document.createElement('table');
        table.classList.add('table');
        document.getElementById('answers').appendChild(table);
        const length = answer.answers.length + 1;
        let max = 1;
        for(let i = 0; i < length; i++) {
          total += answer.results[i];
          if (i > 0 && answer.results[i] > answer.results[max])
            max = i;
        }
        let expressed = 0;
        for(let i = 1; i < length; i++)
          expressed += answer.results[i];
        for(let i = 1; i < length; i++) {
          const tr = document.createElement('tr');
          table.appendChild(tr);
          if (i === max)
            tr.style.fontWeight = 'bold';
          tr.style.backgroundColor = i === max ? 'lightgreen' : 'lightpink';
          let td = document.createElement('td');
          tr.appendChild(td);
          td.textContent = answer.answers[i - 1];
          td = document.createElement('td');
          tr.appendChild(td);
          td.textContent = answer.results[i];
          td = document.createElement('td');
          tr.appendChild(td);
          td.textContent = expressed ? (Math.floor(10000 * answer.results[i] / expressed) / 100) + '%' : 'N/A';
        }
        let tr = document.createElement('tr');
        table.appendChild(tr);
        tr.style.backgroundColor = 'lightgrey';
        let td = document.createElement('td');
        tr.appendChild(td);
        td.style.fontStyle = 'italic';
        translator.translateElement(td, 'blank');
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = answer.results[0];
        td = document.createElement('td');
        tr.appendChild(td);
      }
      const deadline = new Date(answer.deadline * 1000);
      const published = new Date(answer.published * 1000);
      const now = new Date();
      document.getElementById('deadline').innerHTML = `<span style="color:#${deadline < now ? 'a00' : '0a0'}">` +
        `${deadline.toLocaleString()}</span>`;
      document.getElementById('published').textContent = published.toLocaleString();
      const a = document.createElement('a');
      a.setAttribute('target', '_blank');
      a.setAttribute('href', answer.judge);
      a.textContent = answer.judge;
      document.getElementById('judge').appendChild(a);
      document.querySelector('.subtitle').textContent = (answer.secret) ? 'referendum' : 'petition';
      translator.translateElement(document.getElementById('modal-title'), answer.secret ? 'vote-at' : 'sign-this');
      const actionButton = document.getElementById('action-button');
      if (deadline < now)
        translator.translateElement(actionButton, 'closed');
      else {
        translator.translateElement(actionButton, answer.secret ? 'vote' : 'sign');
        actionButton.removeAttribute('disabled');
      }
      const corpus = document.getElementById('corpus');
      corpus.textContent = answer.corpus;
      corpus.href = `participants.html?${payload}&corpus=1`;
      corpus.target = '_blank';
      translator.translateElement(document.getElementById('signers-or-voters-label'), answer.secret ? 'voters:' : 'signers:');
      const sv = document.getElementById('signers-or-voters');
      sv.textContent = answer.secret ? total : answer.participants;
      sv.href = `participants.html?${payload}`;
      sv.target = '_blank';
      document.getElementById('participation').textContent = answer.corpus === 0 ? 'N/A' : (Math.round(10000 * answer.participants / answer.corpus) / 100) + '%';
      const areas = document.getElementById('areas');
      areas.textContent = answer.areas ? answer.areas : 'undefined';
      areas.href = `areas.html?${payload}`;
      areas.target = '_blank';
      const areaName = document.getElementById('area-name');
      const areaNames = answer.areaName[0].split('=');
      let query = '';
      answer.areaName.forEach(function(line) {
        const eq = line.indexOf('=');
        let type = line.substr(0, eq);
        if (['village', 'town', 'municipality'].includes(type))
          type = 'city';
        const name = line.substr(eq + 1);
        if (type)
          query += type + '=' + encodeURIComponent(name) + '&';
      });
      areaName.textContent = `Area: ${areaNames[1]}`;
      query = query.slice(0, -1);
      if (!areaNames[0])
        areaName.innerHTML = `Area: <a target="_blank" href="https://en.wikipedia.org/wiki/Earth">Earth</a>`;
      else if (areaNames[0] === 'union')
        areaName.innerHTML = `Area: <a target="_blank" href="https://en.wikipedia.org/wiki/European_Union">European Union</a>`;
      else {
        fetch(`https://nominatim.openstreetmap.org/search.php?${query}&format=json&extratags=1`)
          .then(response => response.json())
          .then(answer => {
            if (answer.length) {
              const response = answer[0];
              if (response.hasOwnProperty('osm_id')) {
                const url = 'https://nominatim.openstreetmap.org/ui/details.html?osmtype=R&osmid=' + response.osm_id;
                let population;
                if (response.hasOwnProperty('extratags') && response.extratags.hasOwnProperty('population')) {
                  const corpusPercent = Math.round(10000 * corpus / parseFloat(response.extratags.population)) / 100;
                  population = `<a target="_blank" href="${url}">${response.extratags.population}</a> with a ` +
                    `<a target="_blank" href="participants.html?${payload}&corpus=1">` +
                    `corpus</a> of ${corpusPercent}%`;
                } else
                  population = `<a target="_blank" href="${url}">N/A</a>`;
                areaName.innerHTML = `Area: ${areaNames[1]} (estimated population: ${population})`;
              }
            }
          });
      }
      let map = L.map('area');
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      map.whenReady(function() { setTimeout(() => { this.invalidateSize(); }, 0); });
      map.on('contextmenu', function(event) { return false; });
      L.geoJSON({ type: 'MultiPolygon', coordinates: answer.areaPolygons }).addTo(map);
      let maxLon = -1000;
      let minLon = 1000;
      let maxLat = -1000;
      let minLat = 1000;
      answer.areaPolygons.forEach(function(polygons) {
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
  document.getElementById('modal-close-button').addEventListener('click', function() {
    document.getElementById('modal').classList.remove('is-active');
  });
  document.querySelector('.modal-background').addEventListener('click', function() {
    document.getElementById('modal').classList.remove('is-active');
  });
  document.getElementById('action-button').addEventListener('click', function() {
    let binaryFingerprint = new Uint8Array(20);
    for (let i = 0; i < 20; i += 1)
      binaryFingerprint[i] = parseInt(fingerprint.slice(2 * i, 2 * i + 2), 16);
    const qr = new QRious({
      value: encodeBase128(binaryFingerprint),
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
      document.execCommand('copy');
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
