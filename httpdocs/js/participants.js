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
      for(participant in answer.participants) {
        console.log(participant);
      }
    });
};
