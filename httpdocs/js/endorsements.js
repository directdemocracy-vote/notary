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
  const judge = findGetParameter('judge');
  if (!judge)
    judge = 'https://judge.directdemocracy.vote';
  fetch(`/api/endorsements.php?fingerprint=${fingerprint}&judge=${judge}`)
    .then((response) => response.json())
    .then((answer) => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      let subtitle = document.getElementById('subtitle');
      subtitle.innerHTML = 'Citizen endorsements';
      let panel = document.getElementById('panel');
      let title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      title.innerHTML = `<a href="citizen.html?fingerprint=${fingerprint}">${answer.givenNames} <b>${answer.familyName}</b></a>`;
      for(const endorsement of answer.endorsements) {
        let block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        const d = new Date(parseInt(endorsement.published));
        const action = answer.revoked ? 'Revoked on: ' : 'Endorsed on: ';
        const latest = answer.latest === true;
        block.innerHTML = `<p style="width:100%">${latest ? '<b>' : ''}${action} ${d.toLocaleString()}${latest ? '</b>' : ''}</p>`;
      }
    });
};
