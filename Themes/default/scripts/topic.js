// *** QuickModifyTopic object.
function QuickModifyTopic(oOptions)
{
	this.opt = oOptions;
	this.aHidePrefixes = this.opt.aHidePrefixes;
	this.iCurTopicId = 0;
	this.sCurMessageId = '';
	this.sBuffSubject = '';
	this.oCurSubjectDiv = null;
	this.oTopicModHandle = document;
	this.bInEditMode = false;
	this.bMouseOnDiv = false;
	this.init();
}

// Used to initialise the object event handlers
QuickModifyTopic.prototype.init = function ()
{
	// Detect and act on keypress
	this.oTopicModHandle.onkeydown = this.modify_topic_keypress.bind(this);

	// Used to detect when we've stopped editing.
	this.oTopicModHandle.onclick = this.modify_topic_click.bind(this);
};

// called from the double click in the div
QuickModifyTopic.prototype.modify_topic = function (topic_id, first_msg_id)
{
	// already editing
	if (this.bInEditMode)
	{
		// Same message then just return, otherwise drop out of this edit.
		if (this.iCurTopicId == topic_id)
			return;
		else
			this.modify_topic_cancel();
	}

	this.bInEditMode = true;
	this.bMouseOnDiv = true;
	this.iCurTopicId = topic_id;

	// Get the topics current subject
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + "action=quotefast;quote=" + first_msg_id + ";modify;xml", '', this.onDocReceived_modify_topic);
}

// callback function from the modify_topic ajax call
QuickModifyTopic.prototype.onDocReceived_modify_topic = function (XMLDoc)
{
	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('message'))
	{
		this.modify_topic_cancel();
		return true;
	}

	this.sCurMessageId = XMLDoc.getElementsByTagName("message")[0].getAttribute("id");
	this.oCurSubjectDiv = document.getElementById('msg_' + this.sCurMessageId.substr(4));
	this.sBuffSubject = getInnerHTML(this.oCurSubjectDiv);

	// Here we hide any other things they want hidden on edit.
	this.set_hidden_topic_areas('none');

	// Show we are in edit mode and allow the edit
	ajax_indicator(false);
	this.modify_topic_show_edit(XMLDoc.getElementsByTagName("subject")[0].childNodes[0].nodeValue);
}

// Cancel out of an edit and return things to back to what they were
QuickModifyTopic.prototype.modify_topic_cancel = function ()
{
	setInnerHTML(this.oCurSubjectDiv, this.sBuffSubject);
	this.set_hidden_topic_areas('');
	this.bInEditMode = false;

	return false;
}

// Simply restore/show any hidden bits during topic editing.
QuickModifyTopic.prototype.set_hidden_topic_areas = function (set_style)
{
	for (var i = 0; i < this.aHidePrefixes.length; i++)
	{
		if (document.getElementById(this.aHidePrefixes[i] + this.sCurMessageId.substr(4)) != null)
			document.getElementById(this.aHidePrefixes[i] + this.sCurMessageId.substr(4)).style.display = set_style;
	}
}

// For templating, shown that an inline edit is being made.
QuickModifyTopic.prototype.modify_topic_show_edit = function (subject)
{
	// Just template the subject.
	setInnerHTML(this.oCurSubjectDiv, '<input type="text" name="subject" value="' + subject + '" size="60" style="width: 95%;" maxlength="80"><input type="hidden" name="topic" value="' + this.iCurTopicId + '"><input type="hidden" name="msg" value="' + this.sCurMessageId.substr(4) + '">');

	// attach mouse over and out events to this new div
	this.oCurSubjectDiv.instanceRef = this;
	this.oCurSubjectDiv.onmouseout = function (oEvent) {return this.instanceRef.modify_topic_mouseout(oEvent);};
	this.oCurSubjectDiv.onmouseover = function (oEvent) {return this.instanceRef.modify_topic_mouseover(oEvent);};
}

