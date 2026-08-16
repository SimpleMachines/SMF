function smfRegister(formID, passwordDifficultyLevel, regTextStrings)
{
	this.addVerify = addVerificationField;
	this.autoSetup = autoSetup;
	this.refreshMainPassword = refreshMainPassword;
	this.refreshVerifyPassword = refreshVerifyPassword;

	var verificationFields = {};
	var reservedFieldCount = 0;
	var textStrings = regTextStrings || {};
	var passwordLevel = passwordDifficultyLevel || 0;

	// Setup all the fields!
	autoSetup(formID);

	// This is a field which requires some form of verification check.
	function addVerificationField(fieldType, inputHandle)
	{
		// What is the event handler?
		var eventHandler;

		if (fieldType == 'pwmain' || fieldType == 'reserved')
			eventHandler = refreshMainPassword;
		else if (fieldType == 'pwverify')
			eventHandler = refreshVerifyPassword;
		else if (fieldType == 'username')
			eventHandler = refreshUsername;

		/*
		 * A reserved field is only something the password may not contain - the
		 * email address, say - so it has nothing of its own to report. The other
		 * three each show an icon, which the templates used to write out hidden
		 * and which nothing outside this file ever addressed. Build it here.
		 */
		var iconHandle;

		if (fieldType != 'reserved')
		{
			iconHandle = document.createElement('span');
			iconHandle.className = 'main_icons';

			// The username icon doubles as the button that asks the server.
			if (fieldType == 'username')
			{
				var linkHandle = document.createElement('a');
				linkHandle.href = '#';
				linkHandle.appendChild(iconHandle);
				linkHandle.addEventListener('click', function (e) {
					e.preventDefault();
					checkUsername(false);
				}, false);

				inputHandle.after(linkHandle);
			}
			else
				inputHandle.after(iconHandle);
		}

		// Store this field.
		var vFieldIndex = fieldType == 'reserved' ? fieldType + reservedFieldCount++ : fieldType;

		verificationFields[vFieldIndex] = {
			type: fieldType,
			input: inputHandle,
			icon: iconHandle,
			className: inputHandle.className
		};

		// Step to it!
		if (eventHandler)
		{
			inputHandle.addEventListener('keyup', eventHandler, false);
			eventHandler();

			// Username will auto check on blur!
			if (fieldType == 'username')
				inputHandle.addEventListener('blur', autoCheckUsername, false);
		}
	}

	// This function will automatically pick up all the necessary verification fields and initialise their visual status.
	function autoSetup(formID)
	{
		var formHandle = document.getElementById(formID);

		if (!formHandle)
			return false;

		for (var i = 0, n = formHandle.elements.length; i < n; i++)
		{
			var curElement = formHandle.elements[i];

			// The field says what it is, in data-autov.
			var curType = curElement.dataset.autov;

			if (!curType || (curElement.type != 'text' && curElement.type != 'password' && curElement.type != 'email'))
				continue;

			// A reserved field is one the password may not contain.
			if (curType.indexOf('reserve') === 0)
				curType = 'reserved';

			addVerificationField(curType, curElement);
		}

		return true;
	}

	// What is the password state?
	function refreshMainPassword()
	{
		if (!verificationFields['pwmain'])
			return false;

		var curPass = verificationFields['pwmain'].input.value;
		var stringIndex = passwordProblem(curPass);
		var isValid = stringIndex == '';

		setVerificationImage(verificationFields['pwmain'], isValid, textStrings[isValid ? 'password_valid' : stringIndex]);

		// As this has changed the verification one may have too!
		if (verificationFields['pwverify'])
			refreshVerifyPassword();

		return isValid;
	}

	/*
	 * Which of the password rules this password breaks, or an empty string if it
	 * breaks none. Kept apart from refreshMainPassword() so that the verify
	 * field can ask the same question without drawing the main field's icon: it
	 * used to do that by calling refreshMainPassword() with a flag that stopped
	 * the two from calling each other forever.
	 */
	function passwordProblem(curPass)
	{
		var stringIndex = '';

		// Is it a valid length?
		if ((curPass.length < 8 && passwordLevel >= 1) || curPass.length < 4)
			stringIndex = 'password_short';

		// More than basic?
		if (passwordLevel >= 1)
		{
			// If there is a username check it's not in the password!
			if (verificationFields['username'] && verificationFields['username'].input.value && curPass.indexOf(verificationFields['username'].input.value) != -1)
				stringIndex = 'password_reserved';

			// Any reserved fields?
			for (var i in verificationFields)
			{
				if (verificationFields[i].type == 'reserved' && verificationFields[i].input.value && curPass.indexOf(verificationFields[i].input.value) != -1)
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
		if (!verificationFields['pwmain'] || !verificationFields['pwverify'])
			return false;

		// Check and set valid status!
		var curPass = verificationFields['pwmain'].input.value;
		var isValid = curPass == verificationFields['pwverify'].input.value && passwordProblem(curPass) == '';

		setVerificationImage(verificationFields['pwverify'], isValid, textStrings[isValid ? 'password_valid' : 'password_no_match']);

		return true;
	}

	// If the username is changed just revert the status of whether it's valid!
	function refreshUsername()
	{
		if (!verificationFields['username'])
			return false;

		setVerificationImage(verificationFields['username'], 'check', textStrings['username_check']);

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
		var curUsername = verificationFields['username'].input.value;

		if (!curUsername)
			return false;

		if (!is_auto)
			ajax_indicator(true);

		// Request a search on that username.
		var checkName = curUsername.php_to8bit().php_urlencode();
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=signup;sa=usernamecheck;xml;username=' + checkName, checkUsernameCallback);

		return true;
	}

	// Callback for getting the username data.
	function checkUsernameCallback(XMLDoc)
	{
		var tags = XMLDoc ? XMLDoc.getElementsByTagName('username') : null;

		// No answer we can read is not the same as "that name is fine".
		if (!tags || !tags.length)
			return;

		var isValid = tags[0].getAttribute('valid') == 1;

		setVerificationImage(verificationFields['username'], isValid, textStrings[isValid ? 'username_valid' : 'username_invalid']);

		ajax_indicator(false);
	}

	// Set the icon beside a field to say how it is doing.
	function setVerificationImage(field, state, alt)
	{
		if (!field || !field.icon)
			return false;

		if (!alt)
			alt = '*';

		field.icon.className = 'main_icons ' + (state === 'check' ? 'check' : (state ? 'valid' : 'invalid'));
		field.icon.title = alt;

		/*
		 * 'check' means "not asked yet", which is the username field before
		 * anything has been typed into it. That is not a verdict, so leave the
		 * field itself looking the way the stylesheet drew it.
		 */
		field.input.className = state === 'check' ? field.className : field.className + ' ' + (state ? 'valid_input' : 'invalid_input');

		return true;
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
