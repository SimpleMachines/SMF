class JumpTo {
	static instances = [];

	constructor(opt) {
		this.opt = opt;
		this.dropdownList = null;
		this.oContainer = document.getElementById(opt.sContainerId);
		this.sTemplate = opt.sJumpToTemplate || '%dropdown_list%';
		this.showSelect();

		let timeout = null;

		// Register instance
		JumpTo.instances.push(this);

		// Detect if a "coarse pointer" (usually a touch screen) is the primary input device.
		if (window.matchMedia("(pointer: coarse)").matches)
		{
			const focusHandler = () =>
			{
				this.grabJumpToContent();
				this.oContainer.removeEventListener('focus', focusHandler);
			};
			this.oContainer.addEventListener('focus', focusHandler);
		}
		else
		{
			const mouseOverHandler = () =>
			{
				timeout = setTimeout(() =>
				{
					this.grabJumpToContent();
					this.oContainer.removeEventListener('mouseover', mouseOverHandler);
					this.oContainer.removeEventListener('mouseout', mouseOutHandler);
				}, 200);
			};

			const mouseOutHandler = () =>
			{
				clearTimeout(timeout);
			};

			this.oContainer.addEventListener('mouseover', mouseOverHandler);
			this.oContainer.addEventListener('mouseout', mouseOutHandler);
		}
	}

	// This function will retrieve the contents needed for the jump to boxes.
	grabJumpToContent()
	{
		ajax_indicator(true);

		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=jumpto;xml', (xml) =>
		{
			const items = xml.getElementsByTagName('smf')[0].getElementsByTagName('item');
			const boards = [];

			for (let i = 0; i < items.length; i++)
			{
				const item = items[i];
				boards.push({
					id: parseInt(item.getAttribute('id')),
					isCategory: item.getAttribute('type') === 'category',
					name: item.firstChild.nodeValue.removeEntities(),
					is_current: false,
					isRedirect: parseInt(item.getAttribute('is_redirect')),
					childLevel: parseInt(item.getAttribute('childlevel'))
				});
			}

			ajax_indicator(false);

			for (let i = 0; i < JumpTo.instances.length; i++)
			{
				JumpTo.instances[i].fillSelect(boards);
			}
		});
	}

	// Show select using template
	showSelect()
	{
		const el = this.oContainer;
		const frag = parseTemplateToFragment(this.sTemplate);

		// Create select element
		const select = document.createElement('select');
		select.id = this.opt.sContainerId + '_select';
		select.name = this.opt.sCustomName || select.id;
		if (this.opt.sClassName)
		{
			select.className = this.opt.sClassName;
		}
		if (this.opt.bDisabled)
		{
			select.disabled = true;
		}

		// Default option
		const defaultOption = document.createElement('option');
		defaultOption.value = this.opt.bNoRedirect ? this.opt.iCurBoardId : '?board=' + this.opt.iCurBoardId + '.0';
		defaultOption.textContent = this.opt.sBoardChildLevelIndicator.repeat(this.opt.iCurBoardChildLevel) + this.opt.sBoardPrefix + this.opt.sCurBoardName.removeEntities();
		select.appendChild(defaultOption);

		// Replace placeholders using node walker
		replacePlaceholder(frag, '%select_id%', this.opt.sContainerId + '_select');
		replacePlaceholder(frag, '%dropdown_list%', select);

		if (this.opt.sGoButtonLabel)
		{
			const btn = document.createElement('button');
			btn.className = 'button';
			btn.textContent = this.opt.sGoButtonLabel;
			btn.addEventListener('click', () =>
			{
				window.location.href = smf_prepareScriptUrl(smf_scripturl) + (this.opt.sUrlPrefix || '') + 'board=' + this.opt.iCurBoardId + '.0';
			});

			frag.append(' ', btn);
		}

		// Append processed template to container
		el.innerHTML = ''; // clear existing
		el.appendChild(frag);

		this.dropdownList = select;

		if (!this.opt.bNoRedirect)
		{
			select.addEventListener('change', function(self)
			{
				const val = this.options[this.selectedIndex].value;
				if (this.selectedIndex > 0 && val)
				{
					window.location.href = smf_prepareScriptUrl(smf_scripturl) + (self.opt.sUrlPrefix || '') + (val.startsWith('?') ? val.substring(1) : val);
				}
			}.bind(select, this));
		}
	}

	// Fill select with boards/categories
	fillSelect(boards)
	{
		if (!this.dropdownList)
		{
			return;
		}

		const fragment = document.createDocumentFragment();
		const dashOptionTemplate = document.createElement('option');
		dashOptionTemplate.textContent = this.opt.sCatSeparator;
		dashOptionTemplate.disabled = true;

		if (this.opt.bNoRedirect)
		{
			if (this.dropdownList.options[0])
			{
				this.dropdownList.options[0].disabled = true;
			}
		}

		let lastWasCategory = false;

		for (let i = 0; i < boards.length; i++)
		{
			const item = boards[i];

			// If we've reached the currently selected board add all items so far.
			if (!item.isCategory && item.id === this.opt.iCurBoardId)
			{
				this.dropdownList.insertBefore(fragment, this.dropdownList.options[0]);
				continue;
			}

			if (item.isCategory)
			{
				if (!lastWasCategory)
				{
					fragment.appendChild(dashOptionTemplate.cloneNode(true));
					lastWasCategory = true;
				}
			}
			else
			{
				lastWasCategory = false;
			}

			const option = document.createElement('option');
			option.textContent = (item.isCategory ? this.opt.sCatPrefix : this.opt.sBoardChildLevelIndicator.repeat(item.childLevel) + this.opt.sBoardPrefix) + item.name;
			option.value = item.isCategory ? '#c' + item.id : '?board=' + item.id + '.0';

			if (this.opt.bNoRedirect && (item.isCategory || item.isRedirect))
			{
				option.disabled = true;
			}

			fragment.appendChild(option);

			if (item.isCategory)
			{
				fragment.appendChild(dashOptionTemplate.cloneNode(true));
			}
		}

		// Add the remaining items after the currently selected item.
		this.dropdownList.appendChild(fragment);
	}
}