// Yup that's right, save it
QuickModifyTopic.prototype.modify_topic_save = function (cur_session_id, cur_session_var)
{
	if (!this.bInEditMode)
		return true;

	// Add backwards compatibility with old themes.
	if (typeof(cur_session_var) == 'undefined')
		cur_session_var = 'sesc';

	var i, x = new Array();
	x[x.length] = 'subject=' + document.forms.quickModForm['subject'].value.php_to8bit().php_urlencode();
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements['topic'].value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements['msg'].value);

	// send in the call to save the updated topic subject
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + "action=jsmodify;topic=" + parseInt(document.forms.quickModForm.elements['topic'].value) + ";" + cur_session_var + "=" + cur_session_id + ";xml", x.join("&"), this.modify_topic_done);

	return false;
}

// done with the edit, if all went well show the new topic title
QuickModifyTopic.prototype.modify_topic_done = function (XMLDoc)
{
	ajax_indicator(false);

	// If it is not valid then clean up
	if (!XMLDoc || !XMLDoc.getElementsByTagName('subject'))
	{
		this.modify_topic_cancel();
		return true;
	}

	var message = XMLDoc.getElementsByTagName("smf")[0].getElementsByTagName("message")[0];
	var subject = message.getElementsByTagName("subject")[0];
	var error = message.getElementsByTagName("error")[0];

	// No subject or other error?
	if (!subject || error)
		return false;

	this.modify_topic_hide_edit(subject.childNodes[0].nodeValue);
	this.set_hidden_topic_areas('');
	this.bInEditMode = false;

	// redo tips if they are on since we just pulled the rug out on this one
	if ($.isFunction($.fn.SMFtooltip))
		$('.preview').SMFtooltip().smf_tooltip_off;

	return false;
}

// Done with the edit, put in new subject and link.
QuickModifyTopic.prototype.modify_topic_hide_edit = function (subject)
{
	// Re-template the subject!
	setInnerHTML(this.oCurSubjectDiv, '<a href="' + smf_scripturl + '?topic=' + this.iCurTopicId + '.0">' + subject + '<' +'/a>');
}

// keypress event ... like enter or escape
QuickModifyTopic.prototype.modify_topic_keypress = function (oEvent)
{
	if (typeof(oEvent.keyCode) != "undefined" && this.bInEditMode)
	{
		if (oEvent.keyCode == 27)
		{
			this.modify_topic_cancel();
			if (typeof(oEvent.preventDefault) == "undefined")
				oEvent.returnValue = false;
			else
				oEvent.preventDefault();
		}
		else if (oEvent.keyCode == 13)
		{
			this.modify_topic_save(smf_session_id, smf_session_var);
			if (typeof(oEvent.preventDefault) == "undefined")
				oEvent.returnValue = false;
			else
				oEvent.preventDefault();
		}
	}
}

// A click event to signal the finish of the edit
QuickModifyTopic.prototype.modify_topic_click = function (oEvent)
{
	if (this.bInEditMode && !this.bMouseOnDiv)
		this.modify_topic_save(smf_session_id, smf_session_var);
}

// Moved out of the editing div
QuickModifyTopic.prototype.modify_topic_mouseout = function (oEvent)
{
	this.bMouseOnDiv = false;
}

// Moved back over the editing div
QuickModifyTopic.prototype.modify_topic_mouseover = function (oEvent)
{
	this.bMouseOnDiv = true;
}

// *** QuickReply object.
function QuickReply(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = this.opt.bDefaultCollapsed;
	this.bIsFull = this.opt.bIsFull;
}

// When a user presses quote, put it in the quick reply box (if expanded).
QuickReply.prototype.quote = function (iMessageId, xDeprecated)
{
	// Compatibility with older templates.
	if (typeof(xDeprecated) != 'undefined')
		return true;

	if (this.bCollapsed)
	{
		window.location.href = smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=post;quote=' + iMessageId + ';topic=' + this.opt.iTopicId + '.' + this.opt.iStart;
		return false;
	}
	else
	{
		// Doing it the XMLhttp way?
		if (window.XMLHttpRequest)
		{
			ajax_indicator(true);
			if (this.bIsFull)
				insertQuoteFast(iMessageId);

			else
				getXMLDocument(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId + ';xml', this.onQuoteReceived);
		}
		// Or with a smart popup!
		else
			reqWin(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId, 240, 90);

		// Move the view to the quick reply box.
		if (navigator.appName == 'Microsoft Internet Explorer')
			window.location.hash = this.opt.sJumpAnchor;
		else
			window.location.hash = '#' + this.opt.sJumpAnchor;

		return false;
	}
}

