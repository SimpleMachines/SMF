var fails = [];

var atwhoConfig = {
	at: '@',
	data: [],
	show_the_at: true,
	limit: 10,
	callbacks: {
		matcher: function(flag, subtext, should_start_with_space) {
			var match = '', started = false;
			var string = subtext.split('');
			for (var i = 0; i < string.length; i++)
			{
				if (string[i] == flag && (!should_start_with_space || i == 0 || /[\s\n]/gi.test(string[i - 1])))
				{
					started = true;
					match = '';
				}
				else if (started)
					match = match + string[i];
			}

			if (match.length > 0)
				return match;

			return null;
		},
		remoteFilter: function (query, callback) {
			if (typeof query == 'undefined' || query.length < 2 || query.length > 60)
				return;

			for (i in fails)
				if (query.substr(0, fails[i].length) == fails[i])
					return;

			$.ajax({
				url: smf_scripturl + '?action=suggest;' + smf_session_var + '=' + smf_session_id + ';xml',
				method: 'GET',
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
