var cur_topic_id, cur_msg_id, buff_subject, cur_subject_div, in_edit_mode = 0;
var hide_prefixes = Array();

function modify_topic(topic_id, first_msg_id)
{
	if (!('XMLHttpRequest' in window))
		return;

	if ('opera' in window)
	{
		var oTest = new XMLHttpRequest();
		if (!('setRequestHeader' in oTest))
			return;
	}

	// Add backwards compatibility with old themes.
	if (typeof(cur_session_var) == 'undefined')
		cur_session_var = 'sesc';

	if (in_edit_mode == 1)
	{
		if (cur_topic_id == topic_id)
			return;
		else
			modify_topic_cancel();
	}

	in_edit_mode = 1;
	mouse_on_div = 1;
	cur_topic_id = topic_id;

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);
	getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=quotefast;quote=" + first_msg_id + ";modify;xml", onDocReceived_modify_topic);
}

function onDocReceived_modify_topic(XMLDoc)
{
	cur_msg_id = XMLDoc.getElementsByTagName("message")[0].getAttribute("id");

	cur_subject_div = document.getElementById('msg_' + cur_msg_id.substr(4));
	buff_subject = getInnerHTML(cur_subject_div);

	// Here we hide any other things they want hiding on edit.
	set_hidden_topic_areas('none');

	modify_topic_show_edit(XMLDoc.getElementsByTagName("subject")[0].childNodes[0].nodeValue);
	if (typeof window.ajax_indicator == "function")
		ajax_indicator(false);
}

function modify_topic_cancel()
{
	setInnerHTML(cur_subject_div, buff_subject);
	set_hidden_topic_areas('');

	in_edit_mode = 0;
	return false;
}

function modify_topic_save(cur_session_id, cur_session_var)
{
	if (!in_edit_mode)
		return true;

	// Add backwards compatibility with old themes.
	if (typeof(cur_session_var) == 'undefined')
		cur_session_var = 'sesc';

	var i, x = new Array();
	x[x.length] = 'subject=' + document.forms.quickModForm['subject'].value.replace(/&#/g, "&#38;#").php_to8bit().php_urlencode();
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements['topic'].value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements['msg'].value);

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);
	sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=jsmodify;topic=" + parseInt(document.forms.quickModForm.elements['topic'].value) + ";" + cur_session_var + "=" + cur_session_id + ";xml", x.join("&"), modify_topic_done);

	return false;
}

function modify_topic_done(XMLDoc)
{
	if (!XMLDoc)
	{
		modify_topic_cancel();
		return true;
	}

	var message = XMLDoc.getElementsByTagName("smf")[0].getElementsByTagName("message")[0];
	var subject = message.getElementsByTagName("subject")[0];
	var error = message.getElementsByTagName("error")[0];

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(false);

	if (!subject || error)
		return false;

	subjectText = subject.childNodes[0].nodeValue;

	modify_topic_hide_edit(subjectText);

	set_hidden_topic_areas('');

	in_edit_mode = 0;

	return false;
}

// Simply restore any hidden bits during topic editing.
function set_hidden_topic_areas(set_style)
{
	for (var i = 0; i < hide_prefixes.length; i++)
	{
		if (document.getElementById(hide_prefixes[i] + cur_msg_id.substr(4)) != null)
			document.getElementById(hide_prefixes[i] + cur_msg_id.substr(4)).style.display = set_style;
	}
}

// *** QuickReply object.
function QuickReply(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = this.opt.bDefaultCollapsed;
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
	document.getElementById(this.opt.sImageId).src = this.opt.sImagesUrl + "/" + (this.bCollapsed ? this.opt.sImageCollapsed : this.opt.sImageExpanded);
	document.getElementById(this.opt.sContainerId).style.display = this.bCollapsed ? '' : 'none';

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
	this.sMessageBuffer = '';
	this.sSubjectBuffer = '';
	this.bXmlHttpCapable = this.isXmlHttpCapable();

	// Show the edit buttons
	if (this.bXmlHttpCapable)
	{
		for (var i = document.images.length - 1; i >= 0; i--)
			if (document.images[i].id.substr(0, 14) == 'modify_button_')
				document.images[i].style.display = '';
	}
}

// Determine whether the quick modify can actually be used.
QuickModify.prototype.isXmlHttpCapable = function ()
{
	if (typeof(window.XMLHttpRequest) == 'undefined')
		return false;

	// Opera didn't always support POST requests. So test it first.
	if ('opera' in window)
	{
		var oTest = new XMLHttpRequest();
		if (!('setRequestHeader' in oTest))
			return false;
	}

	return true;
}

