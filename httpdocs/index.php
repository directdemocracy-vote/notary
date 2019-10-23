<!doctype html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="//directdemocracy.vote/favicon.ico">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="//unpkg.com/leaflet@1.5.1/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="" />
    <link rel="stylesheet" href="//directdemocracy.vote/css/directdemocracy.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="//unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>
    <title>publisher.directdemocracy.vote</title>
<style>
.slider {
  -webkit-appearance: none;
  min-width:200px;
  width: 100%;
  height: 6px;
  border-radius: 3px;
  background: #d3d3d3;
  outline: none;
  opacity: 0.7;
  -webkit-transition: .2s;
  transition: opacity .2s;
}

.slider:hover {
  opacity: 1;
}

.slider::-moz-focus-outer {
  border: 0;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 15px;
  height: 15px;
  border-radius: 50%;
  background: #4CAF50;
  cursor: pointer;
}

.slider::-moz-range-thumb {
  width: 15px;
  height: 15px;
  border-radius: 50%;
  background: #4CAF50;
  cursor: pointer;
}
</style>
  </head>

  <body>
    <div class='corner-ribbon' title="This web site is in beta quality: it may have bugs and change without notice. Please, report any problem to info@<?=$base_domain?>.">Beta</div>
    <main role='main'>
      <div class="jumbotron directdemocracy-title">
        <div class="container">
          <div class="row" style="margin-top:30px;margin-bottom:30px">
            <div class="col-sd-1" style="margin-right:20px;margin-top:10px"><img class="directdemocracy-title-logo" src="//directdemocracy.vote/images/directdemocracy-title.png"></div>
            <div class="col-sd-11">
              <h1><b>direct</b>democracy</h1>
              <div style="font-size:150%">publisher</div>
            </div>
          </div>
          <div class="directdemocracy-subtitle" style="position:relative;top:0;margin-bottom:40px">
            <h3>This webservice stores the publications of</h3>
            <h3>directdemocracy: citizen cards, votes, etc.</h3>
            <h3>You can check these publications here.</h3>
          </div>
        </div>
        <br>
      </div>
      <div class="form-group">
        <div class="container">
          <h3>Search citizen database</h3>
          <div class="row">
            <div class="col-md-6">
              <label for="family-name">Family name:</label>
              <input id="familyName" type="text" class="form-control" id="family-name" placeholder="Leave empty to search all" oninput="validate()">
            </div>
            <div class="col-md-6">
              <label for="given-names">Given name(s):</label>
              <input id="givenNames" type="text" class="form-control" id="given-names" placeholder="Leave empty to search all" oninput="validate()">
            </div>
          </div>
        </div>
      </div>
      <div class="form-group">
        <div class="container">
          <div class="form-text">Area: <span class="text-muted">select the search area on the map</span></div>
            <div id="latlongmap" style="width:100%;height:400px;margin-top:10px"></div>
            <script type="text/javascript">
              var geolocation = false;
              var latitude = 0;
              var longitude = 0;
              var range = 500;
              var address = '';
              if (navigator.geolocation) navigator.geolocation.getCurrentPosition(getGeolocationPosition);
              var xhttp = new XMLHttpRequest();
              xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200 && geolocation == false) {
                  coords = this.responseText.split(',');
                  getGeolocationPosition({
                    coords: {
                      latitude: coords[0],
                      longitude: coords[1]
                    }
                  });
                } else if (this.status == 429) { // quota exceeded
                  console.log(this.responseText);
                }
              };
              xhttp.open("GET", "https://ipinfo.io/loc", true);
              xhttp.send();

              function getGeolocationPosition(position) {
                geolocation = true;
                latitude = Math.round(1000000 * position.coords.latitude);
                longitude = Math.round(1000000 * position.coords.longitude);
                map.setView([position.coords.latitude, position.coords.longitude], 12);
                setTimeout(updatePosition, 500);
              }

              var lat = latitude / 1000000;
              var lon = longitude / 1000000;
              var map = L.map('latlongmap').setView([lat, lon], 2);
              L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
              }).addTo(map);
              var marker = L.marker([lat, lon]).addTo(map).bindPopup(lat + ',' + lon);
              var circle = L.circle([lat, lon], {color: 'red', opacity: 0.4, fillColor: '#f03', fillOpacity: 0.2, radius: range}).addTo(map);
              marker.setPopupContent('<div style="text-align:center" id="address">' + address + '</div>'
               + '<div><input type="range" min="5" max="100" value="10" class="slider" id="range" oninput="rangeChanged(this)"></div>'
               + '<div style="text-align:center;color:#999" id="position">(' + lat + ', ' + lon + ') &plusmn; ' + Math.round(range / 100) / 10 + ' km</div></center>'
              ).openPopup();
              map.on('click', onMapClick);
              updatePosition();

              function onMapClick(e) {
                marker.setLatLng(e.latlng).openPopup();
                circle.setLatLng(e.latlng);
                latitude = Math.round(1000000 * e.latlng.lat);
                longitude = Math.round(1000000 * e.latlng.lng);
                updateLabel();
                updatePosition();
              }

              function rangeChanged(r) {
                range = r.value * r.value * r.value;
                circle.setRadius(range);
                updateLabel();
              }

              function updatePosition() {
                console.log("updatePosition");
                var lat = latitude / 1000000;
                var lon = longitude / 1000000;
                marker.setLatLng([lat, lon]);
                circle.setLatLng([lat, lon]);
                var xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                  if (this.readyState == 4 && this.status == 200) {
                    a = JSON.parse(this.responseText);
                    address = a.display_name;
                    updateLabel();
                  }
                };
                xhttp.open('GET', 'https://nominatim.openstreetmap.org/reverse.php?format=json&lat=' + lat + '&lon=' + lon, true);
                xhttp.send();
              }

              function updateLabel() {
                var lat = latitude / 1000000;
                var lon = longitude / 1000000;
                document.getElementById("address").innerHTML = address;
                document.getElementById("position").innerHTML = '(' + lat + ', ' + lon + ') &plusmn; ' + Math.round(range / 100) / 10 + ' km';
              }
              function search() {
                const familyName = document.getElementById("familyName").value;
                const givenNames = document.getElementById("givenNames").value;
                let xhttp = new XMLHttpRequest();
                xhttp.onreadystatechange = function() {
                  if (this.readyState == 4 && this.status == 200) {
                    // console.log(this.responseText);
                    const a = JSON.parse(this.responseText);
                    // console.log(a);
                    a.forEach(function(c) {
                      const name = c.givenNames + ' ' + c.familyName;
                      console.log(name);
                      const lat = c.latitude / 1000000;
                      const lon = c.longitude / 1000000;
                      var marker = L.marker([lat, lon]).addTo(map).bindPopup(name);
                      marker.setPopupContent(name).openPopup();
                    });
                  }
                }
                let parameters = "latitude=" + latitude + "&longitude=" + longitude + "&range=" + range;
                if (familyName)
                  parameters += "&familyName=" + encodeURI(familyName);
                if (givenNames)
                  parameters += "&givenNames=" + encodeURI(givenNames);
                xhttp.open("GET", "search.php?" + parameters, true);
                xhttp.send();
              }
            </script>
          </div>
        </div>
        <div class="form-group">
          <div class="container">
            <div style="text-align:center">
              <button class="btn btn-success" role="button" onclick="search()">Search</button>
            </div>
          </div>
        </div>
      </div>
    </main>
    <div>
      <hr>
      <footer>
        <p style='text-align:center'><small>Made by citizens for citizens</small></p>
      </footer>
    </div>
  </body>

</html>
