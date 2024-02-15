/* global L, QRious */

import {encodeBase128} from './base128.js';

const PRODUCTION_APP_KEY = // public key of the genuine app
  'vD20QQ18u761ean1+zgqlDFo6H2Emw3mPmBxeU24x4o1M2tcGs+Q7G6xASRf4LmSdO1h67ZN0sy1tasNHH8Ik4CN63elBj4ELU70xZeYXIMxxxDqis' +
  'FgAXQO34lc2EFt+wKs+TNhf8CrDuexeIV5d4YxttwpYT/6Q2wrudTm5wjeK0VIdtXHNU5V01KaxlmoXny2asWIejcAfxHYSKFhzfmkXiVqFrQ5BHAf' +
  '+/ReYnfc+x7Owrm6E0N51vUHSxVyN/TCUoA02h5UsuvMKR4OtklZbsJjerwz+SjV7578H5FTh0E0sa7zYJuHaYqPevvwReXuggEsfytP/j2B3IgarQ';
const TEST_APP_KEY = // public key of the test app
  'nRhEkRo47vT2Zm4Cquzavyh+S/yFksvZh1eV20bcg+YcCfwzNdvPRs+5WiEmE4eujuGPkkXG6u/DlmQXf2szMMUwGCkqJSPi6fa90pQKx81QHY8Ab4' +
  'z69PnvBjt8tt8L8+0NRGOpKkmswzaX4ON3iplBx46yEn00DQ9W2Qzl2EwaIPlYNhkEs24Rt5zQeGUxMGHy1eSR+mR4Ngqp1LXCyGxbXJ8B/B5hV4QI' +
  'or7U2raCVFSy7sNl080xNLuY0kjHCV+HN0h4EaRdR2FSw9vMyw5UJmWpCFHyQla42Eg1Fxwk9IkHhNe/WobOT1Jiy3Uxz9nUeoCQa5AONAXOaO2wtQ';

