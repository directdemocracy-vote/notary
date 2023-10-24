window.onload = async function() {
  document.getElementById('login').addEventListener('click', function(event) {
    const password = document.getElementById('password').value;
    const hash = await crypto.subtle.digest('SHA-256', password);
    console.log('password = ' + password);
    console.log('hash = ' + hash);
  });
};