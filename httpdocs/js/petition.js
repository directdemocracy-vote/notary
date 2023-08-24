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
    const fingerprint = findGetParameter('fingerprint');
    if (!fingerprint) {
      console.log('Missing fingerprint GET argument');
      return;
    }
    /*
    document.getElementById('fingerprint').value = fingerprint;
    const rectangle = document.getElementById('fingerprint-group').getBoundingClientRect();
    let size = rectangle.right - rectangle.left;
    if (size > 512)
      size = 512;
    let qr = new QRious({
      element: document.getElementById('qr-code'),
      value: fingerprint,
      level: 'M',
      size,
      padding: 0
    });
    document.getElementById('copy-button').addEventListener('click', function(event) {
      let input = document.getElementById('fingerprint');
      input.select();
      input.setSelectionRange(0, 99999);
      document.execCommand("copy");
      input.setSelectionRange(0, 0);
      input.blur();
      message = document.getElementById('copy-message');
      message.innerHTML = "copied!";
      setTimeout(function() {
        message.innerHTML = '';
      }, 1000);
    });
    */
}
  