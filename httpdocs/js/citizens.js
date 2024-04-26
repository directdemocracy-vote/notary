function findGetParameter(parameterName, result) {
  location.search.substr(1).split('&').forEach(function(item) {
    const tmp = item.split('=');
    if (tmp[0] === parameterName)
      result = decodeURIComponent(tmp[1]);
  });
  return result;
}

window.onload = async function() {
  let judge = findGetParameter('judge', 'https://judge.directdemocracy.vote');
  console.log(judge);
}
