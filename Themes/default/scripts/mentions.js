var fails = [];

var atwhoConfig = {
	at: '@',
	data: [],
	show_the_at: true,
	startWithSpace: true,
	limit: 10,
	callbacks: {
		remoteFilter: function (query, callback) {
			if (typeof query == 'undefined' || query.length < 2 || query.length > 60)
				return;

			for (i in fails)
				if (query.substr(0, fails[i].length) == fails[i])
					return;

			$.ajax({
				url: smf_scripturl + '?action=suggest;' + smf_session_var + '=' + smf_session_id + ';xml',
				method: 'GET',
				headers: {
					"X-SMF-AJAX": 1
				},
				xhrFields: {
					withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
				},
				data: {
					search: query,
					suggest_type: 'member'
				},
				success: function (data) {
					var members = $(data).find('smf > items > item');
					if (members.length == 0)
						fails[fails.length] = query;

					var callbackArray = [];
					$.each(members, function (index, item) {
						callbackArray[callbackArray.length] = {
							name: $(item).text()
						};
					});

					callback(callbackArray);
				}
			});
		}
	}
};
$(function()
{
	$('textarea[name=message]').atwho(atwhoConfig);
	$('.sceditor-container').find('textarea').atwho(atwhoConfig);
	var iframe = $('.sceditor-container').find('iframe')[0];
	if (typeof iframe != 'undefined')
		$(iframe.contentDocument.body).atwho(atwhoConfig);
});