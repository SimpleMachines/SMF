var localTime = new Date();
function autoDetectTimeOffset(currentTime)
{
	if (typeof(currentTime) != 'string')
		var serverTime = currentTime;
	else
		var serverTime = new Date(currentTime);

	// Something wrong?
	if (!localTime.getTime() || !serverTime.getTime())
		return 0;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of who.
	var diff = Math.round((localTime.getTime() - serverTime.getTime())/3600000);

	// Make sure we are limiting this to one day's difference.
	diff %= 24;

	return diff;
}

// Prevent Chrome from auto completing fields when viewing/editing other members profiles
function disableAutoComplete()
{
	if (is_chrome && document.addEventListener)
		document.addEventListener("DOMContentLoaded", disableAutoCompleteNow, false);
}

// Once DOMContentLoaded is triggered, call the function
function disableAutoCompleteNow()
{
	for (var i = 0, n = document.forms.length; i < n; i++)
	{
		var die = document.forms[i].elements;
		for (var j = 0, m = die.length; j < m; j++)
			// Only bother with text/password fields?
			if (die[j].type == "text" || die[j].type == "password")
				die[j].setAttribute("autocomplete", "off");
	}
}

/*
 * How long a signature is allowed to be. The template used to write this out
 * as a global of its own; it comes off the textarea now, so the number only
 * exists in one place.
 */
var maxLength = 0;

// Wires up the signature editor, which is on the Forum Profile page.
document.addEventListener('DOMContentLoaded', function ()
{
	var signature = document.getElementById('signature');

	if (!signature)
		return;

	maxLength = Number(signature.dataset.maxLength || 0);

	// "input" rather than the old "keyup", so pasting counts too.
	signature.addEventListener('input', calcCharLeft);
	calcCharLeft();

	var preview = document.getElementById('preview_button');

	if (preview)
		preview.addEventListener('click', function () {
			return ajax_getSignaturePreview(true);
		});
});

function calcCharLeft()
{
	var oldSignature = "", currentSignature = document.forms.creator.signature.value;
	var currentChars = 0;

	if (!document.getElementById("signatureLeft"))
		return;

	if (oldSignature != currentSignature)
	{
		oldSignature = currentSignature;

		var currentChars = currentSignature.replace(/\r/, "").length;
		if (is_opera)
			currentChars = currentSignature.replace(/\r/g, "").length;

		if (currentChars > maxLength)
			document.getElementById("signatureLeft").className = "error";
		else
			document.getElementById("signatureLeft").className = "";

		if (currentChars > maxLength)
			ajax_getSignaturePreview(false);
		// Only hide it if the only errors were signature errors...
		else if (currentChars <= maxLength)
		{
			// Are there any errors to begin with?
			if ($(document).has("#list_errors"))
			{
				// Remove any signature errors
				$("#list_errors").remove(".sig_error");

				// Don't hide this if other errors remain
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		}
	}

	setInnerHTML(document.getElementById("signatureLeft"), maxLength - currentChars);
}

function ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;

	// Is the error box already visible?
	var errorbox_visible = $("#profile_error").is(":visible");

	$.ajax({
		type: "POST",
		url: smf_scripturl + "?action=xmlhttp;sa=previews;xml",
		headers: {
			"X-SMF-AJAX": 1
		},
		xhrFields: {
			withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
		},
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				var signatures = new Array("current", "preview");
				for (var i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display:""});
					$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr>');
				}
			}

			if ($(request).find("error").text() != '')
			{
				// If the box isn't already visible...
				// 1. Add the initial HTML
				// 2. Make it visible
				if (!errorbox_visible)
				{
					// Build our HTML...
					var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul id="list_errors"></ul>';

					// Add it to the box...
					$("#profile_error").html(errors_html);

					// Make it visible
					$("#profile_error").css({display: ""});
				}
				else
				{
					// Remove any existing signature-related errors...
					$("#list_errors").remove(".sig_error");
				}

				var errors = $(request).find('[type="error"]');
				var errors_list = '';

				for (var i = 0; i < errors.length; i++)
					errors_list += '<li class="sig_error">' + $(errors).text() + '</li>';

				$("#list_errors").html(errors_list);
			}
			// If there were more errors besides signature-related ones, don't hide it
			else
			{
				// Remove any signature errors first...
				$("#list_errors").remove(".sig_error");

				// If it still has content, there are other non-signature errors...
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		return false;
		},
	});
	return false;
}

