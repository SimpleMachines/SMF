// *** QuickModifyTopic object.
function QuickModifyTopic(oOptions)
{
	this.opt = oOptions;
	this.aHidePrefixes = this.opt.aHidePrefixes;
	this.iCurTopicId = 0;
	this.sCurMessageId = '';
	this.sBuffSubject = '';
	this.oCurSubjectDiv = null;
	this.oTopicModHandle = this.opt.oTopicModHandle || document;
	this.bInEditMode = false;
	this.aTextFields = ['subject'];
	this.oSourceElments = {};

	const oElement = this.oTopicModHandle.getElementById(oOptions.sTopicContainer);
	for (const el of oElement.children)
	{
		if (el.children[1].dataset.msgId)
			el.children[1].addEventListener(
				'dblclick',
				this.modify_topic.bind(this, el.children[1].dataset.msgId)
			);
	}

	// detect and act on keypress
	this.oTopicModHandle.onkeydown = this.modify_topic_keypress.bind(this);

	// Used to detect when we've stopped editing.
	this.oTopicModHandle.addEventListener('click', function (oEvent)
	{
		if (this.bInEditMode && oEvent.target.tagName != 'INPUT')
			this.modify_topic_save(smf_session_id, smf_session_var);
	}.bind(this));
}

// called from the double click in the div
QuickModifyTopic.prototype.modify_topic = function (topic_id, first_msg_id)
{
	if (this.bInEditMode)
	{
		// Same message then just return, otherwise drop out of this edit.
		if (this.iCurTopicId == topic_id)
			return;
		else
			this.modify_topic_cancel();
	}

	this.bInEditMode = true;
	this.iCurTopicId = topic_id;

	this.sCurMessageId = 'msg_' + first_msg_id;
	this.oCurSubjectDiv = document.getElementById('msg_' + first_msg_id);
	var oInput = document.createElement('input');
	oInput.type = 'text';
	oInput.name = 'subject';
	oInput.value = this.oCurSubjectDiv.textContent;
	oInput.size = '60';
	oInput.style.width = '99%';
	oInput.maxlength = '80';
	oInput.onkeydown = this.modify_topic_keypress.bind(this);
	this.oCurSubjectDiv.after(oInput);
	oInput.focus();

	if (this.opt.funcOnAfterCreate) {
		this.opt.funcOnAfterCreate.call(this);
	}

	// Here we hide any other things they want hidden on edit.
	this.set_hidden_topic_areas('none');
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

	this.set_hidden_topic_areas('none');

	// Show we are in edit mode and allow the edit
	ajax_indicator(false);
	this.modify_topic_show_edit(XMLDoc.getElementsByTagName("subject")[0].childNodes[0].nodeValue);
}

// Cancel out of an edit and return things to back to what they were
QuickModifyTopic.prototype.modify_topic_cancel = function ()
{
	for (var i of this.aTextFields)
		if (i in document.forms.quickModForm)
			document.forms.quickModForm[i].remove();

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

// Yup thats right, save it
QuickModifyTopic.prototype.modify_topic_save = function (cur_session_id, cur_session_var)
{
	if (!this.bInEditMode)
		return true;

	let x = [];
	for (var i of this.aTextFields)
		if (i in document.forms.quickModForm)
			x.push(i + '=' + document.forms.quickModForm[i].value.php_to8bit().php_urlencode());

	// send in the call to save the updated topic subject
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + "action=jsmodify;topic=" + this.iCurTopicId + ";" + cur_session_var + "=" + cur_session_id + ";xml", x.join("&"), this.modify_topic_done);

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
	var subject = message.getElementsByTagName("subject")[0].childNodes[0].nodeValue;
	var error = message.getElementsByTagName("error")[0];

	// No subject or other error?
	if (!subject || error)
		return false;

	setInnerHTML(this.oCurSubjectDiv, '<a href="' + smf_scripturl + '?topic=' + this.iCurTopicId + '.0">' + subject + '<' +'/a>')
	this.set_hidden_topic_areas('');
	this.bInEditMode = false;

	for (var i of this.aTextFields)
	{
		if (this.oSourceElments[i])
			setInnerHTML(this.oSourceElments[i], message.getElementsByTagName(i)[0].childNodes[0].nodeValue);

		if (i in document.forms.quickModForm)
			document.forms.quickModForm[i].remove();
	}

	// redo tips if they are on since we just pulled the rug out on this one
	if ($.isFunction($.fn.SMFtooltip))
		$('.preview').SMFtooltip().smf_tooltip_off;

	return false;
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
	this.sMessageBuffer = '';
	this.sSubjectBuffer = '';
	this.aAccessKeys = new Array();
}

