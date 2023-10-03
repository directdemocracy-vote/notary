function findGetParameter(parameterName) {
  let result;
  location.search.substr(1).split("&").forEach(function(item) {
    const tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  const fingerprint = findGetParameter('fingerprint');
  const signature = findGetParameter('signature');
  if (!fingerprint && !signature) {
    console.error('Missing fingerprint or signature GET argument.');
    return;
  }
  let request = '/api/participants.php?' + signature ? `signature=${encodeURIComponent(signature)}` : `fingerprint=${fingerprint}`;
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
      const subtitle = document.getElementById('subtitle');
      if (corpus)
        subtitle.innerHTML = 'Petition corpus';
      else
        subtitle.innerHTML = 'Petition participants';
      const panel = document.getElementById('panel');
      const title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      title.innerHTML = `<a href="proposal.html?fingerprint=${fingerprint}">${answer.title}</a>`;
      if (answer.participants.length === 0) {
        const block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        block.innerHTML = '<p>Nobody signed this petition yet.</p>';
      } else for(const participant of answer.participants) {
        const block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        const fingerprint = CryptoJS.SHA1(CryptoJS.enc.Base64.parse(participant.signature));
        let line = `<p style="width:100%"><a href="citizen.html?fingerprint=${fingerprint}" target="_blank">` +
                   `<img src="${participant.picture}" style="width:50px;float:left;margin-right:10px"></img> ` +
                   `${participant.givenNames} <b>${participant.familyName}</b></a>`;
        if (!corpus) {
          const d = new Date(parseInt(participant.published));
          line += `<br><small>Signed on: ${d.toLocaleString()}</small>`;
        }
        line += '</p>';
        block.innerHTML = line;
      }
    });
};
