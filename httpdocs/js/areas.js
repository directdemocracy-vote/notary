function findGetParameter(parameterName) {
  let result;
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  if (localStorage.getItem('password')) {
    const a = document.createElement('a');
    a.setAttribute('id', 'logout');
    a.textContent = 'logout';
    document.getElementById('logout-div').appendChild(a);
    document.getElementById('logout').addEventListener('click', function(event) {
      document.getElementById('logout-div').textContent = '';
      localStorage.removeItem('password');
    });
  }
  const fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  if (!fingerprint && !signature) {
    console.error('Missing fingerprint or signature GET argument.');
    return;
  }
  const selector = signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
  let request = `/api/areas.php?${selector}`;
  fetch(request)
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      const panel = document.getElementById('panel');
      const title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      const a = document.createElement('a');
      a.setAttribute('href', `proposal.html?${selector}`);
      a.textContent = answer.title;
      title.appendChild(a);
      if (answer.areas.length === 0) {
        const block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        const p = document.createElement('p');
        p.textContent = 'No referendum area are available yet.';
        block.appendChild(p);
      } else {
        const table = document.createElement('table');
        table.classList.add('table');
        panel.appendChild(table);
        const thead = document.createElement('thead');
        table.appendChild(thead);
        const tr = document.createElement('tr');
        thead.appendChild(tr);
        let th = document.createElement('th');
        tr.appendChild(th);
        translator.translateElement(th, 'area');
        for(const a of answer.answers) {
          th = document.createElement('th');
          tr.appendChild(th);
          th.textContent = a;
        }
        th = document.createElement('th');
        tr.appendChild(th);
        th.textContent = 'Blank';
        const tbody = document.createElement('tbody');
        table.appendChild(tbody);
        for (const area of answer.areas) {
          const tr = document.createElement('tr');
          tbody.appendChild(tr);
          let td = document.createElement('td');
          tr.appendChild(td);
          const localArea = area.name.split('\n')[0].split('=')[1];
          td.textContent = localArea;
          td.title = area.name.replaceAll('=', ': ');
          let expressed = 0;
          let sum = 0;
          let first = true;
          for(const a of area.answers) {
            sum += a;
            if (first) {
              first = false;
              continue;
            }
            td = document.createElement('td');
            td.align = 'center';
            tr.appendChild(td);
            td.textContent = a;
            expressed += a;
          }
          td = document.createElement('td');
          td.align = 'center';
          tr.appendChild(td);
          td.textContent = area.answers[0];          
        }
      }
    });
};