// *** IconList object
function IconList(options) {
	this.opt = options || {};

	// Default CSS classes
	this.opt.sBoxClass = this.opt.sBoxClass || 'icon_list_box';
	this.opt.sContainerClass = this.opt.sContainerClass || 'icon_list_container';
	this.opt.sItemClass = this.opt.sItemClass || 'icon_list_item';

	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.iCurMessageId = 0;
	this.oClickedIcon = null;

	if (!IconList.instances) IconList.instances = [];
	IconList.instances.push(this);

	this.initIcons();
}

// Replace all message icons by icons with hoverable and clickable div's.
IconList.prototype.initIcons = function () {
	const prefixLength = this.opt.sIconIdPrefix.length;
	const imgs = document.images;

	for (let i = 0; i < imgs.length; i++) {
		const img = imgs[i];
		if (img.id.substr(0, prefixLength) === this.opt.sIconIdPrefix) {
			const div = document.createElement('div');
			div.className = this.opt.sBoxClass;
			div.appendChild(img.cloneNode(true));

			div.addEventListener('click', this.openPopup.bind(this, div, parseInt(img.id.substr(prefixLength), 10)));

			img.parentNode.replaceChild(div, img);
		}
	}
};

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (div, messageId) {
	this.iCurMessageId = messageId;
	this.oClickedIcon = div;

	if (!this.bListLoaded && this.oContainerDiv == null) {
		this.oContainerDiv = document.createElement('div');
		this.oContainerDiv.className = this.opt.sContainerClass;
		document.body.appendChild(this.oContainerDiv);

		ajax_indicator(true);
		getXMLDocument(
			smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=messageicons;board=' + this.opt.iBoardId + ';xml',
			this.onIconsReceived.bind(this)
		);
	}

	const pos = smf_itemPos(div);
	this.oContainerDiv.style.top = (pos[1] + div.offsetHeight) + 'px';
	this.oContainerDiv.style.left = (pos[0] - 1) + 'px';

	if (this.bListLoaded) this.oContainerDiv.style.display = 'block';

	document.body.addEventListener('mousedown', IconList.onWindowMouseDown);
};

// Setup the list of icons once it is received through xmlHTTP.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	if (!oXMLDoc) return;

	ajax_indicator(false);
	const icons = oXMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('icon');
	const frag = document.createDocumentFragment();

	for (let i = 0; i < icons.length; i++) {
		const icon = icons[i];
		const span = document.createElement('span');
		span.className = this.opt.sItemClass;

		const img = document.createElement('img');
		img.src = icon.getAttribute('url');
		img.alt = icon.getAttribute('name');
		img.title = icon.firstChild ? icon.firstChild.nodeValue : '';
		img.style.verticalAlign = 'middle';

		span.appendChild(img);

		span.addEventListener('pointerdown', this.onItemMouseDown.bind(this, span, icon.getAttribute('value')));

		frag.appendChild(span);
	}

	this.oContainerDiv.appendChild(frag);
	this.oContainerDiv.style.display = 'block';
	this.bListLoaded = true;
};

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function(span, newIcon) {
	if (this.iCurMessageId === 0) return;

	ajax_indicator(true);
	getXMLDocument(
		smf_prepareScriptUrl(smf_scripturl) +
		'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' +
		smf_session_var + '=' + smf_session_id + ';icon=' + newIcon + ';xml',
		oXMLDoc => {
			ajax_indicator(false);
			if (!oXMLDoc) return;

			const messageEl = oXMLDoc.getElementsByTagName('message')[0];
			if (!messageEl) return;

			const curMessageId = (messageEl.getAttribute('id') || '').replace(/^\D+/g, '');
			if (!messageEl.getElementsByTagName('error')[0]) {
				const modifiedEl = messageEl.getElementsByTagName('modified')[0];
				if (this.opt.bShowModify && modifiedEl) {
					const modContainer = document.getElementById('modified_' + curMessageId);
					if (modContainer) modContainer.innerHTML = modifiedEl.textContent;
				}
				const img = this.oClickedIcon.getElementsByTagName('img')[0];
				if (img) img.src = span.getElementsByTagName('img')[0].src;
			}
		}
	);
};

