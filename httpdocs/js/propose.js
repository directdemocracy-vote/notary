const directdemocracy_version = '2';

function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    const tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

function showModal(title, content) {
  document.getElementById('modal-title').innerHTML = title;
  document.getElementById('modal-content').innerHTML = content;
  document.getElementById('modal').classList.add('is-active');
}

function closeModal() {
  document.getElementById('modal').classList.remove('is-active');
}

function sanitizeString(str) {
  str = str.replaceAll('&', '&amp;');
  str = str.replaceAll("'", '&apos;');
  str = str.replaceAll('"', '&quot;');
  str = str.replaceAll('<', '&lt;');
  str = str.replaceAll('>', '&gt;');

  return str;
}

window.onload = function() {
  document.getElementById('modal-close-button').addEventListener('click', closeModal);
  document.getElementById('modal-ok-button').addEventListener('click', closeModal);

  let publication_crypt;
  let latitude = parseFloat(findGetParameter('latitude', '-1'));
  let longitude = parseFloat(findGetParameter('longitude', '-1'));
  let geolocation = false;
  if (latitude == -1) {
    if (navigator.geolocation)
      navigator.geolocation.getCurrentPosition(getGeolocationPosition);
    fetch('https://ipinfo.io/loc')
      .then(response => {
        if (response.status == 429)
          console.error("quota exceeded");
        return response.text();
      })
      .then(answer => {
        if (!geolocation) {
          coords = answer.split(',');
          getGeolocationPosition({coords: {latitude: coords[0], longitude: coords[1]}});
        }
      });

    function getGeolocationPosition(position) {
      geolocation = true;
      latitude = position.coords.latitude;
      longitude = position.coords.longitude;
      updateArea();
    }
  } else
    updateArea();

  document.getElementById('area').addEventListener('change', areaChange);
  document.getElementById('referendum').addEventListener('change', updateProposalType);
  document.getElementById('petition').addEventListener('change', updateProposalType);
  document.getElementById('title').addEventListener('input', validate);
  document.getElementById('description').addEventListener('input', validate);
  document.getElementById('question').addEventListener('input', validate);
  document.getElementById('answers').addEventListener('input', validate);
  document.getElementById('deadline').addEventListener('input', validate);
  document.getElementById('judge').addEventListener('input', validate);

  generateCryptographicKey();

  function updateArea() {
    fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=10`)
      .then(response => response.json())
      .then(answer => {
        const address = answer.address;
        const select = document.getElementById('area');
        let count = 0;

        function addAdminLevel(level) {
          if (level in address)
            select.options[count++] = new Option(address[level], level);
        }
        // we ignore admin levels lower than 'village': 'block', 'neighbourhood', 'quarter', 'suburb', 'borough' and 'hamlet'
        const admin = ['village', 'town', 'city', 'municipality', 'county', 'district', 'region', 'province', 'state', 'country'];
        admin.forEach(function(item) { addAdminLevel(item); });
        const country_code = address.country_code.toUpperCase();
        if (['DE', 'FR', 'IT', 'SE', 'PL', 'RO', 'HR', 'ES', 'NL', 'IE', 'BG', 'DK', 'GR',
          'AT', 'HU', 'FI', 'CZ', 'PT', 'BE', 'MT', 'CY', 'LU', 'SI', 'LU', 'SK', 'EE', 'LV'].indexOf(country_code) >= 0)
          select.options[count++] = new Option('European Union', 'union');
        select.options[count++] = new Option('Earth', 'world');
        areaChange();
      });
  }

  function areaChange() {
    const a = document.getElementById('area');
    const selected_name = a.options[a.selectedIndex].textContent;
    const selected_type = a.options[a.selectedIndex].value;
    area = '';
    let query = '';
    for (let i = a.selectedIndex; i < a.length - 1; i++) {
      let type = a.options[i].value;
      if (['village', 'town', 'municipality'].includes(type))
        type = 'city';
      const name = a.options[i].textContent;
      area += type + '=' + name + '\n';
      if (type != 'union')
        query += type + '=' + encodeURIComponent(name) + '&';
    }
    query = query.slice(0, -1);
    const place = document.getElementById('place');
    place.textContent = selected_name;
    if (selected_type == 'union' && selected_name == 'European Union')
      place.href = 'https://en.wikipedia.org/wiki/European_Union';
    else if (selected_type == 'world' && selected_name == 'Earth')
      place.href = 'https://en.wikipedia.org/wiki/Earth';
    else
      place.href = 'https://nominatim.openstreetmap.org/ui/search.html?' + query + '&polygon_geojson=1';
    validate();
  }

  function updateProposalType() {
    if (document.querySelector('input[name="type"]:checked').value == 'referendum') {
      document.getElementById('question-block').style.display = 'block';
      document.getElementById('answers-block').style.display = 'block';
      document.getElementById('title').setAttribute('placeholder', 'Enter the title of your referendum');
      document.getElementById('description').setAttribute('placeholder', 'Enter the description of your referendum');
      document.getElementById('publish').textContent = 'Publish your referendum';
    } else {
      document.getElementById('question-block').style.display = 'none';
      document.getElementById('answers-block').style.display = 'none';
      document.getElementById('title').setAttribute('placeholder', 'Enter the title of your petition');
      document.getElementById('description').setAttribute('placeholder', 'Enter the description of your petition');
      document.getElementById('publish').textContent = 'Publish your petition';
    }
    validate();
  }

  function generateCryptographicKey() {
    document.getElementById('publish-message').textContent = 'Forging a cryptographic key, please wait...';
    const button = document.getElementById('publish');
    button.classList.add('is-loading');
    button.setAttribute('disabled', '');
    let dt = new Date();
    let time = -(dt.getTime());
    publication_crypt = new JSEncrypt({
      default_key_size: 2048
    });
    publication_crypt.getKey(function() {
      dt = new Date();
      time += (dt.getTime());
      document.getElementById('publish-message').textContent = `A cryptographic key was just forged in ${Number(time / 1000).toFixed(2)} seconds.`;
      button.classList.remove('is-loading');
      button.removeAttribute('disabled');
      validate();
    });
  }

  function stripped_key(public_key) {
    let stripped = '';
    const header = '-----BEGIN PUBLIC KEY-----\n'.length;
    const footer = '-----END PUBLIC KEY-----'.length;
    const l = public_key.length - footer;
    for (let i = header; i < l; i += 65)
      stripped += public_key.substr(i, 64);
    stripped = stripped.slice(0, -1 - footer);
    return stripped;
  }

  function validate() {
    document.getElementById('publish').setAttribute('disabled', '');
    if (!document.querySelector('input[name="type"]:checked'))
      return;
    const type = document.querySelector('input[name="type"]:checked').value;
    if (document.getElementById('title').value == '')
      return;
    if (document.getElementById('description').value == '')
      return;
    if (type == referendum) {
      if (document.getElementById('question').value == '')
        return;
      if (document.getElementById('answers').value == '')
        return;
    }
    if (document.getElementById('deadline').value == '')
      return;
    if (document.getElementById('judge').value == '')
      return;
    document.getElementById('publish').removeAttribute('disabled');
  }

  document.getElementById('publish').addEventListener('click', function(event) {
    const button = event.currentTarget;
    button.classList.add('is-loading');
    button.setAttribute('disabled', '');
    const judge = document.getElementById('judge').value;
    const query = area.trim().replace(/(\r\n|\n|\r)/g, "&");
    fetch(`${judge}/api/publish_area.php?${query}`)
      .then(response => response.json())
      .then(answer => {
        if (answer.error) {
          showModal('Area publication error', JSON.stringify(answer.error));
          button.classList.remove('is-loading');
          button.removeAttribute('disabled');
        } else {
          publication = {};
          publication.schema = `https://directdemocracy.vote/json-schema/${directdemocracy_version}/proposal.schema.json`;
          publication.key = stripped_key(publication_crypt.getPublicKey());
          publication.signature = '';
          publication.published = Math.round(new Date().getTime() / 1000);
          publication.judge = judge;
          publication.area = answer.signature;
          publication.title = document.getElementById('title').value.trim();
          publication.description = sanitizeString(document.getElementById('description').value.trim());
          const type = document.querySelector('input[name="type"]:checked').value;
          if (type === 'referendum') {
            publication.question = document.getElementById('question').value.trim();
            publication.answers = document.getElementById('answers').value.trim().split("\n");
            publication.secret = true;
          } else
            publication.secret = false;
          publication.deadline = Math.round(Date.parse(document.getElementById('deadline').value) / 1000);
          const website = document.getElementById('website').value.trim();
          if (website)
            publication.website = website;
          const str = JSON.stringify(publication);
          publication.signature = publication_crypt.sign(str, CryptoJS.SHA256, 'sha256');
          console.log(publication);
          fetch(`/api/publish.php`, {'method': 'POST', 'body': JSON.stringify(publication)})
            .then(response => response.json())
            .then(answer => {
              button.classList.remove('is-loading');
              button.removeAttribute('disabled');
              if (answer.error)
                showModal('Publication error', JSON.stringify(answer.error));
              else {
                showModal('Publication success',
                  `Your ${type} was just published!<br>You will be redirected to it.`);
                document.getElementById('modal-ok-button').addEventListener('click', function() {
                  window.location.href = `/proposal.html?signature=${encodeURIComponent(answer.signature)}`;
                });
              }
            });
        }
      });
  });
};