// This is the callback function used after the XMLhttp request.
QuickReply.prototype.onQuoteReceived = function (oXMLDoc)
{
	var sQuoteText = '';

	for (var i = 0; i < oXMLDoc.getElementsByTagName('quote')[0].childNodes.length; i++)
		sQuoteText += oXMLDoc.getElementsByTagName('quote')[0].childNodes[i].nodeValue;

	replaceText(sQuoteText, document.forms.postmodify.message);

	ajax_indicator(false);
}

// The function handling the swapping of the quick reply.
QuickReply.prototype.swap = function ()
{
	$('#' + this.opt.sImageId).toggleClass(this.opt.sClassCollapsed + ' ' + this.opt.sClassExpanded);
	$('#' + this.opt.sContainerId).slideToggle();

	this.bCollapsed = !this.bCollapsed;
}

// *** QuickModify object.
function QuickModify(oOptions)
{
	this.opt = oOptions;
	this.bInEditMode = false;
	this.sCurMessageId = '';
	this.oCurMessageDiv = null;
	this.oCurSubjectDiv = null;

	for (const el of document.getElementsByClassName(this.opt.sClassName)) {
		el.hidden = false;
		el.addEventListener('click', this.modifyMsg.bind(this, el.id.match(/\d+/)));
	}
}

// Function called when a user presses the edit button.
QuickModify.prototype.modifyMsg = function (iMessageId)
{
	// First cancel if there's another message still being edited.
	if (this.bInEditMode)
		this.modifyCancel();

	// At least NOW we're in edit mode
	this.bInEditMode = true;

	// Send out the XMLhttp request to get more info
	ajax_indicator(true);
	getXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + iMessageId + ';modify;xml;' + smf_session_var + '=' + smf_session_id, this.onMessageReceived);

	// Jump to the message
	document.getElementById('msg' + iMessageId).scrollIntoView();
}

// The callback function used for the XMLhttp request retrieving the message.
QuickModify.prototype.onMessageReceived = function (XMLDoc)
{
	var sBodyText = '', sSubjectText = '';

	// No longer show the 'loading...' sign.
	ajax_indicator(false);

	// Grab the message ID.
	this.sCurMessageId = XMLDoc.getElementsByTagName('message')[0].getAttribute('id');

	// If this is not valid then simply give up.
	if (!document.getElementById(this.sCurMessageId))
		return this.modifyCancel();

	// Replace the body part.
	for (let i = 0; i < XMLDoc.getElementsByTagName("message")[0].childNodes.length; i++)
		sBodyText += XMLDoc.getElementsByTagName("message")[0].childNodes[i].nodeValue;

	this.oCurMessageDiv = document.getElementById(this.sCurMessageId);
	this.oCurSubjectDiv = document.getElementById('subject_' + this.sCurMessageId.substring(4));
	if (this.oCurSubjectDiv !== null)
		this.oCurSubjectDiv.hidden = true;
	this.oCurMessageDiv.hidden = true;

	// Actually create the content.
	const form = document.createElement("form");
	form.id = "quickModifyForm";

	var messageInput = document.createElement("textarea");
	messageInput.name = "message";
	messageInput.cols = "80";
	messageInput.rows = "10";
	messageInput.innerHTML = sBodyText;
	messageInput.addEventListener('keydown', function(e) {
		if (e.key === "Enter" && (e.metaKey || e.ctrlKey)) {
			this.modifySave();
		}
		if (e.key === "Escape") {
			this.modifyCancel();
		}
	}.bind(this));

	var subjectInput = document.createElement("input");
	subjectInput.name = "subject";
	subjectInput.maxLength = "80";
	subjectInput.size = "80";
	subjectInput.value = XMLDoc.getElementsByTagName('subject')[0].childNodes[0].nodeValue;

	const reasonLabel = document.createElement("label");
	const reasonInput = document.createElement("input");
	reasonInput.name = "modify_reason";
	reasonInput.maxLength = "80";
	reasonInput.size = "80";
	reasonInput.value = XMLDoc.getElementsByTagName('reason')[0].childNodes[0].nodeValue;

	const buttonGroup = document.createElement("div");
	buttonGroup.className = 'buttonlistend';

	const cancelButton = document.createElement("button");
	cancelButton.className = 'button';
	cancelButton.textContent = this.opt.sCancelButtonText;
	cancelButton.addEventListener('click', this.modifyCancel.bind(this));

	const saveButton = document.createElement("button");
	saveButton.className = 'button active';
	saveButton.textContent = this.opt.sSaveButtonText;
	saveButton.addEventListener('click', this.modifySave.bind(this));

	reasonLabel.append(this.opt.sTemplateReasonEdit, reasonInput);
	buttonGroup.append(saveButton, cancelButton);
	form.append(subjectInput, messageInput, reasonLabel, buttonGroup);
	this.oCurMessageDiv.after(form);
	messageInput.focus();

	if (this.opt.funcOnAfterCreate) {
		this.opt.funcOnAfterCreate.call(this, form);
	}

	return true;
}

