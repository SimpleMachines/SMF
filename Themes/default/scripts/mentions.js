var config = {
	at: '@',
	data: [],
	show_the_at: true,
	limit: 10,
	callbacks: {
		remote_filter: function (query, callback) {
			if (query.length < 2)
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
	$('textarea[name=message]').atwho(config);

	$('#message').parent().find('textarea').atwho(config);
	var iframe = $('#message').parent().find('iframe')[0];
	if (typeof iframe != 'undefined')
		$(iframe[0].contentDocument.body).atwho(config);
});