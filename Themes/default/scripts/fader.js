function smf_NewsFader(oOptions)
{
	this.opt = oOptions;

	this.oFaderHandle = document.getElementById(this.opt.sFaderControlId);

	// Surround each item with... anything special?
	this.sItemTemplate = 'sItemTemplate' in this.opt ? this.opt.sItemTemplate : '%1$s';

	// Fade delay (in milliseconds).
	this.iFadeDelay = 'iFadeDelay' in this.opt ? this.opt.iFadeDelay : 5000;

	// The array that contains all the lines of the news for display.
	this.aFaderItems = 'aFaderItems' in this.opt ? this.opt.aFaderItems : [];

	// Should we look for fader data, still?
	this.bReceivedItemsOnConstruction = 'aFaderItems' in this.opt;

	// The current item in smfFadeContent.
	this.iFadeIndex = -1;

	// Just make sure the page is loaded before calling the init.
	var fader = this;
	$(document).ready(function() {fader.init();});
}

smf_NewsFader.prototype.init = function init()
{
	var oForeEl, oForeColor, oBackEl, oBackColor;

	// Did we get our fader items on construction, or should we be gathering them instead?
	if (!this.bReceivedItemsOnConstruction)
	{
		// Get the news from the list in boardindex
		var oNewsItems = this.oFaderHandle.getElementsByTagName('li');

		// Fill the array that has previously been created
		for (var i = 0, n = oNewsItems.length; i < n; i ++)
			this.aFaderItems[i] = oNewsItems[i].innerHTML;
	}

	// Start the fader!
	this.fade();
}

// Main	fading function... called 50 times every second.
smf_NewsFader.prototype.fade = function fade()
{
	if (this.aFaderItems.length <= 1)
		return;

	var currentText;
	// Starting out?  Set up the first item.
	if (this.iFadeIndex == -1)
	{
		currentText = this.sItemTemplate.replace('%1$s', this.aFaderItems[0]);
		this.iFadeIndex = 1;
	}
	else
	{
		// Go to the next item, or first if we're out of items.
		currentText = this.sItemTemplate.replace('%1$s', this.aFaderItems[this.iFadeIndex ++]);
		if (this.iFadeIndex >= this.aFaderItems.length)
			this.iFadeIndex = 0;
	}

	$('#' + this.opt.sFaderControlId).each(function() {
		temp_elem = $(this).clone().css({height: 'auto'}).appendTo('body').html(currentText);
		final_height = parseInt(temp_elem.height()) + parseInt($(this).css('padding-top').replace(/[^-\d\.]/g, '')) + parseInt($(this).css('padding-bottom').replace(/[^-\d\.]/g, ''));
		temp_elem.remove();
		$(this).height($(this).height());
	}).html(currentText).animate({height: final_height}, 'slow');

	// Keep going.
	window.setTimeout(this.opt.sSelf + '.fade();', this.iFadeDelay);
}