function changeSel(selected)
{
	if (cat.selectedIndex == -1)
		return;

	if (cat.options[cat.selectedIndex].value.indexOf("/") > 0)
	{
		var i;
		var count = 0;

		file.style.display = "inline";
		file.disabled = false;

		for (i = file.length; i >= 0; i = i - 1)
			file.options[i] = null;

		for (i = 0; i < files.length; i++)
			if (files[i].indexOf(cat.options[cat.selectedIndex].value) == 0)
			{
				var filename = files[i].substr(files[i].indexOf("/") + 1);
				var showFilename = filename.substr(0, filename.lastIndexOf("."));
				showFilename = showFilename.replace(/[_]/g, " ");

				file.options[count] = new Option(showFilename, files[i]);

				if (filename == selected)
				{
					if (file.options.defaultSelected)
						file.options[count].defaultSelected = true;
					else
						file.options[count].selected = true;
				}

				count++;
			}

		if (file.selectedIndex == -1 && file.options[0])
			file.options[0].selected = true;

		showAvatar();
	}
	else
	{
		file.style.display = "none";
		file.disabled = true;
		document.getElementById("avatar").src = avatardir + cat.options[cat.selectedIndex].value;
		document.getElementById("avatar").style.width = "";
		document.getElementById("avatar").style.height = "";
	}
}

function showAvatar()
{
	if (file.selectedIndex == -1)
		return;

	document.getElementById("avatar").src = avatardir + file.options[file.selectedIndex].value;
	document.getElementById("avatar").alt = file.options[file.selectedIndex].text;
	document.getElementById("avatar").alt += file.options[file.selectedIndex].text == size ? "!" : "";
	document.getElementById("avatar").style.width = "";
	document.getElementById("avatar").style.height = "";
}

function previewExternalAvatar(src)
{
	if ($('#external_avatar_img_new').length){
		$('#external_avatar_img_new').remove();
	}

	var newImage = $('<img />', {
		id: 'external_avatar_img_new',
		src: src,
		class: 'avatar',
	});

	newImage.appendTo($('#avatar_external'));
}

function readfromUpload(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();

		reader.onload = function (e) {

			// If there is an image already, hide it...
			if ($('#attached_image').length){
				$('#attached_image').remove();
			}

			if ($('#attached_image_new').length){
				$('#attached_image_new').remove();
			}

			var tempImage = new Image();
				tempImage.src = e.target.result;

			var uploadedImage = $('<img />', {
				id: 'attached_image_new',
				src: e.target.result,
				class: 'avatar',
			});

			uploadedImage.appendTo($('#avatar_upload'));
		}

		reader.readAsDataURL(input.files[0]);
	}
}

// The smiley set selector code
$(document).on('change', '#smiley_set', function () {
	$("#smileypr").attr("src", $('#smiley_set option:selected').data('preview'));
});

function changeVariant(iThemeId, el)
{
	document.getElementById('theme_thumb_' + iThemeId).src = oThemeVariants[iThemeId][el.value]['thumbnail'];
	document.getElementById('theme_thumb_preview_' + iThemeId).href = el.form.action + ';theme=' + iThemeId + ';variant=' + el.value;
	document.getElementById('theme_preview_' + iThemeId).href = el.form.action + ';theme=' + iThemeId + ';variant=' + el.value;
}

$(document).on('change', '#export_format_select', function() {
	var selected_format = $('#export_format_select').val();
	if (completed_formats.indexOf(selected_format) > -1)
	{
		$('#export_begin').hide();
		$('#export_begin input').prop('disabled', true);
		$('#export_restart').show();
		$('#export_restart input').prop('disabled', false);
	} else {
		$('#export_begin').show();
		$('#export_begin input').prop('disabled', false);
		$('#export_restart').hide();
		$('#export_restart input').prop('disabled', true);
	}
});

