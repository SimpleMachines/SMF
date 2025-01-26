(function (sceditor) {
	'use strict';

	sceditor.plugins.drafts = function ()
	{
		var
			editor,
			form = document.forms.postmodify,
			bInDraftMode = false,
			lastValue,
			interval,
			opt;

		var cancel = function ()
		{
			bInDraftMode = false;
			document.getElementById('throbber').style.display = 'none';
		};

		// Callback function of the XMLhttp request for saving the draft message
		var done = function (XMLDoc)
		{
			console.log (XMLDoc)
			// If it is not valid then clean up
			if (!XMLDoc || !XMLDoc.getElementsByTagName('draft'))
				return cancel();

			// Grab the returned draft id and saved time from the response
			var sCurDraftId = XMLDoc.getElementsByTagName('draft')[0].getAttribute('id');
			var sLastSaved = XMLDoc.getElementsByTagName('draft')[0].childNodes[0].nodeValue;

			// Update the form to show we finished, if the id is not set, then set it
			document.getElementById(opt.sLastID).value = sCurDraftId;
			document.getElementById(opt.sLastNote).innerHTML = sLastSaved;

			// hide the saved draft infobox in the event they pressed the save draft button at some point
			document.getElementById('draft_section').style.display = 'none';

			cancel();
		};

		var trigger = function ()
		{
			var sPostdata = editor.val();

			// nothing to save or already posting or nothing changed?
			if (isEmptyText(sPostdata) || lastValue == sPostdata)
				return false;

			// Still saving the last one or other?
			if (bInDraftMode)
				return cancel();

			var oSendData = editor.getSendDataForDraft ? editor.getSendDataForDraft() : null;

			if (!editor.getSendDataForDraft) {
				oSendData = new FormData();
				oSendData.append('message', sPostdata.php_to8bit());
			}

			// Flag that we are saving a draft
			document.getElementById('throbber').style.display = '';
			bInDraftMode = true;

			// Send in document for saving and hope for the best
			sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + opt.sQueryParams + ";xml", oSendData, done);

			// Save the latest for compare
			lastValue = sPostdata;
		};

		this.signalReady = function ()
		{
			editor = this;
			opt = editor.opts.draftOptions;
			interval = setInterval(trigger, opt.iFreq);

			editor.opts.original.form.addEventListener("submit", this.signalBlurEvent);
		};

		this.signalFocusEvent = function ()
		{
			if (!interval)
				interval = setInterval(trigger, opt.iFreq);
		};

		this.signalBlurEvent = function ()
		{
			clearInterval(interval);
		};
	};

	sceditor.plugins.messageDrafts = function ()
	{
		this.init = function ()
		{
			const editor = this;
			const form = document.forms.postmodify;

			editor.getSendDataForDraft = function() {
				var sPosticon = form.icon ? form.icon.value : 'xx';
				var sPostsubj = form.subject ? form.subject.value : '';
				var formData = new FormData();

				formData.append('topic', parseInt(form.elements['topic'].value));
				formData.append('id_draft', 'id_draft' in form.elements ? parseInt(form.elements['id_draft'].value) : 0);
				formData.append('subject', sPostsubj.php_to8bit());
				formData.append('message', editor.val().php_to8bit());
				formData.append('icon', sPosticon.php_to8bit());
				formData.append('save_draft', 'true');
				formData.append(smf_session_var, smf_session_id);

				if (document.getElementById('check_lock')?.checked) {
					formData.append('lock', '1');
				}

				if (document.getElementById('check_sticky')?.checked) {
					formData.append('sticky', '1');
				}

				return formData;
			};
		};
	};

	sceditor.plugins.pmDrafts = function ()
	{
		// Function to retrieve the "to" and "bcc" values from the form
		var getRecipient = function (sField) {
			var oRecipient = form.elements[sField];
			var aRecipient = [];

			if (oRecipient) {
				if ('value' in oRecipient) {
					aRecipient.push(parseInt(oRecipient.value));
				} else {
					for (var i = 0, n = oRecipient.length; i < n; i++) {
						aRecipient.push(parseInt(oRecipient[i].value));
					}
				}
			}
			return aRecipient;
		};

		var getFormData = function () {
			// Get the "to" and "bcc" values
			var aTo = getRecipient('recipient_to[]');
			var aBcc = getRecipient('recipient_bcc[]');

			// Create a new FormData object
			var formData = new FormData();

			// Append necessary form fields
			formData.append('replied_to', parseInt(form.elements['replied_to'].value));
			formData.append('id_pm_draft', 'id_pm_draft' in form.elements ? parseInt(form.elements['id_pm_draft'].value) : 0);
			formData.append('subject', form['subject'].value.php_to8bit());
			formData.append('message', editor.val().php_to8bit());
			formData.append('save_draft', 'true');
			formData.append(smf_session_var, smf_session_id);

			// Append recipient arrays properly
			aTo.forEach(value => formData.append('recipient_to[]', value));
			aBcc.forEach(value => formData.append('recipient_bcc[]', value));

			return formData;
		};

		this.init = function ()
		{
			const editor = this;
			const form = document.forms.postmodify;

			editor.getSendDataForDraft = getFormData.bind(editor);
		};
	};
})(sceditor);