// Function in case the user presses cancel (or other circumstances cause it).
QuickModify.prototype.modifyCancel = function ()
{
	if (this.oCurMessageDiv)
	{
		this.oCurMessageDiv.hidden = false;
		if (this.oCurSubjectDiv !== null)
			this.oCurSubjectDiv.hidden = false;
		document.forms.quickModifyForm.remove();
	}

	// No longer in edit mode, that's right.
	this.bInEditMode = false;

	return false;
}

// The function called after a user wants to save his precious message.
QuickModify.prototype.modifySave = function (e)
{
	e && e.preventDefault && e.preventDefault();

	// We cannot save if we weren't in edit mode.
	if (!this.bInEditMode) {
		return true;
	}

	const x = [];
	submitThisOnce(document.forms.quickModifyForm);
	const form = document.forms.quickModifyForm;

	if (form.firstChild.className === 'errorbox') {
		form.firstChild.remove();
		form.message.style.border = '';
		form.subject.style.border = '';
	}

	for (const el of form.elements) {
		x.push(el.name + '=' + el.value.php_to8bit().php_urlencode());
	}

	// Send in the XMLhttp request and let's hope for the best.
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + "action=jsmodify;topic=" + this.opt.iTopicId + ";msg=" + this.oCurMessageDiv.id.match(/\d+/) + ";" + smf_session_var + "=" + smf_session_id + ";xml", x.join("&"), this.onModifyDone);

	return false;
}

// Callback function of the XMLhttp request sending the modified message.
QuickModify.prototype.onModifyDone = function (XMLDoc)
{
	// We've finished the loading stuff.
	ajax_indicator(false);

	// If we didn't get a valid document, just cancel.
	if (!XMLDoc || !XMLDoc.getElementsByTagName('smf')[0])
	{
		reActivateThis(document.forms.quickModifyForm);
		document.forms.quickModifyForm.message.focus();

		// Mozilla will nicely tell us what's wrong.
		if (XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror') {
			const oDiv = document.createElement('div');
			oDiv.innerHTML = XMLDoc.firstChild.textContent;
			oDiv.className = 'errorbox';
			document.forms.quickModifyForm.prepend(oDiv);
		}
		else
			this.modifyCancel();

		return;
	}

	var message = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('message')[0];
	var body = message.getElementsByTagName('body')[0];
	var error = message.getElementsByTagName('error')[0];

	if (body)
	{
		this.bInEditMode = false;
		// Show new body.
		let bodyText = '';
		for (let i = 0; i < body.childNodes.length; i++)
			bodyText += body.childNodes[i].nodeValue;

		this.oCurMessageDiv.innerHTML = bodyText;
		this.oCurMessageDiv.hidden = false;

		// Show new subject div, update in case it changed.
		if (this.oCurSubjectDiv !== null) {
			let oSubject = message.getElementsByTagName('subject')[0],
				sSubjectText = oSubject.childNodes[0].nodeValue;

			this.oCurSubjectDiv.innerHTML = sSubjectText;
			this.oCurSubjectDiv.hidden = false;
		}

		document.forms.quickModifyForm.remove();

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
		{
			let modified = document.getElementById('modified_' + this.sCurMessageId.substring(4));
			modified.innerHTML = message.getElementsByTagName('modified')[0].childNodes[0].nodeValue;
		}

		// Show a message indicating the edit was successfully done.
		const oDiv = document.createElement('div');
		oDiv.textContent = message.getElementsByTagName('success')[0].childNodes[0].nodeValue;
		oDiv.className = 'infobox';
		this.oCurMessageDiv.before(oDiv);
		setTimeout(() => oDiv.remove(), 4000);
	}
	else if (error)
	{
		reActivateThis(document.forms.quickModifyForm);
		const oDiv = document.createElement('div');
		oDiv.innerHTML = error.childNodes[0].nodeValue;
		oDiv.className = 'errorbox';
		document.forms.quickModifyForm.prepend(oDiv);

		document.forms.quickModifyForm.message.focus();
		document.forms.quickModifyForm.message.style.border = error.getAttribute('in_body') == '1' ? this.opt.sErrorBorderStyle : '';
		document.forms.quickModifyForm.subject.style.border = error.getAttribute('in_subject') == '1' ? this.opt.sErrorBorderStyle : '';
	}
}

