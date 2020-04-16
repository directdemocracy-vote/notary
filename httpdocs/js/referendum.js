function findGetParameter(parameterName) {
  let result = null;
  let tmp = [];
  location.search.substr(1).split("&").forEach(function(item) {
    tmp = item.split("=");
    if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
  });
  return result;
}

function unix_time_to_text(unix_timestamp) {
  const a = new Date(unix_timestamp * 1000);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const year = a.getFullYear();
  const month = months[a.getMonth()];
  const date = a.getDate();
  const hour = a.getHours();
  const minute = '0' + a.getMinutes();
  const second = '0' + a.getSeconds();
  const time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + minute.substr(-2) + ':' + second.substr(-2);
  return time;
}

window.onload = function() {
  const fingerprint = findGetParameter('fingerprint');
  if (!fingerprint) {
    console.log('Missing fingerprint GET argument');
    return;
  }
  const publisher = 'https://publisher.directdemocracy.vote';
  let xhttp = new XMLHttpRequest();
  xhttp.onload = function() {
    if (this.status == 200) {
      let referendum = JSON.parse(this.responseText);
      if (referendum.error)
        console.log('publisher error', JSON.stringify(referendum.error));
      else {
        const first_equal = referendum.area.indexOf('=');
        const first_newline = referendum.area.indexOf('\n');
        let area_name = referendum.area.substr(first_equal + 1, first_newline - first_equal);
        let area_type = referendum.area.substr(0, first_equal);
        const area_array = referendum.area.split('\n');
        let area_query = '';
        area_array.forEach(function(argument) {
          const eq = argument.indexOf('=');
          const type = argument.substr(0, eq);
          const name = argument.substr(eq + 1);
          if (type)
            area_query += type + '=' + encodeURIComponent(name) + '&';
        });
        area_query = area_query.slice(0, -1);
        let area_url;
        if (!area_type) {
          area_type = 'world';
          area_name = 'Earth';
          area_url = 'https://en.wikipedia.org/wiki/Earth';
        } else if (area_type == 'union')
          area_url = 'https://en.wikipedia.org/wiki/European_Union';
        else
          area_url = 'https://nominatim.openstreetmap.org/search.php?' + area_query + '&polygon_geojson=1';
        const answers = referendum.answers.split('\n');
        const answer_count = answers.length;
        const results = [223, 336, 28];
        const total = results.reduce((a, b) => a + b, 0);
        answers_table = '<table class="table table-bordered"><thead class="thead-light"><tr>';
        const colors = ['primary', 'danger', 'success', 'warning', 'info', 'secondary', 'light', 'dark'];
        answers.forEach(function(answer) {
          answers_table += '<th width="' + Math.round(100 / answer_count) + '%" scope="col">' + answer + '</th>';
        });
        answers_table += '</tr></thead><tbody><tr>';
        let color_count = 0;
        let count = 0;
        answers.forEach(function(answer) {
          const percent = Math.round(10000 * results[count++] / total) / 100;
          answers_table +=
            '<td><div class="progress"><div class="progress-bar progress-bar-striped bg-' +
            colors[color_count++] + '" role="progressbar" ' +
            'style="width:' + percent + '%" aria-valuemin="0" aria_valuemax="100">' + percent + ' %' +
            '</div></div></td>';
          if (color_count == colors.length)
            color_count = 0;
        });
        count = 0;
        answers_table += '</tr><tr>';
        answers.forEach(function(answer) {
          answers_table += '<td class="text-center">' + results[count++] + '</td>';
        });
        answers_table += '</tr></tbody></table>';
        document.getElementById('content').innerHTML = '<h2>' + referendum.title + '</h2>' +
          '<div><small><b>Deadline:</b> ' + unix_time_to_text(referendum.deadline / 1000) +
          ' &mdash; <b>Area:</b> <a target="_blank" href="' + area_url + '">' + area_name +
          '</a> (' + area_type + ')' + '</small></div><br><div><p>' + referendum.description + '</p></div><div><p><b>' +
          referendum.question + '</b><p></div>' + answers_table + '<div>referendum=' +
          encodeURIComponent(referendum.key) + '</div>';
      }
    }
  };
  xhttp.open('POST', publisher + '/publication.php', true);
  xhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhttp.send('fingerprint=' + fingerprint);
};
