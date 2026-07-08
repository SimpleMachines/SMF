// The draft save object
function smf_DraftAutoSave(oOptions)
{
	this.opt = oOptions;
	this.bInDraftMode = false;
	this.sCurDraftId = '';
	this.oCurDraftDiv = null;
	this.interval_id = null;
	this.oDraftHandle = window;
	this.sLastSaved = '';
	this.bPM = !!this.opt.bPM;
	this.sCheckDraft = '';

	// slight delay on autosave init to allow sceditor to create the iframe
	setTimeout('addLoadEvent(' + this.opt.sSelf + '.init())', 4000);
}

// Start our self calling routine
smf_DraftAutoSave.prototype.init = function ()
{
	if (this.opt.iFreq > 0)
	{
		// find the editors wysiwyg iframe and gets its window
		var oIframe = document.getElementsByTagName('iframe')[0];
		var oIframeWindow = oIframe.contentWindow || oIframe.contentDocument;
		// start the autosave timer
		this.interval_id = window.setInterval(this.opt.sSelf + '.draft' + (this.bPM ? 'PM' : '') + 'Save();', this.opt.iFreq);

		// Set up window focus and blur events
		var instanceRef = this;
		this.oDraftHandle.onblur = function (oEvent) {return instanceRef.draftBlur(oEvent, true);};
		this.oDraftHandle.onfocus = function (oEvent) {return instanceRef.draftFocus(oEvent, true);};

		// If we found the iframe window, set body focus/blur events for it
		if (oIframeWindow.document)
		{
			var oIframeDoc = oIframeWindow.document;
			// @todo oDraftAutoSave should use the this.opt.sSelf name not hardcoded
			oIframeDoc.body.onblur = function (oEvent) {return parent.oDraftAutoSave.draftBlur(oEvent, false);};
			oIframeDoc.body.onfocus = function (oEvent) {return parent.oDraftAutoSave.draftFocus(oEvent, false);};
		};
	}
}

// Moved away from the page, where did you go? ... till you return we pause autosaving
smf_DraftAutoSave.prototype.draftBlur = function(oEvent, source)
{
	var e = $('#' + this.opt.sSceditorID).get(0);
	if (sceditor.instance(e).inSourceMode() == source)
	{
		// save what we have and turn of the autosave
		if (this.bPM)
			this.draftPMSave();
		else
			this.draftSave();

		if (this.interval_id != "")
			window.clearInterval(this.interval_id);
		this.interval_id = "";
	}
}

// Since you're back we resume the autosave timer
smf_DraftAutoSave.prototype.draftFocus = function(oEvent, source)
{
	var e = $('#' + this.opt.sSceditorID).get(0);
	if (sceditor.instance(e).inSourceMode() == source)
	{
		if (this.interval_id == "")
			this.interval_id = window.setInterval(this.opt.sSelf + '.draft' + (this.bPM ? 'PM' : '') + 'Save();', this.opt.iFreq);
	}
}

// Make the call to save this draft in the background
smf_DraftAutoSave.prototype.draftSave = function ()
{
	var e = $('#' + this.opt.sSceditorID).get(0);
	var sPostdata = sceditor.instance(e).getText(true);
	var sPosticon = (typeof document.forms.postmodify['icon'] === 'undefined' ? 'xx' : document.forms.postmodify['icon'].value);
	var sPostsubj = (typeof document.forms.postmodify['subject'] === 'undefined' ? '' : document.forms.postmodify['subject'].value);

	// nothing to save or already posting or nothing changed?
	if (isEmptyText(sPostdata) || smf_formSubmitted || this.sCheckDraft == sPostdata)
		return false;

	// Still saving the last one or other?
	if (this.bInDraftMode)
		this.draftCancel();

	// Flag that we are saving a draft
	document.getElementById('throbber').style.display = '';
	this.bInDraftMode = true;

	// Get the form elements that we want to save
	var aSections = [
		'topic=' + parseInt(document.forms.postmodify.elements['topic'].value),
		'id_draft=' + (('id_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_draft'].value) : 0),
		'subject=' + escape(sPostsubj.php_to8bit()).replace(/\+/g, "%2B"),
		'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
		'icon=' + escape(sPosticon.php_to8bit()).replace(/\+/g, "%2B"),
		'save_draft=true',
		smf_session_var + '=' + smf_session_id,
	];

	// Get the locked an/or sticky values if they have been selected or set that is
	if (this.opt.sType == 'post')
	{
		if (document.getElementById('check_lock') && document.getElementById('check_lock').checked)
			aSections[aSections.length] = 'lock=1';
		if (document.getElementById('check_sticky') && document.getElementById('check_sticky').checked)
			aSections[aSections.length] = 'sticky=1';
	}

	// keep track of source or wysiwyg
	var e = $('#' + this.opt.sSceditorID).get(0);
	aSections[aSections.length] = 'message_mode=' + sceditor.instance(e).inSourceMode();

	// Send in document for saving and hope for the best
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + "action=post2;board=" + this.opt.iBoard + ";xml", aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.sCheckDraft = sPostdata;
}

