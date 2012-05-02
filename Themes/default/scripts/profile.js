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
		
		if (currentChars > maxLength && !$("#profile_error").is(":visible"))
			ajax_getSignaturePreview(false);
		else if (currentChars <= maxLength && $("#profile_error").is(":visible"))
		{
			$("#profile_error").css({display:"none"});
			$("#profile_error").html('');
		}
	}

	setInnerHTML(document.getElementById("signatureLeft"), maxLength - currentChars);
}

function ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;
	$.ajax({
		type: "POST",
		url: smf_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				var signatures = new Array("current", "preview");
				for (var i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display:""});
					$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr />');
				}
			}

			if ($(request).find("error").text() != '')
			{
				if (!$("#profile_error").is(":visible"))
					$("#profile_error").css({display: "", position: "fixed", top: 0, left: 0, width: "100%"});
				var errors = $(request).find('[type="error"]');
				var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul class="reset">';

				for (var i = 0; i < errors.length; i++)
					errors_html += '<li>' + $(errors).text() + '</li>';

				errors_html += '</ul>';
				$(document).find("#profile_error").html(errors_html);
			}
			else
			{
				$("#profile_error").css({display:"none"});
				$("#profile_error").html('');
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
	if (!document.getElementById("avatar"))
		return;

	var tempImage = new Image();

	tempImage.src = src;
	if (maxWidth != 0 && tempImage.width > maxWidth)
	{
		document.getElementById("avatar").style.height = parseInt((maxWidth * tempImage.height) / tempImage.width) + "px";
		document.getElementById("avatar").style.width = maxWidth + "px";
	}
	else if (maxHeight != 0 && tempImage.height > maxHeight)
	{
		document.getElementById("avatar").style.width = parseInt((maxHeight * tempImage.width) / tempImage.height) + "px";
		document.getElementById("avatar").style.height = maxHeight + "px";
	}
	document.getElementById("avatar").src = src;
}
