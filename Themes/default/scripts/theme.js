$(document).ready(function() {
	// menu drop downs
	$('ul.dropmenu, ul.quickbuttons').superfish({delay : 600, speed: 200, sensitivity : 8, interval : 50, timeout : 1}); 
	
	// tooltips
	$('.preview').SMFtooltip();

	// find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');
});

// The purpose of this code is to fix the height of overflow: auto blocks, because some browsers can't figure it out for themselves.
function smf_codeBoxFix()
{
	var codeFix = $('code');
	$.each(codeFix, function(index, tag)
	{
		if (is_webkit && $(tag).height() < 20)
			$(tag).css({height: ($(tag).height + 20) + 'px'});

		else if (is_ff && ($(tag)[0].scrollWidth > $(tag).innerWidth() || $(tag).innerWidth() == 0))
			$(tag).css({overflow: 'scroll'});

		// Holy conditional, Batman!
		else if (
			'currentStyle' in $(tag) && $(tag)[0].currentStyle.overflow == 'auto'
			&& ($(tag).innerHeight() == '' || $(tag).innerHeight() == 'auto')
			&& ($(tag)[0].scrollWidth > $(tag).innerWidth() || $(tag).innerWidth == 0)
			&& ($(tag).outerHeight() != 0)			
		)
			$(tag).css({height: ($(tag).height + 24) + 'px'});
	});
}

// Add a fix for code stuff?
if (is_ie || is_webkit || is_ff)
	addLoadEvent(smf_codeBoxFix);

// Toggles the element height and width styles of an image.
function smc_toggleImageDimensions()
{
	var images = $('img.bbc_img');

	$.each(images, function(key, img)
	{
		if ($(img).hasClass('resized'))
		{
			$(img).css({cursor: 'pointer'});
			$(img).on('click', function()
			{
				var size = $(this)[0].style.width == 'auto' ? '' : 'auto';
				$(this).css({width: size, height: size});
			});
		}
	});
}

// Add a load event for the function above.
addLoadEvent(smc_toggleImageDimensions);

function smf_addButton(stripId, image, options)
{
	$('#' + stripId + ' ul li').last().removeClass("last");

	$('#' + stripId + ' ul').append(
		'<li' + ('sId' in options ? ' id="' + options.sId + '"' : '') + '>' +
			'<a href="' + options.sUrl + '"' + ('sCustom' in options ? options.sCustom : '') + '>' +
				'<span class="last"' + ('sId' in options ? ' id="' + options.sId + '_text"' : '') + '>' + options.sText + '</span>' +
			'</a>' +
		'</li>'
	);
}