// Function called when a user presses the edit button.
QuickModify.prototype.modifyMsg = function (iMessageId)
{
	// Add backwards compatibility with old themes.
	if (typeof(sSessionVar) == 'undefined')
		sSessionVar = 'sesc';

	// Removes the accesskeys from the quickreply inputs and saves them in an array to use them later
	if (typeof(this.opt.sFormRemoveAccessKeys) != 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (var i = 0; i < aInputs.length; i++)
			{
				if (aInputs[i].accessKey != '')
				{
					this.aAccessKeys[aInputs[i].name] = aInputs[i].accessKey;
					aInputs[i].accessKey = '';
				}
			}
		}
	}

	// First cancel if there's another message still being edited.
	if (this.bInEditMode)
		this.modifyCancel();

	// At least NOW we're in edit mode
	this.bInEditMode = true;

	// Send out the XMLhttp request to get more info
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + iMessageId + ';modify;xml;' + smf_session_var + '=' + smf_session_id, '', this.onMessageReceived);

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

	// Field for editing reason.
	sReasonText = XMLDoc.getElementsByTagName('reason')[0].childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');

	$(this.oCurMessageDiv).prepend(this.opt.sTemplateReasonEdit.replace(/%modify_reason%/, sReasonText).replace(/\{&dollarfix;\$\}/g, '$'));

	return true;
}

// Function in case the user presses cancel (or other circumstances cause it).
QuickModify.prototype.modifyCancel = function ()
{
	// Roll back the HTML to its original state.
	if (this.oCurMessageDiv)
	{
		this.oCurMessageDiv.innerHTML = this.sMessageBuffer;
		this.oCurSubjectDiv.innerHTML = this.sSubjectBuffer;
	}

	// No longer in edit mode, that's right.
	this.bInEditMode = false;

	// Let's put back the accesskeys to their original place
	if (typeof(this.opt.sFormRemoveAccessKeys) != 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (var i = 0; i < aInputs.length; i++)
			{
				if (typeof(this.aAccessKeys[aInputs[i].name]) != 'undefined')
				{
					aInputs[i].accessKey = this.aAccessKeys[aInputs[i].name];
				}
			}
		}
	}

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

	// Let's put back the accesskeys to their original place
	if (typeof(this.opt.sFormRemoveAccessKeys) != 'undefined')
	{
		if (typeof(document.forms[this.opt.sFormRemoveAccessKeys]))
		{
			var aInputs = document.forms[this.opt.sFormRemoveAccessKeys].getElementsByTagName('input');
			for (var i = 0; i < aInputs.length; i++)
			{
				if (typeof(this.aAccessKeys[aInputs[i].name]) != 'undefined')
				{
					aInputs[i].accessKey = this.aAccessKeys[aInputs[i].name];
				}
			}
		}
	}

	var i, x = new Array(),
		oCaller = this,
		formData = {
			subject : document.forms.quickModForm['subject'].value,
			message : document.forms.quickModForm['message'].value,
			topic : parseInt(document.forms.quickModForm.elements['topic'].value),
			msg : parseInt(document.forms.quickModForm.elements['msg'].value),
			modify_reason : document.forms.quickModForm.elements['modify_reason'].value
		};

	// Send in the XMLhttp request and let's hope for the best.
	ajax_indicator(true);

	sendXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + "action=jsmodify;topic=" + this.opt.iTopicId + ";" + smf_session_var + "=" + smf_session_id + ";xml", formData, this.onModifyDone);

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

		// Show new subject, but only if we want to...
		var oSubject = message.getElementsByTagName('subject')[0];
		var sSubjectText = oSubject.childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');
		var sTopSubjectText = oSubject.childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}');
		this.sSubjectBuffer = this.opt.sTemplateSubjectNormal.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g,'$');
		setInnerHTML(this.oCurSubjectDiv, this.sSubjectBuffer);

		// If this is the first message, also update the topic subject.
		if (oSubject.getAttribute('is_first') == '1')
			setInnerHTML(document.getElementById('top_subject'), this.opt.sTemplateTopSubject.replace(/%subject%/, sTopSubjectText).replace(/\{&dollarfix;\$\}/g, '$'));

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
			$('#modified_' + this.sCurMessageId.substr(4)).html(message.getElementsByTagName('modified')[0].childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}'));

		// Show a message indicating the edit was successfully done.
		$('<div/>',{
			text: message.getElementsByTagName('success')[0].childNodes[0].nodeValue.replace(/\$/g, '{&dollarfix;$}'),
			class: 'infobox'
		}).prependTo('#' + this.sCurMessageId).delay(5000).fadeOutAndRemove(400);
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
			$('.button_strip_notify').text($obj.find('strong').text());
		});

		return false;
	});
});