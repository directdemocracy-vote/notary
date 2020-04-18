function update() {
  if (document.querySelector('input[name="citizens"]').checked) {
    document.querySelector('input[name="endorsements"]').checked = true;
    document.querySelector('input[name="registrations"]').checked = true;
    document.querySelector('input[name="ballots"]').checked = true;
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="referendums"]').checked) {
    document.querySelector('input[name="registrations"]').checked = true;
    document.querySelector('input[name="ballots"]').checked = true;
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="registrations"]').checked) {
    document.querySelector('input[name="ballots"]').checked = true;
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="ballots"]').checked) {
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  }
}

function wipeout() {
  var url = '/admin/admin.php';
  var data = {
    password: document.querySelector('input[type="password"]').value,
    citizen: document.querySelector('input[name="citizens"]').checked,
    endorsements: document.querySelector('input[name="endorsements"]').checked,
    referendums: document.querySelector('input[name="referendums"]').checked,
    areas: document.querySelector('input[name="areas"]').checked,
    registrations: document.querySelector('input[name="registrations"]').checked,
    ballots: document.querySelector('input[name="ballots"]').checked,
    votes: document.querySelector('input[name="votes"]').checked,
    results: document.querySelector('input[name="results"]').checked
  };
  fetch(url, {
      method: 'POST', // or 'PUT'
      body: JSON.stringify(data), // data can be `string` or {object}!
      headers: {
        'Content-Type': 'application/json'
      }
    }).then(res => res.json())
    .then(function(response) {
      document.querySelector('#result').innerHTML = response.status;
      console.log('Success:', JSON.stringify(response));
    })
    .catch(error => console.error('Error:', error));
  return false;
}
