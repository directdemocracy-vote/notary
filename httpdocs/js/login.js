async function hash(payload, type) {
  const hashBuffer = await crypto.subtle.digest(type, new TextEncoder().encode(payload));
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return hash;
}

window.onload = function() {
  document.getElementById('login').addEventListener('click', function(event) {
    const password = document.getElementById('password').value;
    console.log('password = ' + password);
    hash(password, 'SHA-256').then(h => {
      console.log('hash = ' + h);
    });
  });
};