function InTopicModeration(oOptions)
{
	this.opt = oOptions;
	this.bButtonsShown = false;
	this.iNumSelected = 0;
	this.oRemoveButton = null;
	this.oRestoreButton = null;
	this.oSplitButton = null;

	this.init();
}

InTopicModeration.prototype.init = function()
{
	// Add checkboxes to all the messages.
	for (var i = 0, n = this.opt.aMessageIds.length; i < n; i++)
	{
		// Create the checkbox.
		var oCheckbox = document.createElement('input');
		oCheckbox.type = 'checkbox';
		oCheckbox.className = this.opt.sButtonStrip + '_check';
		oCheckbox.name = 'msgs[]';
		oCheckbox.value = this.opt.aMessageIds[i];
		oCheckbox.onclick = this.handleClick.bind(this, oCheckbox);

		// Append it to the container
		var oCheckboxContainer = document.getElementById(this.opt.sCheckboxContainerMask + this.opt.aMessageIds[i]);
		oCheckboxContainer.appendChild(oCheckbox);
		oCheckboxContainer.style.display = '';
	}

	var oButtonStrip = document.getElementById(this.opt.sButtonStrip);
	var oButtonStripDisplay = document.getElementById(this.opt.sButtonStripDisplay);

	// Make sure it can go somewhere.
	if (oButtonStripDisplay)
		oButtonStripDisplay.style.display = "";
	else
	{
		oButtonStripDisplay = document.createElement('div');
		oNewDiv.id = this.opt.sButtonStripDisplay;
		oNewDiv.className = this.opt.sButtonStripClass || 'buttonlist floatbottom';

		oButtonStrip.appendChild(oButtonStripDisplay);
	}

	// Add the 'remove selected items' button.
	if (this.opt.bCanRemove)
		this.oRemoveButton = smf_addButton(this.opt.sButtonStripDisplay, this.opt.bUseImageButton, {
			sText: this.opt.sRemoveButtonLabel,
			sImage: this.opt.sRemoveButtonImage,
			sUrl: '#',
			aEvents: [
				['click', this.handleSubmit.bind(this, 'remove')]
			]
		});

	// Add the 'restore selected items' button.
	if (this.opt.bCanRestore)
		this.oRestoreButton = smf_addButton(this.opt.sButtonStripDisplay, this.opt.bUseImageButton, {
			sText: this.opt.sRestoreButtonLabel,
			sImage: this.opt.sRestoreButtonImage,
			sUrl: '#',
			aEvents: [
				['click', this.handleSubmit.bind(this, 'restore')]
			]
		});

	// Add the 'split selected items' button.
	if (this.opt.bCanSplit)
		this.oSplitButton = smf_addButton(this.opt.sButtonStripDisplay, this.opt.bUseImageButton, {
			sText: this.opt.sSplitButtonLabel,
			sImage: this.opt.sSplitButtonImage,
			sUrl: '#',
			aEvents: [
				['click', this.handleSubmit.bind(this, 'split')]
			]
		});
}

