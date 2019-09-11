<!doctype html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="//unpkg.com/leaflet@1.5.1/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="" />
    <link rel="stylesheet" href="//directdemocracy.vote/css/directdemocracy.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="//unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>
    <title>publisher.directdemocracy.vote</title>
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
          <div style="padding-bottom:40px"><button class="btn btn-success" role="button" data-toggle="modal" data-target="#myModal">Search &raquo;</button></div>
        </div>
      </div>
      <div class="container">
        Search:
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