// Function called when a user presses the edit button.
QuickModify.prototype.modifyMsg = function (iMessageId)
{
	if (!this.bXmlHttpCapable)
		return;

	// Add backwards compatibility with old themes.
	if (typeof(sSessionVar) == 'undefined')
		sSessionVar = 'sesc';

	// First cancel if there's another message still being edited.
	if (this.bInEditMode)
		this.modifyCancel();

	// At least NOW we're in edit mode
	this.bInEditMode = true;

	// Send out the XMLhttp request to get more info
	ajax_indicator(true);

	// For IE 5.0 support, 'call' is not yet used.
	this.tmpMethod = getXMLDocument;
	this.tmpMethod(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId + ';modify;xml', this.onMessageReceived);
	delete this.tmpMethod;
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
	for (var i = 0; i < XMLDoc.getElementsByTagName("message")[0].childNodes.length; i++)
		sBodyText += XMLDoc.getElementsByTagName("message")[0].childNodes[i].nodeValue;
	this.oCurMessageDiv = document.getElementById(this.sCurMessageId);
	this.sMessageBuffer = getInnerHTML(this.oCurMessageDiv);

	// We have to force the body to lose its dollar signs thanks to IE.
	sBodyText = sBodyText.replace(/\$/g, '{&dollarfix;$}');

	// Actually create the content, with a bodge for disappearing dollar signs.
	setInnerHTML(this.oCurMessageDiv, this.opt.sTemplateBodyEdit.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%body%/, sBodyText).replace(/\{&dollarfix;\$\}/g, '$'));

	// Replace the subject part.
	this.oCurSubjectDiv = document.getElementById('subject_' + this.sCurMessageId.substr(4));
	this.sSubjectBuffer = getInnerHTML(this.oCurSubjectDiv);

	sSubjectText = XMLDoc.getElementsByTagName('subject')[0].childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');
	setInnerHTML(this.oCurSubjectDiv, this.opt.sTemplateSubjectEdit.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$'));

	return true;
}

// Function in case the user presses cancel (or other circumstances cause it).
QuickModify.prototype.modifyCancel = function ()
{
	// Roll back the HTML to its original state.
	if (this.oCurMessageDiv)
	{
		setInnerHTML(this.oCurMessageDiv, this.sMessageBuffer);
		setInnerHTML(this.oCurSubjectDiv, this.sSubjectBuffer);
	}

	// No longer in edit mode, that's right.
	this.bInEditMode = false;

	return false;
}

// The function called after a user wants to save his precious message.
QuickModify.prototype.modifySave = function (sSessionId, sSessionVar)
{
	// We cannot save if we weren't in edit mode.
	if (!this.bInEditMode)
		return true;

	// Add backwards compatibility with old themes.
	if (typeof(sSessionVar) == 'undefined')
		sSessionVar = 'sesc';

	var i, x = new Array();
	x[x.length] = 'subject=' + escape(document.forms.quickModForm['subject'].value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B");
	x[x.length] = 'message=' + escape(document.forms.quickModForm['message'].value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B");
	x[x.length] = 'topic=' + parseInt(document.forms.quickModForm.elements['topic'].value);
	x[x.length] = 'msg=' + parseInt(document.forms.quickModForm.elements['msg'].value);

	// Send in the XMLhttp request and let's hope for the best.
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + "action=jsmodify;topic=" + this.opt.iTopicId + ";" + sSessionVar + "=" + sSessionId + ";xml", x.join("&"), this.onModifyDone);

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
		// Mozilla will nicely tell us what's wrong.
		if (XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror')
			setInnerHTML(document.getElementById('error_box'), XMLDoc.firstChild.textContent);
		else
			this.modifyCancel();
		return;
	}

	var message = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('message')[0];
	var body = message.getElementsByTagName('body')[0];
	var error = message.getElementsByTagName('error')[0];

	if (body)
	{
		// Show new body.
		var bodyText = '';
		for (var i = 0; i < body.childNodes.length; i++)
			bodyText += body.childNodes[i].nodeValue;

		this.sMessageBuffer = this.opt.sTemplateBodyNormal.replace(/%body%/, bodyText.replace(/\$/g, '{&dollarfix;$}')).replace(/\{&dollarfix;\$\}/g,'$');
		setInnerHTML(this.oCurMessageDiv, this.sMessageBuffer);

		// Show new subject.
		var oSubject = message.getElementsByTagName('subject')[0];
		var sSubjectText = oSubject.childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');
		this.sSubjectBuffer = this.opt.sTemplateSubjectNormal.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g,'$');
		setInnerHTML(this.oCurSubjectDiv, this.sSubjectBuffer);

		// If this is the first message, also update the topic subject.
		if (oSubject.getAttribute('is_first') == '1')
			setInnerHTML(document.getElementById('top_subject'), this.opt.sTemplateTopSubject.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$'));

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
			setInnerHTML(document.getElementById('modified_' + this.sCurMessageId.substr(4)), message.getElementsByTagName('modified')[0].childNodes[0].nodeValue);
	}
	else if (error)
	{
		setInnerHTML(document.getElementById('error_box'), error.childNodes[0].nodeValue);
		document.forms.quickModForm.message.style.border = error.getAttribute('in_body') == '1' ? this.opt.sErrorBorderStyle : '';
		document.forms.quickModForm.subject.style.border = error.getAttribute('in_subject') == '1' ? this.opt.sErrorBorderStyle : '';
	}
}

function InTopicModeration(oOptions)
{
	this.opt = oOptions;
	this.bButtonsShown = false;
	this.iNumSelected = 0;

	// Add backwards compatibility with old themes.
	if (typeof(this.opt.sSessionVar) == 'undefined')
		this.opt.sSessionVar = 'sesc';

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
		oCheckbox.className = 'input_check';
		oCheckbox.name = 'msgs[]';
		oCheckbox.value = this.opt.aMessageIds[i];
		oCheckbox.instanceRef = this;
		oCheckbox.onclick = function () {
			this.instanceRef.handleClick(this);
		}

		// Append it to the container
		var oCheckboxContainer = document.getElementById(this.opt.sCheckboxContainerMask + this.opt.aMessageIds[i]);
		oCheckboxContainer.appendChild(oCheckbox);
		oCheckboxContainer.style.display = '';
	}
}

