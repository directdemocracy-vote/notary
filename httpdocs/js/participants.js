function findGetParameter(parameterName) {
  let result = null;
  let tmp = [];
  location.search.substr(1).split("&").forEach(function(item) {
    tmp = item.split("=");
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  const fingerprint = findGetParameter('fingerprint');
  if (!fingerprint) {
    console.log('Missing fingerprint GET argument.');
    return;
  }
  let request = `/api/participants.php?fingerprint=${fingerprint}`;
  let corpus;
  if (findGetParameter('corpus') === '1') {
    corpus = true;
    request += '&corpus=1';
  } else
    corpus = false;
  fetch(request)
    .then((response) => response.json())
    .then((answer) => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      let subtitle = document.getElementById('subtitle');
      if (corpus)
        subtitle.innerHTML = 'Petition corpus';
      else
        subtitle.innerHTML = 'Petition participants';
      let panel = document.getElementById('panel');
      let title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      title.innerHTML = `<a href="petition.html?fingerprint=${fingerprint}">${answer.title}</a>`;
      for(const participant of answer.participants) {
        let block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        let line = `<p><a href="citizen.html?fingerprint=${participant.fingerprint}" target="_blank">` +
                   `<img src="${participant.picture}" style="width:50px;float:left;margin-right:10px"></img> ` +
                   `${participant.givenNames} <b>${participant.familyName}</b></a>`;
        if (!corpus) {
          const d = new Date(parseInt(participant.published));
          line += `<br>Signed on: ${d.toLocaleString()}`;
        }
        line += '</p>';
        block.innerHTML = line;
      }
    });
};