function findGetParameter(parameterName, result) {
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = async function() {
  let judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  document.getElementById('judge').value = judge.substring(8);
  let fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  const me = findGetParameter('me') === 'true';
  if (!fingerprint && !signature) {
    console.error('Missing fingerprint or signature GET argument.');
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
  document.getElementById('modal-close-button').addEventListener('click', function() {
    document.getElementById('modal').classList.remove('is-active');
  });
  document.querySelector('.modal-background').addEventListener('click', function() {
    document.getElementById('modal').classList.remove('is-active');
  });
  const a = document.createElement('a');
  a.classList.add('level-right');
  a.setAttribute('id', 'delete-link');
  translator.translateElement(a, me ? 'thats-me' : 'review');
  document.getElementById('panel-heading').appendChild(a);
  a.addEventListener('click', function(event) {
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
    translator.translateElement(a, 'copy');
    const message = document.createElement('div');
    div.appendChild(message);
    translator.translateElement(message, 'scan-instructions');
    a.addEventListener('click', function() {
      input.select();
      input.setSelectionRange(0, 99999);
      document.execCommand('copy');
      input.setSelectionRange(0, 0);
      input.blur();
      translator.translateElement(message, 'copied-in-clipboard');
    });
    const content = document.getElementById('modal-content');
    content.innerHTML = '';
    content.appendChild(div);
    document.getElementById('modal').classList.add('is-active');
  });
  if (localStorage.getItem('password')) {
    let a = document.createElement('a');
    a.setAttribute('id', 'logout');
    a.textContent = 'logout';
    document.getElementById('logout-div').appendChild(a);
    document.getElementById('logout').addEventListener('click', function(event) {
      document.getElementById('logout-div').textContent = '';
      localStorage.removeItem('password');
    });
    a = document.createElement('a');
    a.classList.add('level-right');
    a.setAttribute('id', 'delete-link');
    a.textContent = 'Delete';
    a.addEventListener('click', function(event) {
      const h = localStorage.getItem('password');
      fetch('/api/developer/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'password=' + h + '&type=citizen&signature=' + encodeURIComponent(signature)
      }).then(response => response.text())
        .then(response => {
          if (response === 'OK')
            window.location.replace('https://notary.directdemocracy.vote');
          else
            console.error(response);
        });
    });
    document.getElementById('panel-heading').appendChild(a);
  }
  const body = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${encodeURIComponent(fingerprint)}`;
  fetch('api/citizen.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      const published = publishedDate(answer.citizen.published);
      const givenNames = answer.citizen.givenNames;
      const familyName = answer.citizen.familyName;
      const latitude = answer.citizen.latitude;
      const longitude = answer.citizen.longitude;
      document.getElementById('picture').src = answer.citizen.picture;
      if (answer.citizen.appKey !== PRODUCTION_APP_KEY) {
        document.getElementById('picture-overlay').style.visibility = '';
        if (answer.citizen.appKey !== TEST_APP_KEY) {
          document.getElementById('picture-overlay').textContent = 'ERROR';
          console.error(answer.citizen.appKey + " !== " + TEST_APP_KEY);
        }
      }
      document.getElementById('given-names').textContent = givenNames;
      document.getElementById('family-name').textContent = familyName;
      document.getElementById('home').innerHTML = `<a href="https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}&zoom=12" target="_blank">${latitude}, ${longitude}</a>`;
      document.getElementById('created').textContent = published;
      const map = L.map('map');
      map.whenReady(function() { setTimeout(() => { this.invalidateSize(); }, 0); });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
      let marker = L.marker([latitude, longitude]).addTo(map);
      marker.bindPopup(`<b>${givenNames} ${familyName}</b><br>[${latitude}, ${longitude}]`);
      map.setView([latitude, longitude], 18);
      map.on('contextmenu', function(event) { return false; });
      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=20`)
        .then(response => response.json())
        .then(answer => {
          const address = answer.display_name;
          marker.setPopupContent(`<b>${givenNames} ${familyName}</b><br>${address}`).openPopup();
        });
      document.getElementById('reload').addEventListener('click', function(event) {
        event.currentTarget.setAttribute('disabled', '');
        event.currentTarget.classList.add('is-loading');
        judge = 'https://' + document.getElementById('judge').value;
        const reputation = document.getElementById('reputation');
        reputation.innerHTML = '...';
        reputation.style.color = 'black';
        loadReputation();
        updateJudgeCertificates();
      });

      function loadReputation() {
        fetch(`${judge}/api/reputation.php?key=${encodeURIComponent(answer.citizen.key)}`)
          .then((response) => {
            if (document.getElementById('judge-certificates').innerHTML !== '<b>...</b>')
              enableJudgeReloadButton();
            return response.json();
          })
          .then((answer) => {
            const reputation = document.getElementById('reputation');
            if (answer.error) {
              reputation.style.color = 'red';
              reputation.textContent = answer.error;
            } else {
              reputation.style.color = answer.trusted ? 'green' : 'red';
              reputation.textContent = `${answer.reputation}`;
            }
          });
      }

      loadReputation();

      function updateJudgeCertificates() {
        let div = document.getElementById('judge-certificates');
        div.innerHTML = '<b>...</b>';
        const payload = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
        fetch(`/api/trusts.php?${payload}&judge=${judge}`)
          .then(response => {
            const reputation = document.getElementById('reputation');
            if (reputation.textContent !== '..')
              enableJudgeReloadButton();
            return response.json();
          })
          .then(answer => {
            if (answer.error) {
              console.error(answer.error);
              return;
            }
            div.innerHTML = '';
            for (const endorsement of answer.endorsements) {
              const block = document.createElement('div');
              div.appendChild(block);
              const d = new Date(parseInt(endorsement.published * 1000));
              const latest = parseInt(endorsement.latest) === 1;
              const color = endorsement.revoke ? 'red' : 'green';
              const icon = endorsement.revoke ? 'xmark_seal_fill' : 'checkmark_seal_fill';
              const p = document.createElement('p');
              block.appendChild(p);
              p.style.width = "100%";
              const i = document.createElement('i');
              p.appendChild(i);
              i.classList.add('icon', 'f7-icons', 'margin-right');
              i.style.color = color;
              i.style.fontSize = '110%';
              i.textContent = icon;
              let span = document.createElement('span');
              p.appendChild(span);
              if (latest)
                span.style.fontWeight = 'bold';
              translator.translateElement(span, endorsement.revoke ? 'distrusted' : 'trusted');
              p.appendChild(document.createTextNode(' '));
              span = document.createElement('span');
              p.appendChild(span);
              translator.translateElement(span, 'on');
              p.appendChild(document.createTextNode(` ${d.toLocaleString()}`));
            }
          });
      }

      updateJudgeCertificates();

      function enableJudgeReloadButton() {
        let button = document.getElementById('reload');
        button.removeAttribute('disabled');
        button.classList.remove('is-loading');
      }

      function deg2rad(deg) {
        return deg * Math.PI / 180;
      }

      function distanceFromLatitudeLongitude(lat1, lon1, lat2, lon2) {
        const R = 6370986; // Radius of the Earth in m
        const dLat = deg2rad(lat2 - lat1);
        const dLon = deg2rad(lon2 - lon1);
        const a =
          Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
          Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const d = R * c;
        return d;
      }

      function addEndorsement(endorsement) {
        const columns = document.getElementById('endorsements');
        const column = document.createElement('div');
        columns.appendChild(column);
        column.style.overflow = 'hidden';
        const container = document.createElement('div');
        column.appendChild(container);
        container.classList.add('picture-container');
        const img = document.createElement('img');
        container.appendChild(img);
        img.src = endorsement.picture;
        img.style.float = 'left';
        img.style.marginRight = '10px';
        img.style.marginBottom = '10px';
        img.style.width = '100px';
        const overlay = document.createElement('div');
        overlay.classList.add('picture-overlay');
        if (endorsement.appKey === PRODUCTION_APP_KEY)
          overlay.style.visibility = 'hidden';
        else if (endorsement.appKey === TEST_APP_KEY)
          translator.translateElement(overlay, 'test');
        else {
          translator.translateElement(overlay, 'error');
          console.error('endorsement.appKey = ' + endorsement.appKey);
          console.error('TEST_APP_KEY    = ' + TEST_APP_KEY);
        }
        container.appendChild(overlay);
        const div = document.createElement('div');
        column.appendChild(div);
        div.classList.add('media-content');
        const content = document.createElement('div');
        div.appendChild(content);
        content.style.minWidth = '250px';        
        const distance = Math.round(distanceFromLatitudeLongitude(latitude, longitude, endorsement.latitude, endorsement.longitude));
        let icon;
        let day;
        let color;
        let otherIcon;
        let otherDay;
        let otherColor;
        if (endorsement.hasOwnProperty('endorsed') && endorsement.hasOwnProperty('endorsedYou')) {
          day = new Date(endorsement.endorsed * 1000).toISOString().slice(0, 10);
          otherDay = new Date(endorsement.endorsedYou * 1000).toISOString().slice(0, 10);
          if (day === otherDay) {
            icon = 'arrow_right_arrow_left';
            color = 'green';
            otherDay = false;
          } else {
            icon = 'arrow_left';
            color = 'green';
            otherIcon = 'arrow_right';
            otherColor = 'green';
          }
        } else {
          if (endorsement.hasOwnProperty('reported')) {
            icon = 'xmark';
            color = 'red';
            day = new Date(endorsement.reported * 1000).toISOString().slice(0, 10);
          } else if (endorsement.hasOwnProperty('endorsed')) {
            icon = 'arrow_left';
            color = 'green';
            day = new Date(endorsement.endorsed * 1000).toISOString().slice(0, 10);
          } else
            day = false;
          if (endorsement.hasOwnProperty('reportedYou')) {
            otherIcon = 'xmark';
            otherColor = 'red';
            otherDay = new Date(endorsement.reportedYou * 1000).toISOString().slice(0, 10);
          } else if (endorsement.hasOwnProperty('endorsedYou')) {
            otherIcon = 'arrow_right';
            otherColor = 'green';
            otherDay = new Date(endorsement.endorsedYou * 1000).toISOString().slice(0, 10);
          } else
            otherDay = false;
        }
        let dates = '';
        if (day) {
          dates += `<i class="icon f7-icons" style="font-size:150%;font-weight:bold;color:${color}">${icon}</i> ${day}`;
          if (otherDay)
            dates += '<br>';
        }
        if (otherDay)
          dates += `<i class="icon f7-icons" style="font-size:150%;font-weight:bold;color:${otherColor}">${otherIcon}</i> ${otherDay}`;
        const a = document.createElement('a');
        content.appendChild(a);
        a.href = `/citizen.html?signature=${encodeURIComponent(endorsement.signature)}`;
        a.innerHTML = `<b>${endorsement.givenNames}<br>${endorsement.familyName}</b>`;
        content.appendChild(document.createElement('br'));
        const small = document.createElement('small');
        content.appendChild(small);
        let span = document.createElement('span');
        small.appendChild(span);
        translator.translateElement(span, 'distance');
        small.appendChild(document.createTextNode(` ${distance} m.`));
        small.appendChild(document.createElement('br'));
        span = document.createElement('span');
        small.appendChild(span);
        span.innerHTML = dates;
      }

      function publishedDate(seconds) {
        return new Date(seconds * 1000).toISOString().slice(0, 10);
      }

      let count = 0;
      let total = 0;
      answer.endorsements.forEach(function(endorsement) {
        if (endorsement.hasOwnProperty('endorsed'))
          count++;
        total++;
      });
      document.getElementById('endorsements-header').textContent = `Endorsed by ${count}/${total}`;
      answer.endorsements.forEach(function(endorsement) {
        addEndorsement(endorsement);
      });
    });
};
