const tributeRemoteSearch = (query, callback) => {
  const URL = smf_scripturl + '?action=suggest;' + smf_session_var + '=' + smf_session_id + ';xml';

  xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        const xmlDoc = xhr.responseXML;
        const items = xmlDoc.getElementsByTagName('item');
        const users = [];

        for (let i = 0; i < items.length; i++) {
          users.push({ key: items[i].id, value: items[i].textContent });
        }

        callback(users);
      } else if (xhr.status === 403) {
        callback([]);
      }
    }
  };
  xhr.open('GET', URL + ';suggest_type=member;search=' + query, true);
  xhr.setRequestHeader('X-SMF-AJAX', '1');
  xhr.withCredentials =
    typeof allow_xhjr_credentials !== 'undefined' ? allow_xhjr_credentials : false;
  xhr.send();
};

const tributeConfig = {
  values: function (query, callback) {
    tributeRemoteSearch(query, (users) => callback(users));
  },
  lookup: 'value',
  menuItemLimit: 10,
  noMatchTemplate: function () {
    return '<span style:"visibility: hidden;"></span>';
  },
};

const tribute = new Tribute(tributeConfig);
