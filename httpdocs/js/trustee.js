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
  let title = document.createElement('h2');
  let a = document.createElement('a');
  a.href = trustee;
  a.innerHTML = trustee;
  a.target = '_blank';
  title.appendChild(a);
  content.appendChild(title);
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error) {
        console.log(answer.error);
        return;
      }
      console.log(answer.endorsements);
      console.log(answer.endorsements[0]);
      console.log(answer.endorsements.length);
      console.log(answer.endorsements[0].givenNames);
      for (i = 0; i < answer.endorsements.length; i++) {
        let endorsement = answer.endorsements[i];
        let row = document.createElement('div');
        content.appendChild(row);
        row.classList.add('row');
        let col = document.createElement('div');
        row.appendChild(col);
        col.classList.add('col');
        console.log('published: ' + endorsement.published);
        col.innerHTML = new Date(endorsement.published).toISOString().slice(0, 19).replace('T', ' ');
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
