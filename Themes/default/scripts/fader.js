function smc_NewsFader(oOptions)
{
	var
		aFaderItems = oOptions.aFaderItems || [],
		iFadeIndex = 0,
		iFadeDelay = oOptions.iFadeDelay || 5000,
		iFadeSpeed = oOptions.iFadeSpeed || 650,
		sItemTemplate = oOptions.sItemTemplate || '%1$s',
		sControlId = '#' + oOptions.sFaderControlId,

		fadeIn = function ()
		{
			iFadeIndex++;
			if (iFadeIndex >= aFaderItems.length)
				iFadeIndex = 0;

			$(sControlId + ' li').html(sItemTemplate.replace('%1$s', aFaderItems[iFadeIndex])).fadeTo(iFadeSpeed, 0.99, function () {
				// Restore ClearType in IE.
				this.style.filter = '';
				fadeOut();
			});
		},

		fadeOut = function ()
		{
			setTimeout(function ()
			{
				$(sControlId + ' li').fadeTo(iFadeSpeed, 0, fadeIn);
			}, iFadeDelay);
		};

	if (!aFaderItems.length)
		$(sControlId + ' li').each(function ()
		{
			aFaderItems.push($(this).html());
		});

	if (aFaderItems.length > 1)
	{
		$(sControlId).html('<li>' + sItemTemplate.replace('%1$s', aFaderItems[0]) + '</li>').show();
		fadeOut();
	}
}