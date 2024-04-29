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

function formatReputation(reputation) {
  if (reputation !== 'N/A') {
    const percent = Math.round(100 * parseFloat(reputation));
    if (percent >= 0 && percent <= 100)
      return percent + '%';
  }
  return 'N/A';
}

function getCommuneName(address) {
  const order = ['village', 'suburb', 'borough', 'town', 'municipality', 'city_district',
    'subdivision', 'city', 'district', 'county'];
  for (const a of order) {
    if (address.hasOwnProperty(a)) {
      if (address.country_code === 'fr' && a === 'suburb' && address['suburb'].indexOf(address['city']) === -1)
        return address['city'] + ' ' + address['suburb'];
      else
        return address[a];
    }
  }
  return 'Unknown';
}

window.onload = async function() {
  let judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  document.getElementById('judge').value = judge.substring(8);
  let fingerprint = findGetParameter('fingerprint');
  let signature = findGetParameter('signature');
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
  translator.translateElement(document.getElementById('modal-title'), me ? 'thats-me' : 'review');
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
    const message = document.createElement('div');
    div.appendChild(message);
    message.classList.add('mb-4');
    translator.translateElement(message, 'scan-instructions');
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
      if (!signature)
        signature = answer.citizen.signature;
      if (answer.status !== 'active') {
        if (answer.status === 'deleted') {
          document.getElementById('status-span').style.display = '';
          translator.translateElement(document.getElementById('status'), answer.status);
        } else { // transferred or updated
          document.getElementById('status-link').style.display = '';
          const a = document.getElementById('status-a');
          translator.translateElement(a, answer.status);
          a.setAttribute('href', '/citizen.html?signature=' + encodeURIComponent(answer.new));
        }
      }
      const published = publishedDate(answer.citizen.published);
      const givenNames = answer.citizen.givenNames;
      const familyName = answer.citizen.familyName;
      const commune = answer.citizen.commune;
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
      document.getElementById('created').textContent = published;
      const communeElement = document.getElementById('commune');
      communeElement.textContent = '...';
      communeElement.href = `https://openstreetmap.org/relation/${commune}`;
      fetch(`https://nominatim.openstreetmap.org/lookup?osm_ids=R${commune}&accept-language=${translator.language}&format=json`)
        .then(response => response.json())
        .then(answer => {
          console.log(answer);
          document.getElementById('commune').textContent = getCommuneName(answer[0].display_name);
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
              reputation.style.color = 'Red';
              reputation.textContent = answer.error;
            } else {
              reputation.style.color = answer.trusted === 1 ? 'Green' : (answer.trusted === 0 ? 'Red' : 'OrangeRed');
              reputation.textContent = formatReputation(answer.reputation);
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
              const color = endorsement.type !== 'trust' ? 'Red' : 'Green';
              const icon = endorsement.type !== 'trust' ? 'xmark_seal_fill' : 'checkmark_seal_fill';
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
              translator.translateElement(span, endorsement.type + 'ed');
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
        // FIXME: const distance = Math.round(distanceFromLatitudeLongitude(latitude, longitude, endorsement.latitude, endorsement.longitude));
        const distance = 0;
        // copied from app.js
        let icon;
        let day;
        let color;
        let comment;
        let otherIcon;
        let otherDay;
        let otherColor;
        let otherComment;
        if (endorsement.hasOwnProperty('endorsed')) {
          day = new Date(endorsement.endorsed * 1000).toISOString().slice(0, 10);
          color = endorsement.endorsedComment === 'in-person' ? 'Green' : 'Blue';
          icon = 'arrow_left';
          comment = endorsement.endorsedComment;
        } else if (endorsement.hasOwnProperty('revoked')) {
          day = new Date(endorsement.revoked * 1000).toISOString().slice(0, 10);
          color = 'Red';
          icon = 'arrow_left';
          comment = endorsement.revokedComment;
        } else
          day = false;
        if (endorsement.hasOwnProperty('endorsedYou')) {
          otherDay = new Date(endorsement.endorsedYou * 1000).toISOString().slice(0, 10);
          otherColor = endorsement.endorsedYouComment === 'in-person' ? 'Green' : 'Blue';
          otherIcon = 'arrow_right';
          otherComment = endorsement.endorsedYouComment;
        } else if (endorsement.hasOwnProperty('revokedYou')) {
          otherDay = new Date(endorsement.revokedYou * 1000).toISOString().slice(0, 10);
          otherColor = 'Red';
          otherIcon = 'arrow_right';
          otherComment = endorsement.revokedYouComment;
        } else
          otherDay = false;
        if (day !== false || otherDay !== false) {
          if (day === otherDay && color === otherColor && comment === otherComment) {
            otherDay = false;
            icon = 'arrow_right_arrow_left';
          }
        }
        if (otherComment === 'remote')
          otherComment = 'endorsed-you-remotely';
        else if (otherComment === 'in-person')
          otherComment = 'endorsed-you-in-person';
        else if (otherComment === 'revoked+commune')
          otherComment = 'revoked-moved';
        else if (otherComment === 'revoked+name')
          otherComment = 'revoked-name';
        else if (otherComment === 'revoked+picture')
          otherComment = 'revoked-picture';
        else if (otherComment === 'revoked+commune+name')
          otherComment = 'revoked-commune-name';
        else if (otherComment === 'revoked+commune+picture')
          otherComment = 'revoked-commune-picture';
        else if (otherComment === 'revoked+name+picture')
          otherComment = 'revoked-name-picture';
        else if (otherComment === 'revoked+commune+name+picture')
          otherComment = 'revoked-commune-name-picture';
        else if (otherComment === 'revoked+died')
          otherComment = 'revoked-died';
        else if (otherComment)
          console.error('Unsupported other comment: ' + otherComment);
        if (comment === 'remote')
          comment = 'you-endorsed-remotely';
        else if (comment === 'in-person')
          comment = 'you-endorsed-in-person';
        else if (comment === 'revoked+commune')
          comment = 'you-revoked-moved';
        else if (comment === 'revoked+name')
          comment = 'you-revoked-name';
        else if (comment === 'revoked+picture')
          comment = 'you-revoked-picture';
        else if (comment === 'revoked+commune+name')
          comment = 'you-revoked-commune-name';
        else if (comment === 'revoked+commune+picture')
          comment = 'you-revoked-commune-picture';
        else if (comment === 'revoked+name+picture')
          comment = 'you-revoked-name-picture';
        else if (comment === 'revoked+commune+name+picture')
          comment = 'you-revoked-commune-name-picture';
        else if (comment === 'revoked+died')
          comment = 'you-revoked-died';
        else if (comment)
          console.error('Unsupported comment: ' + comment);
        let other = otherDay
          ? `<i class="icon f7-icons" style="font-size:150%;font-weight:bold;color:${otherColor}">${otherIcon}</i> ${otherDay}` +
          `${day ? ' ' : ''}`
          : '';
        let main = day
          ? `<i class="icon f7-icons" style="font-size:150%;font-weight:bold;color:${color}">${icon}</i> ${day}`
          : '';
        // end of copy
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
        if (other !== '') {
          span = document.createElement('span');
          const c = comment ? translator.translate(comment, [endorsement.givenNames, endorsement.familyName]) : '';
          const oc = otherComment ? translator.translate(otherComment, [endorsement.givenNames, endorsement.familyName]) : '';
          span.setAttribute('title', icon === 'arrow_right_arrow_left' ? oc + '\n' + c : oc);
          small.appendChild(span);
          span.innerHTML = other;
        }
        if (main !== '') {
          span = document.createElement('span');
          const c = comment ? translator.translate(comment, [endorsement.givenNames, endorsement.familyName]) : '';
          const oc = otherComment ? translator.translate(otherComment, [endorsement.givenNames, endorsement.familyName]) : '';
          span.setAttribute('title', icon === 'arrow_right_arrow_left' ? c + '\n' + oc : c);
          small.appendChild(span);
          span.innerHTML = main;
        }
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
