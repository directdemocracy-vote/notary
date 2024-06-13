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
  const locality = findGetParameter('locality');
  const type = findGetParameter('type');
  fetch(`api/citizens.php?locality=${locality}&judge=${judge}&type=${type}`)
    .then(response => response.json())
    .then(answer => {
      function reduce(event) {
        const style = event.currentTarget.style;
        style.height = '40px';
        style.position = '';
        style.transform = '';
      }
      function magnify(event) {
        const style = event.currentTarget.style;
        style.height = '200px';
        style.position = 'absolute';
        style.transform = 'translate(-20px,-80px)';
      }
      const tbody = document.getElementById('tbody');
      for (const citizen of answer) {
        function link(event) {
          location.href = `/citizen.html?signature=${citizen.signature}`;
        }
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
        img.addEventListener('mouseover', magnify);
        img.addEventListener('mouseout', reduce);
        img.addEventListener('click', function(event) {
          if (event.currentTarget.style.position === '')
            magnify(event);
          else
            reduce(event);
        });
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.familyName;
        td.addEventListener('click', link);
        td = document.createElement('td');
        tr.appendChild(td);
        td.textContent = citizen.givenNames;
        td.addEventListener('click', link);
        td = document.createElement('td');
        tr.appendChild(td);
        const date = new Date(citizen.published * 1000);
        td.textContent = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        td.addEventListener('click', link);
      }
    });
};
