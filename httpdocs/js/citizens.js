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
      answer.push(answer[0]); // debug
      answer.push(answer[0]); // debug
      for(const citizen of answer) {
        if (!citizen.hasOwnProperty('picture'))
          continue;
        const tr = document.createElement('tr');
        tbody.appendChild(tr);
        let td = document.createElement('td');
        tr.appendChild(td);
        const img = document.createElement('img');
        td.appendChild(img);
        td.style.padding = 0;
        img.src = citizen.picture;
        img.style.height = '40px';
        img.style.width = 'auto';
        img.style.verticalAlign = 'middle';
        img.addEventListener('mouseover', function(event) {
          const style = event.currentTarget.style;
          style.height = '200px';
          style.position = 'absolute';
          style.transform = 'translate(-20px,-80px)';
        });
        img.addEventListener('mouseout', function(event) {
          const style = event.currentTarget.style;
          style.height = '40px';
          style.position = '';
          style.transform = '';
        });
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.familyName;
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.givenNames;
        td = document.createElement('td');
        tr.appendChild(td);
        const date = new Date(citizen.published * 1000);
        td.textContent = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
      }
      console.log(answer);
    });
}
