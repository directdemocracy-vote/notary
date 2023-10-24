async function hash(payload, type) {
  const hashBuffer = await crypto.subtle.digest(type, new TextEncoder().encode(payload));
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return hash;
}

window.onload = function() {
  document.getElementById('login').addEventListener('click', function(event) {
    const password = document.getElementById('password').value;
    const url = 'https://notary.directdemocracy.vote';
    hash(password + url, 'SHA-256').then(h => {
      fetch('/api/developer/login.php', {method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'password=' + h } )
      .then(response => response.text())
      .then(answer => {
        if (answer === 'OK') {
          localStorage.setItem('password', h);
          window.location.replace(url);
        } else
          alert('Wrong password, try again');
      });
    });
  });
};