function findGetParameter(parameterName, result) {
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  const judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  const commune = findGetParameter('commune');
  const type = findGetParameter('type');
  fetch(`api/citizens.php?commune=${commune}&judge=${judge}&type=${type}`)
    .then(response => response.json())
    .then(answer => {
      const tbody = document.getElementById('tbody');
      for(const citizen of answer) {
        if (!citizen.hasOwnProperty('picture'))
          continue;
        const tr = document.createElement('tr');
        tbody.appendChild(tr);
        let td = document.createElement('td');
        tr.appendChild(td);
        const img = document.createElement('img');
        td.appendChild(img);
        img.src = citizen.picture;
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.familyName;
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.givenNames;
        td = document.createElement('td');
        tr.appendChild(td);
        const date = new Date(citizen.published * 1000);
        td.textContent = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + ' ' + date.toLocaleDateString();
      }
      console.log(answer);
    });
}