$(document).ready(function() {
	$(".export_download_all").show();
});

function export_download_all(format)
{
	$('#' + format + '_export_files a').each(function(index, element) {
		// Add an invisible iframe pointing to the file.
		var iframe = $('<iframe style="visibility: collapse;"></iframe>');
		iframe[0].src = $(element).attr('href');
		$('body').append(iframe);

		// Give plenty of time for the download to complete, then clean up.
		setTimeout(function() { iframe.remove(); }, 30000);
	});
}

/*
 * Everything below belongs to the "issue a warning" page. It used to be
 * generated into the template, which meant the notification templates were
 * written out as one JavaScript branch each. They arrive as data now:
 * notification_templates holds the bodies in the order the select lists them,
 * level_effects maps each warning level to what it does at that level.
 */
document.addEventListener('DOMContentLoaded', function ()
{
	var slider = document.getElementById('warning_level');

	// Viewing your own warning level shows the slider and nothing else, so
	// this half stands on its own.
	if (slider)
	{
		slider.addEventListener('input', updateSlider);
	}

	var notify = document.getElementById('warn_notify');

	if (!notify)
		return;

	notify.addEventListener('change', modifyWarnNotify);
	document.getElementById('warn_temp').addEventListener('change', populateNotifyTemplate);
	document.getElementById('preview_button').addEventListener('click', ajax_getTemplatePreview);

	// The notification fields start out matching the checkbox.
	modifyWarnNotify.call(notify);
});

// The notification is optional, so its fields follow the checkbox.
function modifyWarnNotify()
{
	var disable = !document.getElementById('warn_notify').checked;

	document.getElementById('warn_sub').disabled = disable;
	document.getElementById('warn_body').disabled = disable;
	document.getElementById('warn_temp').disabled = disable;
	document.getElementById('new_template_link').hidden = disable;

	// Disabled rather than hidden, because .button sets display and an author
	// rule beats the [hidden] one the browser brings.
	document.getElementById('preview_button').disabled = disable;
}

// Picking a template drops its body into the message.
function populateNotifyTemplate()
{
	if (this.value == -1)
		return;

	document.getElementById('warn_body').value = notification_templates[this.value];
}

// Says what the level being dragged to actually does.
function updateSlider()
{
	// Number(), because both sides are strings otherwise and 100 sorts below
	// 35 when they are compared as text.
	var output = this.form.cur_level, level = Number(this.value), effect = '';

	for (var limit in level_effects)
		if (level >= Number(limit))
			effect = level_effects[limit];

	output.value = output.dataset.format.replace('{0}', this.value) + ' (' + effect + ')';
}

// Renders the notification body the way the member will receive it.
function ajax_getTemplatePreview()
{
	var data = new URLSearchParams({
		item: 'warning_preview',
		title: document.getElementById('warn_sub').value,
		body: document.getElementById('warn_body').value,
		issuing: true
	});

	fetch(smf_scripturl + '?action=xmlhttp;sa=previews;xml', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-SMF-AJAX': 1
		},
		credentials: typeof allow_xhjr_credentials !== 'undefined' && allow_xhjr_credentials ? 'include' : 'same-origin',
		body: data
	})
	.then(function (response) {
		return response.text();
	})
	.then(function (text) {
		var xml = new DOMParser().parseFromString(text, 'application/xml'),
			errors = xml.querySelectorAll('error'),
			problems = document.getElementById('profile_error');

		document.getElementById('box_preview').style.display = '';
		setInnerHTML(document.getElementById('body_preview'), xml.querySelector('body').textContent);

		if (!problems)
			return;

		problems.style.display = errors.length ? '' : 'none';

		var list = '';

		errors.forEach(function (error) {
			list += '<li>' + error.textContent + '</li>';
		});

		setInnerHTML(problems, list === '' ? '' : '<ul class="list_errors">' + list + '</ul>');
	});
}
