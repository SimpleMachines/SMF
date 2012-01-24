function smfRegister(formID, passwordDifficultyLevel, regTextStrings)
{
	this.addVerify = addVerificationField;
	this.autoSetup = autoSetup;
	this.refreshMainPassword = refreshMainPassword;
	this.refreshVerifyPassword = refreshVerifyPassword;

	var verificationFields = new Array();
	var verificationFieldLength = 0;
	var textStrings = regTextStrings ? regTextStrings : new Array();
	var passwordLevel = passwordDifficultyLevel ? passwordDifficultyLevel : 0;

	// Setup all the fields!
	autoSetup(formID);

	// This is a field which requires some form of verification check.
	function addVerificationField(fieldType, fieldID)
	{
		// Check the field exists.
		if (!document.getElementById(fieldID))
			return;

		// Get the handles.
		var inputHandle = document.getElementById(fieldID);
		var imageHandle = document.getElementById(fieldID + '_img') ? document.getElementById(fieldID + '_img') : false;
		var divHandle = document.getElementById(fieldID + '_div') ? document.getElementById(fieldID + '_div') : false;

		// What is the event handler?
		var eventHandler = false;
		if (fieldType == 'pwmain')
			eventHandler = refreshMainPassword;
		else if (fieldType == 'pwverify')
			eventHandler = refreshVerifyPassword;
		else if (fieldType == 'username')
			eventHandler = refreshUsername;
		else if (fieldType == 'reserved')
			eventHandler = refreshMainPassword;

		// Store this field.
		var vFieldIndex = fieldType == 'reserved' ? fieldType + verificationFieldLength : fieldType;
		verificationFields[vFieldIndex] = Array(6);
		verificationFields[vFieldIndex][0] = fieldID;
		verificationFields[vFieldIndex][1] = inputHandle;
		verificationFields[vFieldIndex][2] = imageHandle;
		verificationFields[vFieldIndex][3] = divHandle;
		verificationFields[vFieldIndex][4] = fieldType;
		verificationFields[vFieldIndex][5] = inputHandle.className;

		// Keep a count to it!
		verificationFieldLength++;

		// Step to it!
		if (eventHandler)
		{
			createEventListener(inputHandle);
			inputHandle.addEventListener('keyup', eventHandler, false);
			eventHandler();

			// Username will auto check on blur!
			inputHandle.addEventListener('blur', autoCheckUsername, false);
		}

		// Make the div visible!
		if (divHandle)
			divHandle.style.display = '';
	}

	// A button to trigger a username search?
	function addUsernameSearchTrigger(elementID)
	{
		var buttonHandle = document.getElementById(elementID);

		// Attach the event to this element.
		createEventListener(buttonHandle);
		buttonHandle.addEventListener('click', checkUsername, false);
	}

	// This function will automatically pick up all the necessary verification fields and initialise their visual status.
	function autoSetup(formID)
	{
		if (!document.getElementById(formID))
			return false;

		var curElement, curType;
		for (var i = 0, n = document.getElementById(formID).elements.length; i < n; i++)
		{
			curElement = document.getElementById(formID).elements[i];

			// Does the ID contain the keyword 'autov'?
			if (curElement.id.indexOf('autov') != -1 && (curElement.type == 'text' || curElement.type == 'password'))
			{
				// This is probably it - but does it contain a field type?
				curType = 0;
				// Username can only be done with XML.
				if (curElement.id.indexOf('username') != -1 && window.XMLHttpRequest)
					curType = 'username';
				else if (curElement.id.indexOf('pwmain') != -1)
					curType = 'pwmain';
				else if (curElement.id.indexOf('pwverify') != -1)
					curType = 'pwverify';
				// This means this field is reserved and cannot be contained in the password!
				else if (curElement.id.indexOf('reserve') != -1)
					curType = 'reserved';

				// If we're happy let's add this element!
				if (curType)
					addVerificationField(curType, curElement.id);

				// If this is the username do we also have a button to find the user?
				if (curType == 'username' && document.getElementById(curElement.id + '_link'))
				{
					addUsernameSearchTrigger(curElement.id + '_link');
				}
			}
		}

		return true;
	}

	// What is the password state?
	function refreshMainPassword(called_from_verify)
	{
		if (!verificationFields['pwmain'])
			return false;

		var curPass = verificationFields['pwmain'][1].value;
		var stringIndex = '';

		// Is it a valid length?
		if ((curPass.length < 8 && passwordLevel >= 1) || curPass.length < 4)
			stringIndex = 'password_short';

		// More than basic?
		if (passwordLevel >= 1)
		{
			// If there is a username check it's not in the password!
			if (verificationFields['username'] && verificationFields['username'][1].value && curPass.indexOf(verificationFields['username'][1].value) != -1)
				stringIndex = 'password_reserved';

			// Any reserved fields?
			for (var i in verificationFields)
			{
				if (verificationFields[i][4] == 'reserved' && verificationFields[i][1].value && curPass.indexOf(verificationFields[i][1].value) != -1)
					stringIndex = 'password_reserved';
			}

			// Finally - is it hard and as such requiring mixed cases and numbers?
			if (passwordLevel > 1)
			{
				if (curPass == curPass.toLowerCase())
					stringIndex = 'password_numbercase';
				if (!curPass.match(/(\D\d|\d\D)/))
					stringIndex = 'password_numbercase';
			}
		}

		var isValid = stringIndex == '' ? true : false;
		if (stringIndex == '')
			stringIndex = 'password_valid';

		// Set the image.
		setVerificationImage(verificationFields['pwmain'][2], isValid, textStrings[stringIndex] ? textStrings[stringIndex] : '');
		verificationFields['pwmain'][1].className = verificationFields['pwmain'][5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

		// As this has changed the verification one may have too!
		if (verificationFields['pwverify'] && !called_from_verify)
			refreshVerifyPassword();

		return isValid;
	}

	// Check that the verification password matches the main one!
	function refreshVerifyPassword()
	{
		// Can't do anything without something to check again!
		if (!verificationFields['pwmain'])
			return false;

		// Check and set valid status!
		var isValid = verificationFields['pwmain'][1].value == verificationFields['pwverify'][1].value && refreshMainPassword(true);
		var alt = textStrings[isValid == 1 ? 'password_valid' : 'password_no_match'] ? textStrings[isValid == 1 ? 'password_valid' : 'password_no_match'] : '';
		setVerificationImage(verificationFields['pwverify'][2], isValid, alt);
		verificationFields['pwverify'][1].className = verificationFields['pwverify'][5] + ' ' + (isValid ? 'valid_input' : 'invalid_input');

		return true;
	}

	// If the username is changed just revert the status of whether it's valid!
	function refreshUsername()
	{
		if (!verificationFields['username'])
			return false;

		// Restore the class name.
		if (verificationFields['username'][1].className)
			verificationFields['username'][1].className = verificationFields['username'][5];
		// Check the image is correct.
		var alt = textStrings['username_check'] ? textStrings['username_check'] : '';
		setVerificationImage(verificationFields['username'][2], 'check', alt);

		// Check the password is still OK.
		refreshMainPassword();

		return true;
	}

	// This is a pass through function that ensures we don't do any of the AJAX notification stuff.
	function autoCheckUsername()
	{
		checkUsername(true);
	}

	// Check whether the username exists?
	function checkUsername(is_auto)
	{
		if (!verificationFields['username'])
			return false;

		// Get the username and do nothing without one!
		var curUsername = verificationFields['username'][1].value;
		if (!curUsername)
			return false;

		if (!is_auto)
			ajax_indicator(true);

		// Request a search on that username.
		checkName = curUsername.php_to8bit().php_urlencode();
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=register;sa=usernamecheck;xml;username=' + checkName, checkUsernameCallback);

		return true;
	}

	// Callback for getting the username data.
	function checkUsernameCallback(XMLDoc)
	{
		if (XMLDoc.getElementsByTagName("username"))
			isValid = XMLDoc.getElementsByTagName("username")[0].getAttribute("valid");
		else
			isValid = true;

		// What to alt?
		var alt = textStrings[isValid == 1 ? 'username_valid' : 'username_invalid'] ? textStrings[isValid == 1 ? 'username_valid' : 'username_invalid'] : '';

		verificationFields['username'][1].className = verificationFields['username'][5] + ' ' + (isValid == 1 ? 'valid_input' : 'invalid_input');
		setVerificationImage(verificationFields['username'][2], isValid == 1, alt);

		ajax_indicator(false);
	}

	// Set the image to be the correct type.
	function setVerificationImage(imageHandle, imageIcon, alt)
	{
		if (!imageHandle)
			return false;
		if (!alt)
			alt = '*';

		var curImage = imageIcon ? (imageIcon == 'check' ? 'field_check.gif' : 'field_valid.gif') : 'field_invalid.gif';
		imageHandle.src = smf_images_url + '/icons/' + curImage;
		imageHandle.alt = alt;
		imageHandle.title = alt;

		return true;
	}
}