// Event handler for clicking outside the list (will make the list disappear).
IconList.onWindowMouseDown = function() {
	if (!IconList.instances) return;
	for (const inst of IconList.instances) {
		inst.collapseList();
	}
};

// Collapse the list of icons.
IconList.prototype.collapseList = function() {
	if (!this.oClickedIcon || !this.oContainerDiv) return;
	this.oContainerDiv.style.display = 'none';
	this.iCurMessageId = 0;
};

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

	for (const el of document.getElementsByClassName(this.opt.sClassName)) {
		el.hidden = false;
		el.addEventListener('click', this.modifyMsg.bind(this, el.id.match(/\d+/)));
	}

	document.forms[this.opt.sFormName].addEventListener('submit', function() {
		if (this.bInEditMode) {
			this.modifySave();
		}
	}.bind(this));
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

	reasonLabel.append(this.opt.sEditReasonText, reasonInput);
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
	/*
	 * The topic draws two of these strips - one for the page and one for the
	 * mobile menu - and they share the containers, so the second instance
	 * finds the checkboxes the first one already made. Collect those once,
	 * under the message id each one carries, and listen to them as well
	 * rather than putting a second checkbox beside every post. This file is
	 * the only thing that emits a msgs[] input, so there is nothing else on
	 * the page for this to pick up.
	 */
	var oExisting = {};
	var aCheckboxes = document.querySelectorAll('input[name="msgs[]"]');

	for (var j = 0, m = aCheckboxes.length; j < m; j++)
		oExisting[aCheckboxes[j].value] = aCheckboxes[j];

	// Add checkboxes to all the messages.
	for (var i = 0, n = this.opt.aMessageIds.length; i < n; i++)
	{
		// Append it to the container
		var oCheckboxContainer = document.getElementById(this.opt.sCheckboxContainerMask + this.opt.aMessageIds[i]);
		var oCheckbox = oExisting[this.opt.aMessageIds[i]];

		if (!oCheckbox)
		{
			// Create the checkbox.
			oCheckbox = document.createElement('input');
			oCheckbox.type = 'checkbox';
			oCheckbox.className = this.opt.sButtonStrip + '_check';
			oCheckbox.name = 'msgs[]';
			oCheckbox.value = this.opt.aMessageIds[i];

			oCheckboxContainer.appendChild(oCheckbox);
		}

		oCheckbox.addEventListener('click', this.handleClick.bind(this, oCheckbox));
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
		oButtonStripDisplay.id = this.opt.sButtonStripDisplay;
		oButtonStripDisplay.className = this.opt.sButtonStripClass || 'buttonlist floatbottom';

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

	/*
	 * Nothing is selected yet, so put the buttons in the state that says so.
	 * They used to be built on the first click instead of here, which is why
	 * nothing hid them to begin with - and pressing one with an empty
	 * selection submits the form with no msgs[] at all.
	 */
	this.updateButtons();
}

InTopicModeration.prototype.handleClick = function(oCheckbox)
{

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;

	this.updateButtons();
}

// Show the number of messages selected in each of the buttons, and hide them
// while that number is zero.
InTopicModeration.prototype.updateButtons = function()
{
	var aButtons = [
		[this.opt.bCanRemove, this.oRemoveButton, this.opt.sRemoveButtonLabel],
		[this.opt.bCanRestore, this.oRestoreButton, this.opt.sRestoreButtonLabel],
		[this.opt.bCanSplit, this.oSplitButton, this.opt.sSplitButtonLabel]
	];

	for (var i = 0; i < aButtons.length; i++)
	{
		if (!aButtons[i][0] || !aButtons[i][1])
			continue;

		if (!this.opt.bUseImageButton)
			aButtons[i][1].innerHTML = aButtons[i][2] + ' [' + this.iNumSelected + ']';

		aButtons[i][1].style.display = this.iNumSelected < 1 ? "none" : "";
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