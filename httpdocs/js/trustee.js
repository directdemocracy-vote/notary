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
  let title = document.createElement('div');
  let p = document.createElement('p');
  let a = document.createElement('a');
  a.href = trustee;
  a.innerHTML = trustee;
  a.target = '_blank';
  let b = document.createElement('b');
  b.innerHTML = 'Trustee: ';
  p.appendChild(b);
  p.appendChild(a);
  title.appendChild(p);
  content.appendChild(title);
  xhttp.onload = function() {
    if (this.status == 200) {
      let answer = JSON.parse(this.responseText);
      if (answer.error) {
        console.log(answer.error);
        return;
      }
      let table = document.createElement('table');
      let th = document.createElement('th');
      table.appendChild(th);
      let td = document.createElement('td');
      td.innerHTML = 'Date';
      th.appendChild(td);
      td = document.createElement('td');
      td.innerHTML = 'Name';
      th.appendChild(td);
      for (i = 0; i < answer.endorsements.length; i++) {
        let tr = document.createElement('tr');
        let endorsement = answer.endorsements[i];
        let td = document.createElement('td');
        td.innerHTML = new Date(endorsement.published).toISOString().slice(0, 19).replace('T', ' ');
        tr.appendChild(td);
        td = document.createElement('td');
        if (endorsement.revoked < endorsement.expires)
          td.style.textDecoration = 'line-through';
        td.innerHTML = endorsement.givenNames + ' ' + endorsement.familyName;
        tr.appendChild(td);
        table.appendChild(tr);
      }
      content.appendChild(table);
    }
  };
};