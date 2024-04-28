function smfRegister(formID, passwordDifficultyLevel, regTextStrings)
{
	this.addVerify = addVerificationField;
	this.autoSetup = autoSetup;
	this.refreshMainPassword = refreshMainPassword;
	this.refreshVerifyPassword = refreshVerifyPassword;

	var verificationFields = {};
	var verificationFieldLength = 0;
	var textStrings = regTextStrings || [];
	var passwordLevel = passwordDifficultyLevel || 0;

	// Setup all the fields!
	autoSetup(formID);

	// This is a field which requires some form of verification check.
	function addVerificationField(fieldType, inputHandle)
	{
		var divHandle;
		if (fieldType == 'username' || fieldType == 'pwmain' || fieldType == 'pwverify')
		{
			divHandle = document.createElement('span');
			inputHandle.after(divHandle);
		}

		var eventHandler;
		if (fieldType == 'pwmain')
			eventHandler = refreshMainPassword;
		else if (fieldType == 'pwverify')
			eventHandler = refreshVerifyPassword;
		else if (fieldType == 'username')
			eventHandler = refreshUsername;
		else if (fieldType == 'reserved')
			eventHandler = refreshMainPassword;

		var vFieldIndex = fieldType == 'reserved' ? fieldType + verificationFieldLength++ : fieldType;
		verificationFields[vFieldIndex] = [fieldType, inputHandle, divHandle];

		if (eventHandler)
		{
			inputHandle.addEventListener('keyup', eventHandler, false);
			eventHandler();

			// Username will auto check on blur!
			if (fieldType == 'username')
				inputHandle.addEventListener('blur', autoCheckUsername, false);
		}
	}

	// A button to trigger a username search?
	function addUsernameSearchTrigger(elementID)
	{
		var buttonHandle = document.getElementById(elementID);
		buttonHandle.addEventListener('click', checkUsername, false);
	}

	// This function will automatically pick up all the necessary verification fields and initialise their visual status.
	function autoSetup(formID)
	{
		if (!document.forms[formID])
			return false;

		for (var curElement of document.forms[formID].elements)
		{
			var curType = curEleent.dataset.autov;
			if (curType && (curElement.type == 'text' || curElement.type == 'password'))
			{
				addVerificationField(curType, curElement);

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
		var stringIndex = isPasswordValid(curPass);

		setVerificationImage(verificationFields['pwmain'][2], stringIndex == '', textStrings[stringIndex || 'password_valid'] || '');

		// As this has changed the verification one may have too!
		if (verificationFields['pwverify'])
			refreshVerifyPassword();

		return stringIndex == '';
	}

	function isPasswordValid(curPass)
	{
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
			for (var field of verificationFields)
			{
				if ((field[0] == 'reserved' || field[0] == 'reserved') && field[1].value && curPass.indexOf(field[1].value) != -1)
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

		return stringIndex;
	}

	// Check that the verification password matches the main one!
	function refreshVerifyPassword()
	{
		// Can't do anything without something to check again!
		if (!verificationFields['pwmain'])
			return false;

		// Check and set valid status!
		var curPass = verificationFields['pwmain'][1].value;
		var isValid = curPass == verificationFields['pwverify'][1].value && isPasswordValid(curPass) === '';
		var alt = textStrings[isValid ? 'password_valid' : 'password_no_match'] || '';
		setVerificationImage(verificationFields['pwverify'][2], isValid, alt);

		return true;
	}

	// If the username is changed just revert the status of whether it's valid!
	function refreshUsername()
	{
		if (!verificationFields['username'])
			return false;

		var alt = textStrings['username_check'] || '';
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

		// Request a search on that username.
		checkName = curUsername.php_to8bit().php_urlencode();
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=signup;sa=usernamecheck;xml;username=' + checkName, checkUsernameCallback);

		return true;
	}

	// Callback for getting the username data.
	function checkUsernameCallback(XMLDoc)
	{
		var
			tags = XMLDoc.getElementsByTagName("username"),
			isValid = tags && tags[0].getAttribute("valid") == 1,
			alt = textStrings[isValid ? 'username_valid' : 'username_invalid'];

		setVerificationImage(verificationFields['username'][2], isValid, alt);
	}

	// Set the image to be the correct type.
	function setVerificationImage(el, isValid, alt)
	{
		el.previousSibling.className = isValid && isValid !== 'check' ? 'valid' : 'invalid';
		el.textContent = isValid ? (isValid == 'check' ? '⚠️' : '✔') : '❌';
		el.title = alt || '*';
	}
}

function onCheckChange()
{
	if (document.forms.postForm.emailActivate.checked || document.forms.postForm.password.value == '')
	{
		document.forms.postForm.emailPassword.disabled = true;
		document.forms.postForm.emailPassword.checked = true;
	}
	else
		document.forms.postForm.emailPassword.disabled = false;
}