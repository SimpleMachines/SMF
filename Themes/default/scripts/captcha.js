// This file contains javascript associated with the captcha visual verification stuffs.

function smfCaptcha(imageURL, uniqueID, useLibrary, letterCount)
{
	// By default the letter count is five.
	if (!letterCount)
		letterCount = 5;

	uniqueID = uniqueID ? '_' + uniqueID : '';
	autoCreate();

	// Automatically get the captcha event handlers in place and the like.
	function autoCreate()
	{
		// Is there anything to cycle images with - if so attach the refresh image function?
		var cycleHandle = document.getElementById('visual_verification' + uniqueID + '_refresh');
		if (cycleHandle)
		{
			createEventListener(cycleHandle);
			cycleHandle.addEventListener('click', refreshImages, false);
		}

		// Maybe a voice is here to spread light?
		var soundHandle = document.getElementById('visual_verification' + uniqueID + '_sound');
		if (soundHandle)
		{
			createEventListener(soundHandle);
			soundHandle.addEventListener('click', playSound, false);
		}
	}

	// Change the images.
	function refreshImages()
	{
		// Make sure we are using a new rand code.
		var new_url = new String(imageURL);
		new_url = new_url.substr(0, new_url.indexOf("rand=") + 5);

		// Quick and dirty way of converting decimal to hex
		var hexstr = "0123456789abcdef";
		for(var i=0; i < 32; i++)
			new_url = new_url + hexstr.substr(Math.floor(Math.random() * 16), 1);

		if (useLibrary && document.getElementById("verification_image" + uniqueID))
		{
			document.getElementById("verification_image" + uniqueID).src = new_url;
		}
		else if (document.getElementById("verification_image" + uniqueID))
		{
			for (i = 1; i <= letterCount; i++)
				if (document.getElementById("verification_image" + uniqueID + "_" + i))
					document.getElementById("verification_image" + uniqueID + "_" + i).src = new_url + ";letter=" + i;
		}

		return false;
	}

	// Request a sound... play it Mr Soundman...
	function playSound(ev)
	{
		if (!ev)
			ev = window.event;

		popupFailed = reqWin(imageURL + ";sound", 400, 120);
		// Don't follow the link if the popup worked, which it would have done!
		if (!popupFailed)
		{
			if (is_ie && ev.cancelBubble)
				ev.cancelBubble = true;
			else if (ev.stopPropagation)
			{
				ev.stopPropagation();
				ev.preventDefault();
			}
		}

		return popupFailed;
	}
}