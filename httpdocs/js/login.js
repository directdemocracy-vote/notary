async function login(password) {
  const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(password));
  console.log('password = ' + password);
  console.log('hash = ' + hash);
}

window.onload = async function() {
  document.getElementById('login').addEventListener('click', function(event) {
    const password = document.getElementById('password').value;
    login(password);
  });
};