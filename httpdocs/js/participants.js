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
  let request = `/api/participants.php?${selector}`;
  let corpus;
  if (findGetParameter('corpus') === '1') {
    corpus = true;
    request += '&corpus=1';
  } else
    corpus = false;
  fetch(request)
    .then(response => response.json())
    .then(answer => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      const type = answer.type.charAt(0).toUpperCase() + answer.type.slice(1);
      const subtitle = document.getElementById('subtitle');
      if (corpus)
        subtitle.textContent = `${type} corpus`;
      else
        subtitle.textContent = `${type} participants`;
      const panel = document.getElementById('panel');
      const title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      const a = document.createElement('a');
      a.setAttribute('href', `proposal.html?${selector}`);
      a.textContent = answer.title;
      title.appendChild(a);
      if (answer.participants.length === 0) {
        const block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        const p = document.createElement('p');
        p.textContent = answer.type === 'petition' ? 'Nobody signed this petition yet.' : 'No referendum results are available yet.';
        block.appendChild(p);
      } else {
        for (const participant of answer.participants) {
          const block = document.createElement('div');
          block.classList.add('panel-block');
          const p = document.createElement('p');
          p.setAttribute('style', 'width:100%');
          const a = document.createElement('a');
          a.setAttribute('href', `citizen.html?${selector}`);
          a.setAttribute('target', '_blank');
          p.appendChild(a);
          const img = document.createElement('img');
          img.setAttribute('src', participant.picture);
          img.setAttribute('style', 'width:50px;float:left;margin-right:10px');
          a.appendChild(img);
          a.appendChild(document.createTextNode(participant.givenNames + ' '));
          const b = document.createElement('b');
          b.textContent = participant.familyName;
          a.appendChild(b);
          if (!corpus) {
            const d = new Date(parseInt(participant.published) * 1000);
            p.appendChild(document.createElement('br'));
            const small = document.createElement('small');
            small.textContent = answer.type === 'petition' ? `Signed on: ${d.toLocaleString()}` : 'Has voted';
            p.appendChild(small);
          }
          block.appendChild(p);
          panel.appendChild(block);
        }
      }
    });
};