// Make the call to save this PM draft in the background
smf_DraftAutoSave.prototype.draftPMSave = function ()
{
	var e = $('#' + this.opt.sSceditorID).get(0);
	var sPostdata = sceditor.instance(e).getText();

	// nothing to save or already posting or nothing changed?
	if (isEmptyText(sPostdata) || smf_formSubmitted || this.sCheckDraft == sPostdata)
		return false;

	// Still saving the last one or some other?
	if (this.bInDraftMode)
		this.draftCancel();

	// Flag that we are saving
	document.getElementById('throbber').style.display = '';
	this.bInDraftMode = true;

	// Get the to and bcc values
	var aTo = this.draftGetRecipient('recipient_to[]');
	var aBcc = this.draftGetRecipient('recipient_bcc[]');

	// Get the rest of the form elements that we want to save, and load them up
	var aSections = [
		'replied_to=' + parseInt(document.forms.postmodify.elements['replied_to'].value),
		'id_pm_draft=' + (('id_pm_draft' in document.forms.postmodify.elements) ? parseInt(document.forms.postmodify.elements['id_pm_draft'].value) : 0),
		'subject=' + escape(document.forms.postmodify['subject'].value.php_to8bit()).replace(/\+/g, "%2B"),
		'message=' + escape(sPostdata.php_to8bit()).replace(/\+/g, "%2B"),
		'recipient_to=' + aTo,
		'recipient_bcc=' + aBcc,
		'save_draft=true',
		smf_session_var + '=' + smf_session_id,
	];

	// account for wysiwyg
	if (this.opt.sType == 'post')
		aSections[aSections.length] = 'message_mode=' + parseInt(document.forms.postmodify.elements['message_mode'].value);

	// Send in (post) the document for saving
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + "action=pm;sa=send2;xml", aSections.join("&"), this.onDraftDone);

	// Save the latest for compare
	this.sCheckDraft = sPostdata;
}

// Callback function of the XMLhttp request for saving the draft message
smf_DraftAutoSave.prototype.onDraftDone = function (XMLDoc)
{
	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('draft'))
		return this.draftCancel();

	// Grab the returned draft id and saved time from the response
	this.sCurDraftId = XMLDoc.getElementsByTagName('draft')[0].getAttribute('id');
	this.sLastSaved = XMLDoc.getElementsByTagName('draft')[0].childNodes[0].nodeValue;

	// Update the form to show we finished, if the id is not set, then set it
	document.getElementById(this.opt.sLastID).value = this.sCurDraftId;
	oCurDraftDiv = document.getElementById(this.opt.sLastNote);
	setInnerHTML(oCurDraftDiv, this.sLastSaved);

	// hide the saved draft infobox in the event they pressed the save draft button at some point
	if (this.opt.sType == 'post')
		document.getElementById('draft_section').style.display = 'none';

	// thank you sir, may I have another
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
}

// function to retrieve the to and bcc values from the pseudo arrays
smf_DraftAutoSave.prototype.draftGetRecipient = function (sField)
{
	var oRecipient = document.forms.postmodify.elements[sField];
	var aRecipient = [];

	if (typeof(oRecipient) != 'undefined')
	{
		// just one recipient
		if ('value' in oRecipient)
			aRecipient.push(parseInt(oRecipient.value));
		else
		{
			// or many !
			for (var i = 0, n = oRecipient.length; i < n; i++)
				aRecipient.push(parseInt(oRecipient[i].value));
		}
	}
	return aRecipient;
}

// If another auto save came in with one still pending
smf_DraftAutoSave.prototype.draftCancel = function ()
{
	// can we do anything at all ... do we want to (e.g. sequence our async events?)
	// @todo if not remove this function
	this.bInDraftMode = false;
	document.getElementById('throbber').style.display = 'none';
}