InTopicModeration.prototype.handleClick = function(oCheckbox)
{
	if (!this.bButtonsShown && this.opt.sButtonStripDisplay)
	{
		var oButtonStrip = document.getElementById(this.opt.sButtonStrip);
		var oButtonStripDisplay = document.getElementById(this.opt.sButtonStripDisplay);

		// Make sure it can go somewhere.
		if (typeof(oButtonStripDisplay) == 'object' && oButtonStripDisplay != null)
			oButtonStripDisplay.style.display = "";
		else
		{
			var oNewDiv = document.createElement('div');
			var oNewList = document.createElement('ul');

			oNewDiv.id = this.opt.sButtonStripDisplay;
			oNewDiv.className = this.opt.sButtonStripClass ? this.opt.sButtonStripClass : 'buttonlist floatbottom';

			oNewDiv.appendChild(oNewList);
			oButtonStrip.appendChild(oNewDiv);
		}

		// Add the 'remove selected items' button.
		if (this.opt.bCanRemove)
			smf_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: this.opt.sSelf + '_remove_button',
				sText: this.opt.sRemoveButtonLabel,
				sImage: this.opt.sRemoveButtonImage,
				sUrl: '#',
				sCustom: ' onclick="return ' + this.opt.sSelf + '.handleSubmit(\'remove\')"'
			});

		// Add the 'restore selected items' button.
		if (this.opt.bCanRestore)
			smf_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: this.opt.sSelf + '_restore_button',
				sText: this.opt.sRestoreButtonLabel,
				sImage: this.opt.sRestoreButtonImage,
				sUrl: '#',
				sCustom: ' onclick="return ' + this.opt.sSelf + '.handleSubmit(\'restore\')"'
			});

		// Adding these buttons once should be enough.
		this.bButtonsShown = true;
	}

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;

	// Show the number of messages selected in the button.
	if (this.opt.bCanRemove && !this.opt.bUseImageButton)
	{
		setInnerHTML(document.getElementById(this.opt.sSelf + '_remove_button'), this.opt.sRemoveButtonLabel + ' [' + this.iNumSelected + ']');
		document.getElementById(this.opt.sSelf + '_remove_button').style.display = this.iNumSelected < 1 ? "none" : "";
	}

	if (this.opt.bCanRestore && !this.opt.bUseImageButton)
	{
		setInnerHTML(document.getElementById(this.opt.sSelf + '_restore_button'), this.opt.sRestoreButtonLabel + ' [' + this.iNumSelected + ']');
		document.getElementById(this.opt.sSelf + '_restore_button').style.display = this.iNumSelected < 1 ? "none" : "";
	}

	// Try to restore the correct position.
	var aItems = document.getElementById(this.opt.sButtonStrip).getElementsByTagName('span');
	if (aItems.length > 3)
	{
		if (this.iNumSelected < 1)
		{
			aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*position_holder/, 'last');
			aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*position_holder/, 'last');
		}
		else
		{
			aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*last/, 'position_holder');
			aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*last/, 'position_holder');
		}
	}
}

InTopicModeration.prototype.handleSubmit = function (sSubmitType)
{
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

			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
		break;

		case 'restore':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action + ';restore_selected=1';
		break;

		default:
			return false;
		break;
	}

	oForm.submit();
	return true;
}


// *** Other functions...
function expandThumb(thumbID)
{
	var img = document.getElementById('thumb_' + thumbID);
	var link = document.getElementById('link_' + thumbID);
	var tmp = img.src;
	img.src = link.href;
	link.href = tmp;
	img.style.width = '';
	img.style.height = '';
	return false;
}