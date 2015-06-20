var laststate_alerts = true; // Begin with checked state.
function toggleAlerts()
{
	$("[name^=alert_]").each(function (key)
	{
		$(this).prop('checked', laststate_alerts);
	});
	laststate_alerts = !laststate_alerts;
}

var laststate_email = true;
function toggleMail()
{
	$("[name^=email_]").each(function (key)
	{
		$(this).prop('checked', laststate_email);
	});
	laststate_email = !laststate_email;
}