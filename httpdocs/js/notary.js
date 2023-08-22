let geolocation = false;
let latitude = 0;
let longitude = 0;
let slider = 10;
let range = slider * slider * slider;
let address = '';
let markers = [];

window.onload = function() {
  if (navigator.geolocation) navigator.geolocation.getCurrentPosition(getGeolocationPosition);
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200 && geolocation == false) {
      coords = this.responseText.split(',');
      getGeolocationPosition({coords: {latitude: coords[0], longitude: coords[1]}});
    } else if (this.status == 429)  // quota exceeded
      console.log(this.responseText);
  };
  xhttp.open("GET", "https://ipinfo.io/loc", true);
  xhttp.send();

  let map = L.map('map').setView([latitude, longitude], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  map.whenReady(function() { setTimeout(() => { this.invalidateSize(); }, 0); });
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
  map.on('click', function(e) {
    marker.setLatLng(e.latlng).openPopup();
    circle.setLatLng(e.latlng);
    latitude = e.latlng.lat;
    longitude = e.latlng.lng;
    updateLabel();
    updatePosition();
    let range = document.getElementById('range');
    range.setAttribute('value', slider);
    range.addEventListener('input', rangeChanged);
  });
  map.on('contextmenu', function(event) { return false; });
  updatePosition();
  document.getElementById('search-ciziten').addEventListener('click', function() {
    const familyName = document.getElementById("family-name").value;
    const givenNames = document.getElementById("given-names").value;
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        const a = JSON.parse(this.responseText);
        markers.forEach(function(m) { // delete previous markers
          map.removeLayer(m);
        });
        markers = [];
        a.forEach(function(c) {
          const name = c.givenNames + ' ' + c.familyName;
          const fingerprint = CryptoJS.SHA1(c.signature).toString();
          const label = '<div style="text-align:center"><a target="_blank" href="/citizen.html?fingerprint=' + fingerprint + '"><img src="' + c.picture + '" width="60" height="80"><br>' + name + '</a></div>';
          markers.push(L.marker([c.latitude, c.longitude], {
            icon: greenIcon
          }).addTo(map).bindPopup(label));
        });
      }
    }
    let parameters = "latitude=" + latitude + "&longitude=" + longitude + "&range=" + range;
    if (familyName) parameters += "&familyName=" + encodeURI(familyName);
    if (givenNames) parameters += "&givenNames=" + encodeURI(givenNames);
    xhttp.open("GET", "https://notary.directdemocracy.vote/api/search.php?" + parameters, true);
    xhttp.send();
  });

  function getGeolocationPosition(position) {
    geolocation = true;
    latitude = position.coords.latitude;
    longitude = position.coords.longitude;
    map.setView([position.coords.latitude, position.coords.longitude], 12);
    setTimeout(updatePosition, 500);
  }
  
  function rangeChanged(e) {
    slider = e.currentTarget.value;
    range = slider * slider * slider;
    circle.setRadius(range);
    updateLabel();
  }
  
  function updatePosition() {
    marker.setLatLng([latitude, longitude]);
    circle.setLatLng([latitude, longitude]);
    let xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        a = JSON.parse(this.responseText);
        address = a.display_name;
        updateLabel();
      }
    };
    xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + latitude + '&lon=' + longitude + '&zoom=10', true);
    xhttp.send();
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
