$(document).ready(function() {
	// menu drop downs
	$('ul.dropmenu, ul.quickbuttons').superfish({delay : 600, speed: 200, sensitivity : 8, interval : 50, timeout : 1}); 
	
	// tooltips
	$('.preview').SMFtooltip();

	// find all nested linked images and turn off the border
	$('a.bbc_link img.bbc_img').parent().css('border', '0');
	
	// Allow us to click that lil button to collapse and expand stuff.
	toggleElementEvent("#inner_wrap", "#upshrink", null, header_is_collapsed);
});

// Toggle an element.
function toggleElement(element, image, imageoptions, duration)
{
	if ($(element).length == 0 || $(image).length == 0)
		return;
	
	imageoptions = {
		visibleimage: imageoptions && imageoptions.visibleimage || smf_images_url + "/upshrink.png",
		hideimage: imageoptions && imageoptions.hideimage || smf_images_url + "/upshrink2.png",
		visibletxt: imageoptions && imageoptions.visibletxt || hide_txt,
		hidetxt: imageoptions && imageoptions.hidetxt || show_txt,
		isimage: imageoptions && !imageoptions.isimage ? false : true,
		eimage: imageoptions && imageoptions.eimage || null
	};
	
	$(element).slideToggle(duration || "fast", "linear", function()
	{
		if (imageoptions.isimage || (!imageoptions.isimage && imageoptions.eimage))
		{
			mimage = (imageoptions.isimage === true ? image : imageoptions.eimage);
			if ($(element).is(":visible"))
			{
				$(mimage).attr("src", imageoptions.visibleimage);
				$(mimage).attr("title", imageoptions.visibletxt);
			}
			else
			{
				$(mimage).attr("src", imageoptions.hideimage);	
				$(mimage).attr("title", imageoptions.hidetxt);
			}
		}
	});
}

// Add the event for toggleElement.
function toggleElementEvent(element, image, imageoptions, iscollapsed)
{
	if ($(element).length == 0 || $(image).length == 0)
		return;
		
	imageoptions = {
		visibleimage: imageoptions && imageoptions.visibleimage || smf_images_url + "/upshrink.png",
		hideimage: imageoptions && imageoptions.hideimage || smf_images_url + "/upshrink2.png",
		visibletxt: imageoptions && imageoptions.visibletxt || hide_txt,
		hidetxt: imageoptions && imageoptions.hidetxt || show_txt,
		isimage: imageoptions && !imageoptions.isimage ? false : true,
		eimage: imageoptions && imageoptions.eimage || null
	};
		
	$(image).click(function ()
	{
		toggleElement(element, image, imageoptions);
	});
	
	// Add a nice pointer to this image if we got one.
	if (imageoptions.isimage)
		$(image).addClass("pointer");
	
	// If it is collapsed, toggle it now.
	if (iscollapsed)
		toggleElement(element, image, imageoptions, 0.1);
}

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
	$('#' + stripId + ' ul').append(
		'<li' + ('sId' in options ? ' id="' + options.sId + '"' : '') + '>' +
			'<a href="' + options.sUrl + '"' + ('sCustom' in options ? options.sCustom : '') + '>' +
				'<span class="last"' + ('sId' in options ? ' id="' + options.sId + '_text"' : '') + '>' + options.sText + '</span>' +
			'</a>' +
		'</li>'
	);
}