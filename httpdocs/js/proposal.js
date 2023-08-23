const directdemocracy_version = '0.0.2';

function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    let tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}
window.onload = function() {
  let publication_crypt = null;
  let latitude = parseFloat(findGetParameter('latitude', '-1'));
  let longitude = parseFloat(findGetParameter('longitude', '-1'));
  if (latitude == -1) {
    if (navigator.geolocation)
      navigator.geolocation.getCurrentPosition(getGeolocationPosition);
    fetch('https://ipinfo.io/loc')
      .then((response) => {
        if (response.status == 429)
          console.log("quota exceeded");
        response.text();
      })
      .then((answer) => {
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

  generateCryptographicKey();

  function updateArea() {
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        const a = JSON.parse(this.responseText);
        let address = a.address;
        let select = document.getElementById('area');
        let count = 0;

        function addAdminLevel(level) {
          if (level in address)
            select.options[count++] = new Option(address[level], level);
        }
        // we ignore admin levels lowed than 'village':
        // 'block', 'neighbourhood', 'quarter', 'suburb', 'borough' and 'hamlet'
        const admin = ['village', 'town',
          'city',
          'municipality',
          'county', 'district',
          'region', 'province', 'state',
          'country'
        ];
        admin.forEach(function(item) {
          addAdminLevel(item);
        });
        const country_code = address.country_code.toUpperCase();
        if (['GB', 'DE', 'FR', 'IT', 'SE', 'PL', 'RO', 'HR', 'ES', 'NL', 'IE', 'BG', 'DK', 'GR',
            'AT', 'HU', 'FI', 'CZ', 'PT', 'BE', 'MT', 'CY', 'LU', 'SI', 'LU', 'SK', 'EE', 'LV'
          ]
          .indexOf(country_code) >= 0)
          select.options[count++] = new Option('European Union', 'union');
        select.options[count++] = new Option('Earth', 'world');
        areaChange();
      }
    };
    xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' + longitude +
      '&zoom=10', true);
    xhttp.send();
  }

  function areaChange() {
    let a = document.getElementById('area');
    let selected_name = a.options[a.selectedIndex].innerHTML;
    let selected_type = a.options[a.selectedIndex].value;
    area = '';
    let query = '';
    for (let i = a.selectedIndex; i < a.length - 1; i++) {
      let type = a.options[i].value;
      if (['village', 'town', 'municipality'].includes(type))
        type = 'city';
      const name = a.options[i].innerHTML;
      area += type + '=' + name + '\n';
      if (type != 'union')
        query += type + '=' + encodeURIComponent(name) + '&';
    }
    query = query.slice(0, -1);
    let place = document.getElementById('place');
    place.innerHTML = selected_name;
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
      document.getElementById('publish').innerHTML = 'Publish your referendum';
    } else {
      document.getElementById('question-block').style.display = 'none';
      document.getElementById('answers-block').style.display = 'none';
      document.getElementById('title').setAttribute('placeholder', 'Enter the title of your petition');
      document.getElementById('description').setAttribute('placeholder', 'Enter the description of your petition');
      document.getElementById('publish').innerHTML = 'Publish your petition';
    }
    validate();
  }

  function generateCryptographicKey() {
    document.getElementById('publish-message').innerHTML = 'Forging a cryptographic key, please wait...';
    document.getElementById('publish').classList.add('is-loading');
    let dt = new Date();
    let time = -(dt.getTime());
    publication_crypt = new JSEncrypt({
      default_key_size: 2048
    });
    publication_crypt.getKey(function() {
      dt = new Date();
      time += (dt.getTime());
      document.getElementById('publish-message').innerHTML = `A cryptographic key was just forged in ${Number(time / 1000).toFixed(2)} seconds.`;
      document.getElementById('publish').classList.remove('is-loading');
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
    console.log('title = ' + document.getElementById('title').value);
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
    console.log(document.getElementById('deadline').value);
    if (document.getElementById('deadline').value == '')
      return;
    console.log(6);
    document.getElementById('publish').removeAttribute('disabled');
  }

  document.getElementById('publish').addEventListener('click', function() {
    publication = {};
    publication.schema = `https://directdemocracy.vote/json-schema/${directdemocracy_version}/proposal.schema.json`;
    publication.key = stripped_key(publication_crypt.getPublicKey());
    publication.signature = '';
    publication.published = new Date().getTime();
    let judge = document.getElementById('judge').value;
    if (judge == '')
      judge = 'https://judge.direcdemocracy.vote';
    publication.judge = judge;
    publication.area = area;
    publication.title = document.getElementById('title').value.trim();
    publication.description = document.getElementById('description').value.trim();
    const type = document.querySelector('input[name="type"]:checked').value;
    if (type == 'referendum') {
      publication.question = document.getElementById('question').value.trim();
      publication.answers = document.getElementById('answers').value.trim();
    }
    publication.secret = (type === 'referendum');
    publication.deadline = document.getElementById('deadline').value;
    let website = document.getElementById('website').value.trim();
    if (website)
      publication.website = website;
    let str = JSON.stringify(publication);
    publication.signature = publication_crypt.sign(str, CryptoJS.SHA256, 'sha256');
    console.log(publication);
    /*
    let xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
      if (this.status == 200) {
        const answer = JSON.parse(this.responseText);
        if (answer.error)
          showModal('Area publication error', JSON.stringify(answer.error));
        else {
          let xhttp = new XMLHttpRequest();
          xhttp.onload = function() {
            if (this.status == 200) {
              const answer = JSON.parse(this.responseText);
              if (answer.error)
                showModal('Publication error', JSON.stringify(answer.error));
              else
                showModal('Publication success',
                  'Your ' + publication_type + ' was just published!<br>Check it <a target="_blank" href="' +
                  publisher + '/' + publication_type + '.html?fingerprint=' + answer.fingerprint + '">here</a>.<br>');
            }
          };
          xhttp.open('POST', publisher + '/api/publish.php', true);
          xhttp.send(JSON.stringify(publication));
        }
      }
    };
    let query = publication.area.trim().replace(/(\r\n|\n|\r)/g, "&");
    xhttp.open('GET', judge + '/api/publish_area.php?' + query, true);
    xhttp.send();
    */
  });
}
