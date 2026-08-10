// This file contains the browser's half of registering and using passkeys.

var smf_passkey = {

	// Whether this browser can do any of this at all.
	isSupported: function ()
	{
		return typeof window.PublicKeyCredential !== "undefined" && !!navigator.credentials;
	},

	/*
	 * WebAuthn hands binary around, and JSON cannot carry it, so everything
	 * crossing between the two is base64url. That is base64 with a couple of
	 * characters swapped and the padding left off, so these two are just
	 * base64 with the swap undone or redone.
	 */
	decode: function (value)
	{
		var padded = value.replace(/-/g, "+").replace(/_/g, "/"),
			raw = window.atob(padded),
			bytes = new Uint8Array(raw.length);

		for (var i = 0; i < raw.length; i++)
			bytes[i] = raw.charCodeAt(i);

		return bytes;
	},

	encode: function (buffer)
	{
		var bytes = new Uint8Array(buffer),
			raw = "";

		for (var i = 0; i < bytes.length; i++)
			raw += String.fromCharCode(bytes[i]);

		return window.btoa(raw).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
	},

	// Turns the options the forum sent into the shapes the browser wants.
	prepare: function (options)
	{
		options.challenge = smf_passkey.decode(options.challenge);

		if (options.user)
			options.user.id = smf_passkey.decode(options.user.id);

		["excludeCredentials", "allowCredentials"].forEach(function (list)
		{
			if (!options[list])
				return;

			options[list] = options[list].map(function (credential)
			{
				return {
					type: credential.type,
					id: smf_passkey.decode(credential.id)
				};
			});
		});

		return options;
	},

	// Asks the forum something, and gives back whatever it answers.
	post: function (subaction, data)
	{
		var body = new URLSearchParams(data || {});

		body.append(smf_session_var, smf_session_id);

		return fetch(smf_scripturl + "?action=passkey;sa=" + subaction, {
			method: "POST",
			credentials: "same-origin",
			headers: {"X-Requested-With": "XMLHttpRequest"},
			body: body
		}).then(function (response)
		{
			return response.json();
		}).then(function (answer)
		{
			if (answer.error)
				throw new Error(answer.error);

			return answer;
		});
	},

	// Flattens what an authenticator produced into something postable.
	flatten: function (credential)
	{
		var data = {
			id: credential.id,
			rawId: smf_passkey.encode(credential.rawId),
			clientDataJSON: smf_passkey.encode(credential.response.clientDataJSON)
		};

		if (credential.response.attestationObject)
			data.attestationObject = smf_passkey.encode(credential.response.attestationObject);

		if (credential.response.authenticatorData)
		{
			data.authenticatorData = smf_passkey.encode(credential.response.authenticatorData);
			data.signature = smf_passkey.encode(credential.response.signature);

			if (credential.response.userHandle)
				data.userHandle = smf_passkey.encode(credential.response.userHandle);
		}

		return data;
	},

	// Makes a passkey and tells the forum about it.
	register: function (config)
	{
		var title = document.getElementById(config.field) ? document.getElementById(config.field).value : "";

		return smf_passkey.post("registeroptions", {}).then(function (answer)
		{
			return navigator.credentials.create({publicKey: smf_passkey.prepare(answer.options)});
		}).then(function (credential)
		{
			var data = smf_passkey.flatten(credential);
			data.title = title;

			return smf_passkey.post("register", data);
		}).then(function (answer)
		{
			window.location.href = answer.redirect;
		});
	},

	/*
	 * Signs in with a passkey. Conditional mediation is the version that puts
	 * the passkey in the browser's own autofill list on the username field,
	 * so it is offered without anyone having to press anything; the other
	 * version is the button, for when somebody does.
	 */
	login: function (conditional)
	{
		return smf_passkey.post("loginoptions", {}).then(function (answer)
		{
			var request = {publicKey: smf_passkey.prepare(answer.options)};

			if (conditional)
				request.mediation = "conditional";

			return navigator.credentials.get(request);
		}).then(function (credential)
		{
			return smf_passkey.post("login", smf_passkey.flatten(credential));
		}).then(function (answer)
		{
			window.location.href = answer.redirect;
		});
	},

	// Puts the passkey button on the login form, once we know it will work.
	offerLogin: function (config)
	{
		var container = document.getElementById(config.container);

		if (!smf_passkey.isSupported() || !container)
			return;

		var button = document.createElement("button");
		button.type = "button";
		button.className = "button login_with_passkey";
		button.textContent = config.label;

		button.addEventListener("click", function ()
		{
			button.disabled = true;

			smf_passkey.login(false).catch(function (error)
			{
				button.disabled = false;
				smf_passkey.complain(container, config.failed, error);
			});
		});

		container.appendChild(button);

		smf_passkey.offerAutofill(config, container);
	},

	/*
	 * Offers the passkey in the username field's autofill list. The browser
	 * only does this while a get() with conditional mediation is outstanding,
	 * and only when the field says it will accept one, so both go together.
	 */
	offerAutofill: function (config, container)
	{
		var field = document.getElementById(config.field);

		if (!field || !window.PublicKeyCredential.isConditionalMediationAvailable)
			return;

		window.PublicKeyCredential.isConditionalMediationAvailable().then(function (available)
		{
			if (!available)
				return;

			field.setAttribute("autocomplete", "username webauthn");

			/*
			 * Nothing is said when this fails. It was never asked for out
			 * loud: the member may simply have typed their password instead,
			 * which abandons the request and is not an error.
			 */
			smf_passkey.login(true).catch(function ()
			{
			});
		});
	},

	/*
	 * Makes a passkey for an account that does not exist yet. The forum holds
	 * on to it until the sign up form has been submitted and accepted, so
	 * nothing here is final until the member has finished registering.
	 */
	signUp: function (config)
	{
		var field = document.getElementById(config.field);

		return smf_passkey.post("signupoptions", {user: field ? field.value : ""}).then(function (answer)
		{
			return navigator.credentials.create({publicKey: smf_passkey.prepare(answer.options)});
		}).then(function (credential)
		{
			return smf_passkey.post("signup", smf_passkey.flatten(credential));
		});
	},

	/*
	 * Offers to make one on the sign up form. The password boxes stay where
	 * they are until there is a passkey to put in their place, so somebody who
	 * changes their mind, or whose authenticator refuses, still has the form
	 * they started with.
	 */
	offerSignUp: function (config)
	{
		var group = document.getElementById(config.group),
			container = document.getElementById(config.container),
			button = document.getElementById(config.button);

		if (!smf_passkey.isSupported() || !group || !container || !button)
			return;

		group.style.display = "";

		button.addEventListener("click", function ()
		{
			button.disabled = true;

			smf_passkey.signUp(config).then(function ()
			{
				smf_passkey.usePasskeyInstead(config, container, button);
			}).catch(function (error)
			{
				button.disabled = false;
				smf_passkey.complain(container, config.failed, error);
			});
		});
	},

	// Takes the password boxes off the form, now that there is a passkey.
	usePasskeyInstead: function (config, container, button)
	{
		config.hide.forEach(function (id)
		{
			var hidden = document.getElementById(id);

			if (!hidden)
				return;

			hidden.style.display = "none";

			/*
			 * A disabled field is not submitted, so the empty boxes cannot
			 * arrive looking like a password somebody meant to set. The forum
			 * ignores them either way; this is so the browser stops offering to
			 * remember a password that does not exist.
			 */
			Array.prototype.forEach.call(hidden.getElementsByTagName("input"), function (input)
			{
				input.value = "";
				input.disabled = true;
			});
		});

		// The form's own submit check compares the two boxes unless this says
		// they are not in use.
		window.currentAuthMethod = "vouched";

		button.style.display = "none";

		// Reuse whatever an earlier attempt complained in, so a message about a
		// passkey that failed is not left sitting under one that worked.
		var box = container.querySelector(".passkey_error");

		if (!box)
		{
			box = document.createElement("div");
			container.appendChild(box);
		}

		box.className = "infobox";
		box.textContent = config.done;
	},

	// Puts the passkey button on the profile page, once we know it will work.
	offerRegistration: function (config)
	{
		var container = document.getElementById(config.container);

		if (!smf_passkey.isSupported() || !container)
			return;

		container.style.display = "";

		var button = document.getElementById(config.button);

		button.addEventListener("click", function ()
		{
			button.disabled = true;

			smf_passkey.register(config).catch(function (error)
			{
				button.disabled = false;
				smf_passkey.complain(container, config.failed, error);
			});
		});
	},

	// Says that something went wrong, where the member is already looking.
	complain: function (container, message, error)
	{
		var box = container.querySelector(".passkey_error");

		if (!box)
		{
			box = document.createElement("div");
			box.className = "errorbox passkey_error";
			container.appendChild(box);
		}

		/*
		 * The browser's own message is worth showing: it is the one that knows
		 * whether the member cancelled, had no passkey to offer, or was asked
		 * for something their authenticator cannot do.
		 */
		box.textContent = message + (error && error.message ? " (" + error.message + ")" : "");
	}
};

if (typeof smf_passkey_login !== "undefined")
	smf_passkey.offerLogin(smf_passkey_login);

if (typeof smf_passkey_manage !== "undefined")
	smf_passkey.offerRegistration(smf_passkey_manage);

if (typeof smf_passkey_signup !== "undefined")
	smf_passkey.offerSignUp(smf_passkey_signup);
