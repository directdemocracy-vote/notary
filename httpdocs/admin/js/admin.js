function update() {
  if (document.querySelector('input[name="citizens"]').checked) {
    document.querySelector('input[name="endorsements"]').checked = true;
    document.querySelector('input[name="participations"]').checked = true;
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="proposals"]').checked) {
    document.querySelector('input[name="participations"]').checked = true;
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="participations"]').checked) {
    document.querySelector('input[name="votes"]').checked = true;
    document.querySelector('input[name="results"]').checked = true;
  } else if (document.querySelector('input[name="votes"]').checked) {
    document.querySelector('input[name="results"]').checked = true;
  }
}

function wipeout() {
  var url = '/admin/admin.php';
  var data = {
    password: document.querySelector('input[type="password"]').value,
    citizens: document.querySelector('input[name="citizens"]').checked,
    endorsements: document.querySelector('input[name="endorsements"]').checked,
    proposals: document.querySelector('input[name="proposals"]').checked,
    areas: document.querySelector('input[name="areas"]').checked,
    participations: document.querySelector('input[name="participations"]').checked,
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
      if (response.hasOwnProperty('error'))
        document.querySelector('#result').innerHTML = `<font style="color:red">${response.error}</font>`;
      else
        document.querySelector('#result').innerHTML = response.status;
      console.log('Success:', JSON.stringify(response));
    })
    .catch(error => console.error('Error:', error));
  return false;
}
