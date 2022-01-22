function findGetParameter(parameterName, result = null) {
  location.search.substr(1).split("&").forEach(function(item) {
    let tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  let trustee = findGetParameter('trustee', 'https://trustee.directdemocracy.vote');
  let content = document.getElementById('content');
  let xhttp = new XMLHttpRequest();
  xhttp.open('POST', '/trustee.php', true);
  xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhttp.send('trustee=' + encodeURIComponent(trustee));
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error) {
        console.log(answer.error);
        return;
      }
      for (endorsement in answer.endorsements) {
        console.log(endorsement);
        let row = document.createElement('div');
        content.appendChild(row);
        row.classList.add('row');
        let col = document.createElement('div');
        row.appendChild(col);
        col.classList.add('col');
        console.log('published: ' + endorsement.published);
        col.innerHTML = new Date(endorsement.published).toISOString().slice(0, 10);
        col = document.createElement('div');
        row.appendChild(col);
        col.classList.add('col');
        if (endorsement.revoked < endorsement.expires)
          col.style.textDecoration = 'line-through';
        col.innerHTML = endorsement.givenNames + ' ' + endorsement.familyName;
      }
    }
  };
};
