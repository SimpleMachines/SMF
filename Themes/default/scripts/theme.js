$(function() {
	$('ul.dropmenu, ul.quickbuttons').superfish({delay : 250, speed: 100, sensitivity : 8, interval : 50, timeout : 1});

	// tooltips
	$('.preview').SMFtooltip();

	// find all nested linked images and turn off the border
	$('a.bbc_link img').parent().css('border', '0');
});

// Toggles the element height and width styles of an image.
function smc_toggleImageDimensions()
{
	var oImages = document.getElementsByTagName('IMG');
	for (oImage in oImages)
	{
		// Not a resized image? Skip it.
		if (oImages[oImage].className == undefined || oImages[oImage].className.indexOf('bbc_img resized') == -1)
			continue;

		oImages[oImage].addEventListener('click', function()
		{
			this.classList.toggle('original_size');
		});
	}
}

// Add a load event for the function above.
window.addEventListener("load", smc_toggleImageDimensions);

// Disable all fields on form submit.
window.addEventListener('load', function () {
	for (const form of document.forms) {
		form.addEventListener('submit', function () {
			submitonce(this);
		});
	}
});

// When using Go Back due to fatal_error, allow the form to be re-submitted with changes.
if (is_ff) {
	window.addEventListener("pageshow", function () {
		for (const form of document.forms) {
			reActivateThis(form);
		}
	});
}

// Adds a button to a certain button strip.
function smf_addButton(sButtonStripId, bUseImage, oOptions)
{
	var oButtonStrip = document.getElementById(sButtonStripId);
	var oNewButton = document.createElement("a");
	oNewButton.href = oOptions.sUrl;
	oNewButton.textContent = oOptions.sText;
	oNewButton.className = 'button';

	if (oOptions.sId)
		oNewButton.id = oOptions.sId;

	if (oOptions.aEvents)
		oOptions.aEvents.forEach(function (e)
		{
			oNewButton.addEventListener(e[0], e[1]);
		});
	oButtonStrip.appendChild(oNewButton);

	return oNewButton;
}