InTopicModeration.prototype.handleClick = function(oCheckbox)
{

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;

	// Show the number of messages selected in each of the buttons.
	if (this.opt.bCanRemove && !this.opt.bUseImageButton)
	{
		this.oRemoveButton.innerHTML = this.opt.sRemoveButtonLabel + ' [' + this.iNumSelected + ']';
		this.oRemoveButton.style.display = this.iNumSelected < 1 ? "none" : "";
	}

	if (this.opt.bCanRestore && !this.opt.bUseImageButton)
	{
		this.oRestoreButton.innerHTML = this.opt.sRestoreButtonLabel + ' [' + this.iNumSelected + ']';
		this.oRestoreButton.style.display = this.iNumSelected < 1 ? "none" : "";
	}

	if (this.opt.bCanSplit && !this.opt.bUseImageButton)
	{
		this.oSplitButton.innerHTML = this.opt.sSplitButtonLabel + ' [' + this.iNumSelected + ']';
		this.oSplitButton.style.display = this.iNumSelected < 1 ? "none" : "";
	}
}

// Called when the user clicks one of the buttons that we added
InTopicModeration.prototype.handleSubmit = function (sSubmitType, oEvent)
{
	oEvent.preventDefault();
	var oForm = document.getElementById(this.opt.sFormId);

	// Make sure this form isn't submitted in another way than this function.
	var oInput = document.createElement('input');
	oInput.type = 'hidden';
	oInput.name = this.opt.sSessionVar;
	oInput.value = this.opt.sSessionId;
	oForm.appendChild(oInput);

	switch (sSubmitType)
	{
		case 'remove':
			if (!confirm(this.opt.sRemoveButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;split_selection=1/, '');
			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
		break;

		case 'restore':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;split_selection=1/, '');
			oForm.action += ';restore_selected=1';
		break;

		case 'split':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
			oForm.action += ';split_selection=1';
		break;

		default:
			return false;
		break;
	}

	oForm.submit();
	return true;
}


// *** Other functions...
function ignore_toggles(msgids, text)
{
	for (i = 0; i < msgids.length; i++)
	{
		var msgid = msgids[i];
		new smc_Toggle({
			bToggleEnabled: true,
			bCurrentlyCollapsed: true,
			aSwappableContainers: [
				'msg_' + msgid + '_extra_info',
				'msg_' + msgid,
				'msg_' + msgid + '_footer',
				'msg_' + msgid + '_quick_mod',
				'modify_button_' + msgid,
				'msg_' + msgid + '_signature',
				'msg_' + msgid + '_likes'

			],
			aSwapLinks: [
				{
					sId: 'msg_' + msgid + '_ignored_link',
					msgExpanded: '',
					msgCollapsed: text
				}
			]
		});
	}
}

// On document ready.
$(function() {

	// Likes count for messages.
	$(document).on('click', '.like_count a', function(e){
		e.preventDefault();
		var title = $(this).parent().text(),
			url = $(this).attr('href') + ';js=1';
		return reqOverlayDiv(url, title, 'like');
	});

	// Message likes.
	$(document).on('click', '.msg_like', function(event){
		var obj = $(this);
		event.preventDefault();
		ajax_indicator(true);
		$.ajax({
			type: 'GET',
			url: obj.attr('href') + ';js=1',
			headers: {
				"X-SMF-AJAX": 1
			},
			xhrFields: {
				withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
			},
			cache: false,
			dataType: 'html',
			success: function(html){
				obj.closest('ul').replaceWith(html);
			},
			error: function (html){
			},
			complete: function (){
				ajax_indicator(false);
			}
		});

		return false;
	});

	$('.button_strip_notify').next().find('a').click(function (e) {
		var $obj = $(this);
		e.preventDefault();
		ajax_indicator(true);
		$.get($obj.attr('href') + ';xml', function () {
			ajax_indicator(false);
			$('.button_strip_notify').text($obj.find('span').first().text());
		});

		return false;
	});
});