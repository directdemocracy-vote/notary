function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    let tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
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
  }

  document.getElementById('type').addEventListener('change', function() {
    if (document.querySelector('input[name="type"]:checked').value == 'referendum') {
      console.log('referendum');
      document.getElementById('question-block').classList.remove('display-none');
    } else {
      document.getElementById('question-block').classList.add('display-none');
      console.log('petition');
    }
  });
}
