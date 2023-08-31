function findGetParameter(parameterName) {
  let result = null;
  let tmp = [];
  location.search.substr(1).split("&").forEach(function(item) {
    tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = function() {
  const fingerprint = findGetParameter('fingerprint');
  if (!fingerprint) {
    console.log('Missing fingerprint GET argument.');
    return;
  }
  fetch(`/api/participants.php?fingerprint=${fingerprint}`)
    .then((response) => response.json())
    .then((answer) => {
      if (answer.error) {
        console.error(answer.error);
        return;
      }
      let panel = document.getElementById('panel');
      let title = document.createElement('p');
      panel.appendChild(title);
      title.classList.add('panel-heading');
      title.innerHTML = `<a href="petition.html?fingerprint=${fingerprint}">${answer.title}</a>`;
      for(const participant of answer.participants) {
        let block = document.createElement('div');
        block.classList.add('panel-block');
        panel.appendChild(block);
        block.innerHTML = `<p><a href="citizen.html?fingerprint=${participant.fingerprint}" target="_blank">` +
                          `<img src="${participant.picture}" style="width:50px;vertical-align:middle;"></img> ` +
                          `${participant.givenNames} <b>${participant.familyName}</a></p>`;
      }
    });
};
