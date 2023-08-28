let geolocation = false;
let latitude = 0;
let longitude = 0;
let slider = 10;
let range = slider * slider * slider;
let address = '';
let markers = [];

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
  document.getElementById('proposal').addEventListener('click', function() {
    window.open(`proposal.html?latitude=${latitude}&longitude=${longitude}`, '_blank');
  });
  let tab = findGetParameter('tab');
  if (tab) {
    document.getElementById('citizens-tab').classList.remove('is-active');
    document.getElementById('citizens').style.display = 'none';
    document.getElementById(`${tab}-tab`).classList.add('is-active');
    document.getElementById(tab).style.display = 'block';
  }
  latitude = parseFloat(findGetParameter('latitude'));
  longitude = parseFloat(findGetParameter('longitude'));
  console.log(latitude);
  if (isNaN(latitude) || isNaN(longitude)) {
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
  }
  let map = L.map('map').setView([latitude, longitude], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.whenReady(function() {setTimeout(() => {this.invalidateSize();}, 0);});
  const greenIcon = new L.Icon({
    iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
  });
  let marker = L.marker([latitude, longitude]).addTo(map).bindPopup(latitude + ',' + longitude).on('click', updateLabel);
  let circle = L.circle([latitude, longitude], { color: 'red', opacity: 0.4, fillColor: '#f03', fillOpacity: 0.2, radius: range}).addTo(map);
  marker.setPopupContent(`<div style="text-align:center" id="address">${address}</div><div><input type="range" min="5" max="100" value="${slider}" class="slider" id="range"></div>` +
    `<div style="text-align:center;color:#999" id="position">(${latitude}, ${longitude} &plusmn; ${Math.round(range / 100) / 10} km</div></center>`).openPopup();
  document.getElementById('range').addEventListener('input', rangeChanged);
  map.on('click', function(event) {
    marker.setLatLng(event.latlng).openPopup();
    circle.setLatLng(event.latlng);
    latitude = event.latlng.lat;
    longitude = event.latlng.lng;
    updateLabel();
    updatePosition();
    let range = document.getElementById('range');
    range.setAttribute('value', slider);
    range.addEventListener('input', rangeChanged);
  });
  map.on('contextmenu', function(event) { return false; });
  updatePosition();
  document.getElementById('search-citizens').addEventListener('click', function(event) {
    document.getElementById('citizens-fieldset').setAttribute('disabled', '');
    let searchCitizen = event.currentTarget;
    searchCitizen.classList.add('is-loading');
    const familyName = document.getElementById("family-name").value;
    const givenNames = document.getElementById("given-names").value;
    let parameters = `latitude=${latitude}&longitude=${longitude}&range=${range}`;
    if (familyName)
      parameters += `&familyName=${encodeURI(familyName)}`;
    if (givenNames)
      parameters += `&givenNames=${encodeURI(givenNames)}`;
    fetch(`https://notary.directdemocracy.vote/api/search.php?${parameters}`)
      .then((response) => response.json())
      .then((answer) => {
        markers.forEach(function(marker) {map.removeLayer(marker);});
        markers = [];
        answer.forEach(function(citizen) {
          const name = `${citizen.givenNames} ${citizen.familyName}`;
          const fingerprint = CryptoJS.SHA1(citizen.signature).toString();
          const label = `<div style="text-align:center"><a target="_blank" href="/citizen.html?fingerprint=${fingerprint}"><img src="${citizen.picture}" width="60" height="80"><br>${name}</a></div>`;
          markers.push(L.marker([citizen.latitude, citizen.longitude], {icon: greenIcon}).addTo(map).bindPopup(label));
        });
        document.getElementById('citizens-fieldset').removeAttribute('disabled');
        searchCitizen.classList.remove('is-loading');
      });
  });

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }
  
  function rangeChanged(event) {
    slider = event.currentTarget.value;
    range = slider * slider * slider;
    circle.setRadius(range);
    updateLabel();
  }
  
  function updatePosition() {
    marker.setLatLng([latitude, longitude]);
    circle.setLatLng([latitude, longitude]);
    fetch(`https://nominatim.openstreetmap.org/reverse.php?format=json&lat=${latitude}&lon=${longitude}&zoom=10`)
      .then((response) => response.json())
      .then((answer) => {
        address = answer.display_name;
        updateLabel();
      });
  }
  
  function updateLabel() {
    document.getElementById("address").innerHTML = address;
    document.getElementById("position").innerHTML = '(' + latitude + ', ' + longitude + ') &plusmn; ' + Math.round(range / 100) / 10 + ' km';
  }
}

function openTab(event, name) {
  let contentTab = document.getElementsByClassName('content-tab');
  for (let i = 0; i < contentTab.length; i++)
    contentTab[i].style.display = 'none';
  let tab = document.getElementsByClassName('tab');
  for (let i = 0; i < tab.length; i++)
    tab[i].classList.remove('is-active');
  document.getElementById(name).style.display = 'block';
  event.currentTarget.classList.add('is-active');
}
