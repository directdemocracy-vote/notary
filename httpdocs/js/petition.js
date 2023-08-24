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
    function closeModal() {
      document.getElementById('modal').classList.remove('is-active');
    }
    document.getElementById('modal-cancel-button').addEventListener('click', closeModal);
    document.getElementById('modal-close-button').addEventListener('click', closeModal);
    document.getElementById('modal-ok-button').addEventListener('click', closeModal);

    document.getElementById('sign-button').addEventListener('click', function() {
      const qr = new QRious({
        value: fingerprint,
        level: 'L',
        size: 512,
        padding: 0
      });
      let div = document.createElement('div');
      div.classList.add('content', 'has-text-centered');
      let input = document.createElement('input');
      div.appendChild(input);
      input.style.display = 'none';
      input.value = fingerprint;
      let img = document.createElement('img');
      div.appendChild(img);
      img.src = qr.toDataURL();
      let message = document.createElement('div');
      div.appendChild(message);
      message.innerHTML = 'From the <i>directdemocracy</i> app, scan this QR code or click it and paste it in the app.';
      img.addEventListener('click', function() {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand("copy");
        input.setSelectionRange(0, 0);
        input.blur();
        message.innerHTML = 'Copied in clipboard! You can now paste in the <i>directdemocracy</i> app.';
      });
      document.getElementById('modal-title').innerHTML = 'Sign this petition';
      let content = document.getElementById('modal-content');
      content.innerHTML = '';
      content.appendChild(div);
      document.getElementById('modal-footer').style.display = 'none';
      document.getElementById('modal').classList.add('is-active');
    });
}
