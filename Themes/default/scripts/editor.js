// *** smc_Editor class.
function smc_Editor(oOptions)
{
	this.opt = oOptions;

	// Create some links to the editor object.
	this.oTextHandle = null;
	this.sCurrentText = 'sText' in this.opt ? this.opt.sText : '';

	// How big?
	this.sEditWidth = 'sEditWidth' in this.opt ? this.opt.sEditWidth : '70%';
	this.sEditHeight = 'sEditHeight' in this.opt ? this.opt.sEditHeight : '150px';

	this.showDebug = false;
	this.bRichTextEnabled = 'bWysiwyg' in this.opt && this.opt.bWysiwyg;
	// This doesn't work on Opera as they cannot restore focus after clicking a BBC button.
	this.bRichTextPossible = !this.opt.bRichEditOff && ((is_ie5up && !is_ie50) || is_ff || is_opera95up || is_safari || is_chrome) && !(is_iphone || is_android);

	this.oFrameHandle = null;
	this.oFrameDocument = null;
	this.oFrameWindow = null;

	// These hold the breadcrumb.
	this.oBreadHandle = null;
	this.oResizerElement = null;

	// Kinda holds all the useful stuff.
	this.aKeyboardShortcuts = new Array();

	// This tracks the cursor position on IE to avoid refocus problems.
	this.cursorX = 0;
	this.cursorY = 0;

	// This is all the elements that can have a simple execCommand.
	this.oSimpleExec = {
		b: 'bold',
		u: 'underline',
		i: 'italic',
		s: 'strikethrough',
		left: 'justifyleft',
		center: 'justifycenter',
		right: 'justifyright',
		hr: 'inserthorizontalrule',
		list: 'insertunorderedlist',
		orderlist: 'insertorderedlist',
		sub: 'subscript',
		sup: 'superscript',
		indent: 'indent',
		outdent: 'outdent'
	}

	// Codes to call a private function
	this.oSmfExec = {
		unformat: 'removeFormatting',
		toggle: 'toggleView'
	}

	// Any special breadcrumb mappings to ensure we show a consistant tag name.
	this.breadCrumbNameTags = {
		strike: 's',
		strong: 'b',
		em: 'i'
	}

	this.aBreadCrumbNameStyles = [
		{
			sStyleType: 'text-decoration',
			sStyleValue: 'underline',
			sBbcTag: 'u'
		},
		{
			sStyleType: 'text-decoration',
			sStyleValue: 'line-through',
			sBbcTag: 's'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'left',
			sBbcTag: 'left'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'center',
			sBbcTag: 'center'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'right',
			sBbcTag: 'right'
		},
		{
			sStyleType: 'font-weight',
			sStyleValue: 'bold',
			sBbcTag: 'b'
		},
		{
			sStyleType: 'font-style',
			sStyleValue: 'italic',
			sBbcTag: 'i'
		}
	];

	// All the fonts in the world.
	this.aFontFaces = [
		'Arial',
		'Arial Black',
		'Impact',
		'Verdana',
		'Times New Roman',
		'Georgia',
		'Andale Mono',
		'Trebuchet MS',
		'Comic Sans MS'
	];
	// Font maps (HTML => CSS size)
	this.aFontSizes = [
		0,
		8,
		10,
		12,
		14,
		18,
		24,
		36
	];
	// Color maps! (hex => name)
	this.oFontColors = {
		black: '#000000',
		red: '#ff0000',
		yellow: '#ffff00',
		pink: '#ffc0cb',
		green: '#008000',
		orange: '#ffa500',
		purple: '#800080',
		blue: '#0000ff',
		beige: '#f5f5dc',
		brown: '#a52a2a',
		teal: '#008080',
		navy: '#000080',
		maroon: '#800000',
		limegreen: '#32cd32'
	}

	this.sFormId = 'sFormId' in this.opt ? this.opt.sFormId : 'postmodify';
	this.iArrayPosition = smf_editorArray.length;

	// Current resize state.
	this.osmc_EditorCurrentResize = {};

	this.init();
}

smc_Editor.prototype.init = function()
{
	// Define the event wrapper functions.
	var oCaller = this;
	this.aEventWrappers = {
		editorKeyUp: function(oEvent) {return oCaller.editorKeyUp(oEvent);},
		shortcutCheck: function(oEvent) {return oCaller.shortcutCheck(oEvent);},
		editorBlur: function(oEvent) {return oCaller.editorBlur(oEvent);},
		editorFocus: function(oEvent) {return oCaller.editorFocus(oEvent);},
		startResize: function(oEvent) {return oCaller.startResize(oEvent);},
		resizeOverDocument: function(oEvent) {return oCaller.resizeOverDocument(oEvent);},
		endResize: function(oEvent) {return oCaller.endResize(oEvent);},
		resizeOverIframe: function(oEvent) {return oCaller.resizeOverIframe(oEvent);}
	};

	// Set the textHandle.
	this.oTextHandle = document.getElementById(this.opt.sUniqueId);

	// Ensure the currentText is set correctly depending on the mode.
	if (this.sCurrentText == '' && !this.bRichTextEnabled)
		this.sCurrentText = getInnerHTML(this.oTextHandle).php_unhtmlspecialchars();

	// Only try to do this if rich text is supported.
	if (this.bRichTextPossible)
	{
		// Make the iframe itself, stick it next to the current text area, and give it an ID.
		this.oFrameHandle = document.createElement('iframe');
		this.oFrameHandle.src = 'about:blank';
		this.oFrameHandle.id = 'html_' + this.opt.sUniqueId;
		this.oFrameHandle.className = 'rich_editor_frame';
		this.oFrameHandle.style.display = 'none';
		this.oFrameHandle.style.margin = '0px';
		this.oFrameHandle.tabIndex = this.oTextHandle.tabIndex;
		this.oTextHandle.parentNode.appendChild(this.oFrameHandle);

		// Create some handy shortcuts.
		this.oFrameDocument = this.oFrameHandle.contentDocument ? this.oFrameHandle.contentDocument : ('contentWindow' in this.oFrameHandle ? this.oFrameHandle.contentWindow.document : this.oFrameHandle.document);
		this.oFrameWindow = 'contentWindow' in this.oFrameHandle ? this.oFrameHandle.contentWindow : this.oFrameHandle.document.parentWindow;

		// Create the debug window... and stick this under the main frame - make it invisible by default.
		this.oBreadHandle = document.createElement('div');
		this.oBreadHandle.id = 'bread_' . uid;
		this.oBreadHandle.style.visibility = 'visible';
		this.oBreadHandle.style.display = 'none';
		this.oFrameHandle.parentNode.appendChild(this.oBreadHandle);

		// Size the iframe dimensions to something sensible.
		this.oFrameHandle.style.width = this.sEditWidth;
		this.oFrameHandle.style.height = this.sEditHeight;
		this.oFrameHandle.style.visibility = 'visible';

		// Only bother formatting the debug window if debug is enabled.
		if (this.showDebug)
		{
			this.oBreadHandle.style.width = this.sEditWidth;
			this.oBreadHandle.style.height = '20px';
			this.oBreadHandle.className = 'windowbg2';
			this.oBreadHandle.style.border = '1px black solid';
			this.oBreadHandle.style.display = '';
		}

		// Populate the editor with nothing by default.
		if (!is_opera95up)
		{
			this.oFrameDocument.open();
			this.oFrameDocument.write('');
			this.oFrameDocument.close();
		}

		// Right to left mode?
		if (this.opt.bRTL)
		{
			this.oFrameDocument.dir = "rtl";
			this.oFrameDocument.body.dir = "rtl";
		}

		// Mark it as editable...
		if (this.oFrameDocument.body.contentEditable)
			this.oFrameDocument.body.contentEditable = true;
		else
		{
			this.oFrameHandle.style.display = '';
			this.oFrameDocument.designMode = 'on';
			this.oFrameHandle.style.display = 'none';
		}

		// Now we need to try and style the editor - internet explorer allows us to do the whole lot.
		if (document.styleSheets['editor_css'] || document.styleSheets['editor_ie_css'])
		{
			var oMyStyle = this.oFrameDocument.createElement('style');
			this.oFrameDocument.documentElement.firstChild.appendChild(oMyStyle);
			oMyStyle.styleSheet.cssText = document.styleSheets['editor_ie_css'] ? document.styleSheets['editor_ie_css'].cssText : document.styleSheets['editor_css'].cssText;
		}
		// Otherwise we seem to have to try to rip out each of the styles one by one!
		else if (document.styleSheets.length)
		{
			var bFoundSomething = false;
			// First we need to find the right style sheet.
			for (var i = 0, iNumStyleSheets = document.styleSheets.length; i < iNumStyleSheets; i++)
			{
				// Start off looking for the right style sheet.
				if (!document.styleSheets[i].href || document.styleSheets[i].href.indexOf('editor') < 1)
					continue;

				// Firefox won't allow us to get a CSS file which ain't in the right URL.
				try
				{
					if (document.styleSheets[i].cssRules.length < 1)
						continue;
				}
				catch (e)
				{
					continue;
				}

				// Manually try to find the rich_editor class.
				for (var r = 0, iNumRules = document.styleSheets[i].cssRules.length; r < iNumRules; r++)
				{
					// Got the main editor?
					if (document.styleSheets[i].cssRules[r].selectorText == '.rich_editor')
					{
						// Set some possible styles.
						if (document.styleSheets[i].cssRules[r].style.color)
							this.oFrameDocument.body.style.color = document.styleSheets[i].cssRules[r].style.color;
						if (document.styleSheets[i].cssRules[r].style.backgroundColor)
							this.oFrameDocument.body.style.backgroundColor = document.styleSheets[i].cssRules[r].style.backgroundColor;
						if (document.styleSheets[i].cssRules[r].style.fontSize)
							this.oFrameDocument.body.style.fontSize = document.styleSheets[i].cssRules[r].style.fontSize;
						if (document.styleSheets[i].cssRules[r].style.fontFamily)
							this.oFrameDocument.body.style.fontFamily = document.styleSheets[i].cssRules[r].style.fontFamily;
						if (document.styleSheets[i].cssRules[r].style.border)
							this.oFrameDocument.body.style.border = document.styleSheets[i].cssRules[r].style.border;
						bFoundSomething = true;
					}
					// The frame?
					else if (document.styleSheets[i].cssRules[r].selectorText == '.rich_editor_frame')
					{
						if (document.styleSheets[i].cssRules[r].style.border)
							this.oFrameHandle.style.border = document.styleSheets[i].cssRules[r].style.border;
					}
				}
			}

			// Didn't find it?
			if (!bFoundSomething)
			{
				// Do something that is better than nothing.
				this.oFrameDocument.body.style.color = 'black';
				this.oFrameDocument.body.style.backgroundColor = 'white';
				this.oFrameDocument.body.style.fontSize = '78%';
				this.oFrameDocument.body.style.fontFamily = '"Verdana", "Arial", "Helvetica", "sans-serif"';
				this.oFrameDocument.body.style.border = 'none';
				this.oFrameHandle.style.border = '1px solid #808080';
				if (is_opera)
					this.oFrameDocument.body.style.height = '99%';
			}
		}

		// Apply the class...
		this.oFrameDocument.body.className = 'rich_editor';

		// Set the frame padding/margin inside the editor.
		this.oFrameDocument.body.style.padding = '1px';
		this.oFrameDocument.body.style.margin = '0';

		// Listen for input.
		this.oFrameDocument.instanceRef = this;
		this.oFrameHandle.instanceRef = this;
		this.oTextHandle.instanceRef = this;

		// Attach addEventListener for those browsers that don't support it.
		createEventListener(this.oFrameHandle);
		createEventListener(this.oFrameDocument);
		createEventListener(this.oTextHandle);
		createEventListener(window);
		createEventListener(document);

		// Attach functions to the key and mouse events.
		this.oFrameDocument.addEventListener('keyup', this.aEventWrappers.editorKeyUp, true);
		this.oFrameDocument.addEventListener('mouseup', this.aEventWrappers.editorKeyUp, true);
		this.oFrameDocument.addEventListener('keydown', this.aEventWrappers.shortcutCheck, true);
		this.oTextHandle.addEventListener('keydown', this.aEventWrappers.shortcutCheck, true);

		if (is_ie)
		{
			this.oFrameDocument.addEventListener('blur', this.aEventWrappers.editorBlur, true);
			this.oFrameDocument.addEventListener('focus', this.aEventWrappers.editorFocus, true);
		}

		// Show the iframe only if wysiwyrg is on - and hide the text area.
		this.oTextHandle.style.display = this.bRichTextEnabled ? 'none' : '';
		this.oFrameHandle.style.display = this.bRichTextEnabled ? '' : 'none';
		this.oBreadHandle.style.display = this.bRichTextEnabled ? '' : 'none';
	}
	// If we can't do advanced stuff then just do the basics.
	else
	{
		// Cannot have WYSIWYG anyway!
		this.bRichTextEnabled = false;

		// We need some of the event handlers.
		createEventListener(this.oTextHandle);
		createEventListener(window);
		createEventListener(document);
	}

	// Make sure we set the message mode correctly.
	document.getElementById(this.opt.sUniqueId + '_mode').value = this.bRichTextEnabled ? 1 : 0;

	// Show the resizer.
	if (document.getElementById(this.opt.sUniqueId + '_resizer') && (!is_opera || is_opera95up) && !(is_chrome && !this.bRichTextEnabled))
	{
		// Currently nothing is being resized...I assume!
		window.smf_oCurrentResizeEditor = null;

		this.oResizerElement = document.getElementById(this.opt.sUniqueId + '_resizer');
		this.oResizerElement.style.display = '';

		createEventListener(this.oResizerElement);
		this.oResizerElement.addEventListener('mousedown', this.aEventWrappers.startResize, false);
	}

	// Set the text - if WYSIWYG is enabled that is.
	if (this.bRichTextEnabled)
	{
		this.insertText(this.sCurrentText, true);

		// Better make us the focus!
		this.setFocus();
	}

	// Finally, register shortcuts.
	this.registerDefaultShortcuts();
	this.updateEditorControls();
}

// Return the current text.
smc_Editor.prototype.getText = function(bPrepareEntities, bModeOverride)
{
	var bCurMode = typeof(bModeOverride) != 'undefined' ? bModeOverride : this.bRichTextEnabled;

	if (!bCurMode || this.oFrameDocument == null)
	{
		var sText = this.oTextHandle.value;
		if (bPrepareEntities)
			sText = sText.replace(/</g, '#smlt#').replace(/>/g, '#smgt#').replace(/&/g, '#smamp#');
	}
	else
	{
		var sText = this.oFrameDocument.body.innerHTML;
		if (bPrepareEntities)
			sText = sText.replace(/&lt;/g, '#smlt#').replace(/&gt;/g, '#smgt#').replace(/&amp;/g, '#smamp#');
	}

	// Clean it up - including removing semi-colons.
	if (bPrepareEntities)
		sText = sText.replace(/&nbsp;/g, ' ').replace(/;/g, '#smcol#');

	// Return it.
	return sText;
}

// Return the current text.
smc_Editor.prototype.unprotectText = function(sText)
{
	var bCurMode = typeof(bModeOverride) != 'undefined' ? bModeOverride : this.bRichTextEnabled;

	// This restores smlt, smgt and smamp into boring entities, to unprotect against XML'd information like quotes.
	sText = sText.replace(/#smlt#/g, '&lt;').replace(/#smgt#/g, '&gt;').replace(/#smamp#/g, '&amp;');

	// Return it.
	return sText;
}

smc_Editor.prototype.editorKeyUp = function()
{
	// Rebuild the breadcrumb.
	this.updateEditorControls();
}

smc_Editor.prototype.editorBlur = function()
{
	if (!is_ie)
		return;

	// Need to do something here.
}

smc_Editor.prototype.editorFocus = function()
{
	if (!is_ie)
		return;

	// Need to do something here.
}

// Rebuild the breadcrumb etc - and set things to the correct context.
smc_Editor.prototype.updateEditorControls = function()
{
	// Everything else is specific to HTML mode.
	if (!this.bRichTextEnabled)
	{
		// Set none of the buttons active.
		if (this.opt.oBBCBox)
			this.opt.oBBCBox.setActive([]);
		return;
	}

	var aCrumb = new Array();
	var aAllCrumbs = new Array();
	var iMaxLength = 6;

	// What is the current element?
	var oCurTag = this.getCurElement();

	var i = 0;
	while (typeof(oCurTag) == 'object' && oCurTag != null && oCurTag.nodeName.toLowerCase() != 'body' && i < iMaxLength)
	{
		aCrumb[i++] = oCurTag;
		oCurTag = oCurTag.parentNode;
	}

	// Now print out the tree.
	var sTree = '';
	var sCurFontName = '';
	var sCurFontSize = '';
	var sCurFontColor = '';
	for (var i = 0, iNumCrumbs = aCrumb.length; i < iNumCrumbs; i++)
	{
		var sCrumbName = aCrumb[i].nodeName.toLowerCase();

		// Does it have an alternative name?
		if (sCrumbName in this.breadCrumbNameTags)
			sCrumbName = this.breadCrumbNameTags[sCrumbName];
		// Don't bother with this...
		else if (sCrumbName == 'p')
			continue;
		// A link?
		else if (sCrumbName == 'a')
		{
			var sUrlInfo = aCrumb[i].getAttribute('href');
			sCrumbName = 'url';
			if (typeof(sUrlInfo) == 'string')
			{
				if (sUrlInfo.substr(0, 3) == 'ftp')
					sCrumbName = 'ftp';
				else if (sUrlInfo.substr(0, 6) == 'mailto')
					sCrumbName = 'email';
			}
		}
		else if (sCrumbName == 'span' || sCrumbName == 'div')
		{
			if (aCrumb[i].style)
			{
				for (var j = 0, iNumStyles = this.aBreadCrumbNameStyles.length; j < iNumStyles; j++)
				{
					// Do we have a font?
					if (aCrumb[i].style.fontFamily && aCrumb[i].style.fontFamily != '' && sCurFontName == '')
					{
						sCurFontName = aCrumb[i].style.fontFamily;
						sCrumbName = 'face';
					}
					// ... or a font size?
					if (aCrumb[i].style.fontSize && aCrumb[i].style.fontSize != '' && sCurFontSize == '')
					{
						sCurFontSize = aCrumb[i].style.fontSize;
						sCrumbName = 'size';
					}
					// ... even color?
					if (aCrumb[i].style.color && aCrumb[i].style.color != '' && sCurFontColor == '')
					{
						sCurFontColor = aCrumb[i].style.color;
						if (in_array(sCurFontColor, this.oFontColors))
							sCurFontColor = array_search(sCurFontColor, this.oFontColors);
						sCrumbName = 'color';
					}

					if (this.aBreadCrumbNameStyles[j].sStyleType == 'text-align' && aCrumb[i].style.textAlign && aCrumb[i].style.textAlign == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'text-decoration' && aCrumb[i].style.textDecoration && aCrumb[i].style.textDecoration == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'font-weight' && aCrumb[i].style.fontWeight && aCrumb[i].style.fontWeight == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'font-style' && aCrumb[i].style.fontStyle && aCrumb[i].style.fontStyle == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
				}
			}
		}
		// Do we have a font?
		else if (sCrumbName == 'font')
		{
			if (aCrumb[i].getAttribute('face') && sCurFontName == '')
			{
				sCurFontName = aCrumb[i].getAttribute('face').toLowerCase();
				sCrumbName = 'face';
			}
			if (aCrumb[i].getAttribute('size') && sCurFontSize == '')
			{
				sCurFontSize = aCrumb[i].getAttribute('size');
				sCrumbName = 'size';
			}
			if (aCrumb[i].getAttribute('color') && sCurFontColor == '')
			{
				sCurFontColor = aCrumb[i].getAttribute('color');
				if (in_array(sCurFontColor, this.oFontColors))
					sCurFontColor = array_search(sCurFontColor, this.oFontColors);
				sCrumbName = 'color';
			}
			// Something else - ignore.
			if (sCrumbName == 'font')
				continue;
		}

		sTree += (i != 0 ? '&nbsp;<strong>&gt;</strong>' : '') + '&nbsp;' + sCrumbName;
		aAllCrumbs[aAllCrumbs.length] = sCrumbName;
	}

	// Since we're in WYSIWYG state, show the toggle button as active.
	aAllCrumbs[aAllCrumbs.length] = 'toggle';

	this.opt.oBBCBox.setActive(aAllCrumbs);

	// Try set the font boxes correct.
	this.opt.oBBCBox.setSelect('sel_face', sCurFontName);
	this.opt.oBBCBox.setSelect('sel_size', sCurFontSize);
	this.opt.oBBCBox.setSelect('sel_color', sCurFontColor);

	if (this.showDebug)
		setInnerHTML(this.oBreadHandle, sTree);
}

// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
smc_Editor.prototype.doSubmit = function()
{
	if (this.bRichTextEnabled)
		this.oTextHandle.value = this.oFrameDocument.body.innerHTML;
}

// Populate the box with text.
smc_Editor.prototype.insertText = function(sText, bClear, bForceEntityReverse, iMoveCursorBack)
{
	if (bForceEntityReverse)
		sText = this.unprotectText(sText);

	// Erase it all?
	if (bClear)
	{
		if (this.bRichTextEnabled)
		{
			// This includes a work around for FF to get the cursor to show!
			this.oFrameDocument.body.innerHTML = sText;

			// If FF trick the cursor into coming back!
			if (is_ff || is_opera)
			{
				// For some entirely unknown reason FF3 Beta 2 and some Opera versions
				// require this.
				this.oFrameDocument.body.contentEditable = false;

				this.oFrameDocument.designMode = 'off';
				this.oFrameDocument.designMode = 'on';
			}
		}
		else
			this.oTextHandle.value = sText;
	}
	else
	{
		this.setFocus();
		if (this.bRichTextEnabled)
		{
			// IE croaks if you have an image selected and try to insert!
			if ('selection' in this.oFrameDocument && this.oFrameDocument.selection.type != 'Text' && this.oFrameDocument.selection.type != 'None' && this.oFrameDocument.selection.clear)
				this.oFrameDocument.selection.clear();

			var oRange = this.getRange();

			if (oRange.pasteHTML)
			{
				oRange.pasteHTML(sText);

				// Do we want to move the cursor back at all?
				if (iMoveCursorBack)
					oRange.moveEnd('character', -iMoveCursorBack);

				oRange.select();
			}
			else
			{
				// If the cursor needs to be positioned, insert the last fragment first.
				if (typeof(iMoveCursorBack) != 'undefined' && iMoveCursorBack > 0 && sText.length > iMoveCursorBack)
				{
					var oSelection = this.getSelect(false, false);
					var oRange = oSelection.getRangeAt(0);
					oRange.insertNode(this.oFrameDocument.createTextNode(sText.substr(sText.length - iMoveCursorBack)));
				}

				this.smf_execCommand('inserthtml', false, typeof(iMoveCursorBack) == 'undefined' ? sText : sText.substr(0, sText.length - iMoveCursorBack));
			}
		}
		else
		{
			replaceText(sText, this.oTextHandle);
		}
	}
}


// Special handler for WYSIWYG.
smc_Editor.prototype.smf_execCommand = function(sCommand, bUi, sValue)
{
	return this.oFrameDocument.execCommand(sCommand, bUi, sValue);
}

smc_Editor.prototype.insertSmiley = function(oSmileyProperties)
{
	// In text mode we just add it in as we always did.
	if (!this.bRichTextEnabled)
		this.insertText(' ' + oSmileyProperties.sCode);

	// Otherwise we need to do a whole image...
	else
	{
		var iUniqueSmileyId = 1000 + Math.floor(Math.random() * 100000);
		this.insertText('<img src="' + oSmileyProperties.sSrc + '" id="smiley_' + iUniqueSmileyId + '_' + oSmileyProperties.sSrc.replace(/^.*\//, '') + '" onresizestart="return false;" align="bottom" alt="" title="' + oSmileyProperties.sDescription.php_htmlspecialchars() + '" style="padding: 0 3px 0 3px;" />');
	}
}

smc_Editor.prototype.handleButtonClick = function (oButtonProperties)
{
	this.setFocus();

	// A special SMF function?
	if (oButtonProperties.sCode in this.oSmfExec)
		this[this.oSmfExec[oButtonProperties.sCode]]();

	else
	{
		// In text this is easy...
		if (!this.bRichTextEnabled)
		{
			// Replace?
			if (!('sAfter' in oButtonProperties) || oButtonProperties.sAfter == null)
				replaceText(oButtonProperties.sBefore.replace(/\\n/g, '\n'), this.oTextHandle)

			// Surround!
			else
				surroundText(oButtonProperties.sBefore.replace(/\\n/g, '\n'), oButtonProperties.sAfter.replace(/\\n/g, '\n'), this.oTextHandle)
		}
		else
		{
			// Is it easy?
			if (oButtonProperties.sCode in this.oSimpleExec)
				this.smf_execCommand(this.oSimpleExec[oButtonProperties.sCode], false, null);

			// A link?
			else if (oButtonProperties.sCode == 'url' || oButtonProperties.sCode == 'email' || oButtonProperties.sCode == 'ftp')
				this.insertLink(oButtonProperties.sCode);

			// Maybe an image?
			else if (oButtonProperties.sCode == 'img')
				this.insertImage();

			// Everything else means doing something ourselves.
			else if ('sBefore' in oButtonProperties)
				this.insertCustomHTML(oButtonProperties.sBefore.replace(/\\n/g, '\n'), oButtonProperties.sAfter.replace(/\\n/g, '\n'));

		}
	}

	this.updateEditorControls();

	// Finally set the focus.
	this.setFocus();
}

// Changing a select box?
smc_Editor.prototype.handleSelectChange = function (oSelectProperties)
{
	this.setFocus();

	var sValue = oSelectProperties.oSelect.value;
	if (sValue == '')
		return true;

	// Changing font face?
	if (oSelectProperties.sName == 'sel_face')
	{
		// Not in HTML mode?
		if (!this.bRichTextEnabled)
		{
			sValue = sValue.replace(/"/, '');
			surroundText('[font=' + sValue + ']', '[/font]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}
		else
		{
			if (is_webkit)
				this.smf_execCommand('styleWithCSS', false, true);
			this.smf_execCommand('fontname', false, sValue);
		}
	}

	// Font size?
	else if (oSelectProperties.sName == 'sel_size')
	{
		// Are we in boring mode?
		if (!this.bRichTextEnabled)
		{
			surroundText('[size=' + this.aFontSizes[sValue] + 'pt]', '[/size]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}

		else
			this.smf_execCommand('fontsize', false, sValue);
	}
	// Or color even?
	else if (oSelectProperties.sName == 'sel_color')
	{
		// Are we in boring mode?
		if (!this.bRichTextEnabled)
		{
			surroundText('[color=' + sValue + ']', '[/color]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}

		else
			this.smf_execCommand('forecolor', false, sValue);
	}

	this.updateEditorControls();

	return true;
}

// Put in some custom HTML.
smc_Editor.prototype.insertCustomHTML = function(sLeftTag, sRightTag)
{
	var sSelection = this.getSelect(true, true);
	if (sSelection.length == 0)
		sSelection = '';

	// Are we overwriting?
	if (sRightTag == '')
		this.insertText(sLeftTag);
	// If something was selected, replace and position cursor at the end of it.
	else if (sSelection.length > 0)
		this.insertText(sLeftTag + sSelection + sRightTag, false, false, 0);
	// Wrap the tags around the cursor position.
	else
		this.insertText(sLeftTag + sRightTag, false, false, sRightTag.length);

}

// Insert a URL link.
smc_Editor.prototype.insertLink = function(sType)
{
	if (sType == 'email')
		var sPromptText = oEditorStrings['prompt_text_email'];
	else if (sType == 'ftp')
		var sPromptText = oEditorStrings['prompt_text_ftp'];
	else
		var sPromptText = oEditorStrings['prompt_text_url'];

	// IE has a nice prompt for this - others don't.
	if (sType != 'email' && sType != 'ftp' && is_ie)
		this.smf_execCommand('createlink', true, 'http://');

	else
	{
		// Ask them where to link to.
		var sText = prompt(sPromptText, sType == 'email' ? '' : (sType == 'ftp' ? 'ftp://' : 'http://'));
		if (!sText)
			return;

		if (sType == 'email' && sText.indexOf('mailto:') != 0)
			sText = 'mailto:' + sText;

		// Check if we have text selected and if not force us to have some.
		var oCurText = this.getSelect(true, true);

		if (oCurText.toString().length != 0)
		{
			this.smf_execCommand('unlink');
			this.smf_execCommand('createlink', false, sText);
		}
		else
			this.insertText('<a href="' + sText + '">' + sText + '</a>');
	}
}

smc_Editor.prototype.insertImage = function(sSrc)
{
	if (!sSrc)
	{
		sSrc = prompt(oEditorStrings['prompt_text_img'], 'http://');
		if (!sSrc || sSrc.length < 10)
			return;
	}
	this.smf_execCommand('insertimage', false, sSrc);
}

smc_Editor.prototype.getSelect = function(bWantText, bWantHTMLText)
{
	if (is_ie && 'selection' in this.oFrameDocument)
	{
		// Just want plain text?
		if (bWantText && !bWantHTMLText)
			return this.oFrameDocument.selection.createRange().text;
		// We want the HTML flavoured variety?
		else if (bWantHTMLText)
			return this.oFrameDocument.selection.createRange().htmlText;

		return this.oFrameDocument.selection;
	}

	// This is mainly Firefox.
	if ('getSelection' in this.oFrameWindow)
	{
		// Plain text?
		if (bWantText && !bWantHTMLText)
			return this.oFrameWindow.getSelection().toString();

		// HTML is harder - currently using: http://www.faqts.com/knowledge_base/view.phtml/aid/32427
		else if (bWantHTMLText)
		{
			var oSelection = this.oFrameWindow.getSelection();
			if (oSelection.rangeCount > 0)
			{
				var oRange = oSelection.getRangeAt(0);
				var oClonedSelection = oRange.cloneContents();
				var oDiv = this.oFrameDocument.createElement('div');
				oDiv.appendChild(oClonedSelection);
				return oDiv.innerHTML;
			}
			else
				return '';
		}

		// Want the whole object then.
		return this.oFrameWindow.getSelection();
	}

	// If we're here it's not good.
	return this.oFrameDocument.getSelection();
}

smc_Editor.prototype.getRange = function()
{
	// Get the current selection.
	var oSelection = this.getSelect();

	if (!oSelection)
		return null;

	if (is_ie && oSelection.createRange)
		return oSelection.createRange();

	return oSelection.rangeCount == 0 ? null : oSelection.getRangeAt(0);
}

// Get the current element.
smc_Editor.prototype.getCurElement = function()
{
	var oRange = this.getRange();

	if (!oRange)
		return null;

	if (is_ie)
	{
		if (oRange.item)
			return oRange.item(0);
		else
			return oRange.parentElement();
	}
	else
	{
		var oElement = oRange.commonAncestorContainer;
		return this.getParentElement(oElement);
	}
}

smc_Editor.prototype.getParentElement = function(oNode)
{
	if (oNode.nodeType == 1)
		return oNode;

	for (var i = 0; i < 50; i++)
	{
		if (!oNode.parentNode)
			break;

		oNode = oNode.parentNode;
		if (oNode.nodeType == 1)
			return oNode;
	}
	return null;
}

// Remove formatting for the selected text.
smc_Editor.prototype.removeFormatting = function()
{
	// Do both at once.
	if (this.bRichTextEnabled)
	{
		this.smf_execCommand('removeformat');
		this.smf_execCommand('unlink');
	}
	// Otherwise do a crude move indeed.
	else
	{
		// Get the current selection first.
		if (this.oTextHandle.caretPos)
			var sCurrentText = this.oTextHandle.caretPos.text;

		else if ('selectionStart' in this.oTextHandle)
			var sCurrentText = this.oTextHandle.value.substr(this.oTextHandle.selectionStart, (this.oTextHandle.selectionEnd - this.oTextHandle.selectionStart));

		else
			return;

		// Do bits that are likely to have attributes.
		sCurrentText = sCurrentText.replace(RegExp("\\[/?(url|img|iurl|ftp|email|img|color|font|size|list|bdo).*?\\]", "g"), '');
		// Then just anything that looks like BBC.
		sCurrentText = sCurrentText.replace(RegExp("\\[/?[A-Za-z]+\\]", "g"), '');

		replaceText(sCurrentText, this.oTextHandle);
	}
}

// Toggle wysiwyg/normal mode.
smc_Editor.prototype.toggleView = function(bView)
{
	if (!this.bRichTextPossible)
	{
		alert(oEditorStrings['wont_work']);
		return false;
	}

	// Overriding or alternating?
	if (typeof(bView) == 'undefined')
		bView = !this.bRichTextEnabled;

	this.requestParsedMessage(bView);

	return true;
}

// Request the message in a different form.
smc_Editor.prototype.requestParsedMessage = function(bView)
{
	// Replace with a force reload.
	if (!window.XMLHttpRequest)
	{
		alert(oEditorStrings['func_disabled']);
		return;
	}

	// Get the text.
	var sText = this.getText(true, !bView).replace(/&#/g, "&#38;#").php_to8bit().php_urlencode();

	this.tmpMethod = sendXMLDocument;
	this.tmpMethod(smf_prepareScriptUrl(smf_scripturl) + 'action=jseditor;view=' + (bView ? 1 : 0) + ';' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + sText, this.onToggleDataReceived);
	delete tmpMethod;
}

smc_Editor.prototype.onToggleDataReceived = function(oXMLDoc)
{
	var sText = '';
	for (var i = 0; i < oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	// What is this new view we have?
	this.bRichTextEnabled = oXMLDoc.getElementsByTagName('message')[0].getAttribute('view') != '0';

	if (this.bRichTextEnabled)
	{
		this.oFrameHandle.style.display = '';
		if (this.showDebug)
			this.oBreadHandle.style.display = '';
		this.oTextHandle.style.display = 'none';
	}
	else
	{
		sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
		this.oFrameHandle.style.display = 'none';
		this.oBreadHandle.style.display = 'none';
		this.oTextHandle.style.display = '';
	}

	// First we focus.
	this.setFocus();

	this.insertText(sText, true);

	// Record the new status.
	document.getElementById(this.opt.sUniqueId + '_mode').value = this.bRichTextEnabled ? '1' : '0';

	// Rebuild the bread crumb!
	this.updateEditorControls();
}

// Set the focus for the editing window.
smc_Editor.prototype.setFocus = function(force_both)
{
	if (!this.bRichTextEnabled)
		this.oTextHandle.focus();
	else if (is_ff || is_opera)
		this.oFrameHandle.focus();
	else
		this.oFrameWindow.focus();
}

// Start up the spellchecker!
smc_Editor.prototype.spellCheckStart = function()
{
	if (!spellCheck)
		return false;

	// If we're in HTML mode we need to get the non-HTML text.
	if (this.bRichTextEnabled)
	{
		var sText = escape(this.getText(true, 1).php_to8bit());

		this.tmpMethod = sendXMLDocument;
		this.tmpMethod(smf_prepareScriptUrl(smf_scripturl) + 'action=jseditor;view=0;' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + sText, this.onSpellCheckDataReceived);
		delete tmpMethod;
	}
	// Otherwise start spellchecking right away.
	else
		spellCheck(this.sFormId, this.opt.sUniqueId);

	return true;
}

// This contains the spellcheckable text.
smc_Editor.prototype.onSpellCheckDataReceived = function(oXMLDoc)
{
	var sText = '';
	for (var i = 0; i < oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

	this.oTextHandle.value = sText;
	spellCheck(this.sFormId, this.opt.sUniqueId);
}

// Function called when the Spellchecker is finished and ready to pass back.
smc_Editor.prototype.spellCheckEnd = function()
{
	// If HTML edit put the text back!
	if (this.bRichTextEnabled)
	{
		var sText = escape(this.getText(true, 0).php_to8bit());

		this.tmpMethod = sendXMLDocument;
		this.tmpMethod(smf_prepareScriptUrl(smf_scripturl) + 'action=jseditor;view=1;' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + sText, smf_editorArray[this.iArrayPosition].onSpellCheckCompleteDataReceived);
		delete tmpMethod;
	}
	else
		this.setFocus();
}

// The corrected text.
smc_Editor.prototype.onSpellCheckCompleteDataReceived = function(oXMLDoc)
{
	var sText = '';
	for (var i = 0; i < oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	this.insertText(sText, true);
	this.setFocus();
}

smc_Editor.prototype.resizeTextArea = function(newHeight, newWidth, is_change)
{
	// Work out what the new height is.
	if (is_change)
	{
		// We'll assume pixels but may not be.
		newHeight = this._calculateNewDimension(this.oTextHandle.style.height, newHeight);
		if (newWidth)
			newWidth = this._calculateNewDimension(this.oTextHandle.style.width, newWidth);
	}

	// Do the HTML editor - but only if it's enabled!
	if (this.bRichTextPossible)
	{
		this.oFrameHandle.style.height = newHeight;
		if (newWidth)
			this.oFrameHandle.style.width = newWidth;
	}
	// Do the text box regardless!
	this.oTextHandle.style.height = newHeight;
	if (newWidth)
		this.oTextHandle.style.width = newWidth;
}

// A utility instruction to save repetition when trying to work out what to change on a height/width.
smc_Editor.prototype._calculateNewDimension = function(old_size, change_size)
{
	// We'll assume pixels but may not be.
	changeReg = change_size.toString().match(/(-)?(\d+)(\D*)/);
	curReg = old_size.toString().match(/(\d+)(\D*)/);

	if (!changeReg[3])
		changeReg[3] = 'px';

	if (changeReg[1] == '-')
		changeReg[2] = 0 - changeReg[2];

	// Both the same type?
	if (changeReg[3] == curReg[2])
	{
		new_size = parseInt(changeReg[2]) + parseInt(curReg[1]);
		if (new_size < 50)
			new_size = 50;
		new_size = new_size.toString() + changeReg[3];
	}
	// Is the change a percentage?
	else if (changeReg[3] == '%')
		new_size = (parseInt(curReg[1]) + parseInt((parseInt(changeReg[2]) * parseInt(curReg[1])) / 100)).toString() + 'px';
	// Otherwise just guess!
	else
		new_size = (parseInt(curReg[1]) + (parseInt(changeReg[2]) / 10)).toString() + '%';

	return new_size;
}

// Register default keyboard shortcuts.
smc_Editor.prototype.registerDefaultShortcuts = function()
{
	if (is_ff)
	{
		this.registerShortcut('b', 'ctrl', 'b');
		this.registerShortcut('u', 'ctrl', 'u');
		this.registerShortcut('i', 'ctrl', 'i');
		this.registerShortcut('p', 'alt', 'preview');
		this.registerShortcut('s', 'alt', 'submit');
	}
}

// Register a keyboard shortcut.
smc_Editor.prototype.registerShortcut = function(sLetter, sModifiers, sCodeName)
{
	if (!sCodeName)
		return;

	var oNewShortcut = {
		code : sCodeName,
		key: sLetter.toUpperCase().charCodeAt(0),
		alt : false,
		ctrl : false
	};

	var aSplitModifiers = sModifiers.split(',');
	for(var i = 0, n = aSplitModifiers.length; i < n; i++)
		if (aSplitModifiers[i] in oNewShortcut)
			oNewShortcut[aSplitModifiers[i]] = true;

	this.aKeyboardShortcuts[this.aKeyboardShortcuts.length] = oNewShortcut;
}

// Check whether the key has triggered a shortcut?
smc_Editor.prototype.checkShortcut = function(oEvent)
{
	// To be a shortcut it needs to be one of these, duh!
	if (!oEvent.altKey && !oEvent.ctrlKey)
		return false;

	var sReturnCode = false;

	// Let's take a look at each of our shortcuts shall we?
	for (var i = 0, n = this.aKeyboardShortcuts.length; i < n; i++)
	{
		// Found something?
		if (oEvent.altKey == this.aKeyboardShortcuts[i].alt && oEvent.ctrlKey == this.aKeyboardShortcuts[i].ctrl && oEvent.keyCode == this.aKeyboardShortcuts[i].key)
			sReturnCode = this.aKeyboardShortcuts[i].code;
	}

	return sReturnCode;
}

// The actual event check for the above!
smc_Editor.prototype.shortcutCheck = function(oEvent)
{
	var sFoundCode = this.checkShortcut(oEvent);

	// Run it and exit.
	if (typeof(sFoundCode) == 'string' && sFoundCode != '')
	{
		var bCancelEvent = false;
		if (sFoundCode == 'submit')
		{
			// So much to do!
			var oForm = document.getElementById(this.sFormId);
			submitThisOnce(oForm);
			submitonce(oForm);
			smc_saveEntities(oForm.name, ['subject', this.opt.sUniqueId, 'guestname', 'evtitle', 'question']);
			oForm.submit();

			bCancelEvent = true;
		}
		else if (sFoundCode == 'preview')
		{
			previewPost();
			bCancelEvent = true;
		}
		else
			bCancelEvent = this.opt.oBBCBox.emulateClick(sFoundCode);

		if (bCancelEvent)
		{
			if (is_ie && oEvent.cancelBubble)
				oEvent.cancelBubble = true;

			else if (oEvent.stopPropagation)
			{
				oEvent.stopPropagation();
				oEvent.preventDefault();
			}

			return false;
		}
	}

	return true;
}

// This is the method called after clicking the resize bar.
smc_Editor.prototype.startResize = function(oEvent)
{
	if ('event' in window)
		oEvent = window.event;

	if (!oEvent || window.smf_oCurrentResizeEditor != null)
		return true;

	window.smf_oCurrentResizeEditor = this.iArrayPosition;

	var aCurCoordinates = smf_mousePose(oEvent);
	this.osmc_EditorCurrentResize.old_y = aCurCoordinates[1];
	this.osmc_EditorCurrentResize.old_rel_y = null;
	this.osmc_EditorCurrentResize.cur_height = parseInt(this.oTextHandle.style.height);

	// Set the necessary events for resizing.
	var oResizeEntity = is_ie ? document : window;
	oResizeEntity.addEventListener('mousemove', this.aEventWrappers.resizeOverDocument, false);

	if (this.bRichTextPossible)
		this.oFrameDocument.addEventListener('mousemove', this.aEventWrappers.resizeOverIframe, false);

	document.addEventListener('mouseup', this.aEventWrappers.endResize, true);

	if (this.bRichTextPossible)
		this.oFrameDocument.addEventListener('mouseup', this.aEventWrappers.endResize, true);

	return false;
}

// This is kind of a cheat, as it only works over the IFRAME.
smc_Editor.prototype.resizeOverIframe = function(oEvent)
{
	if ('event' in window)
		oEvent = window.event;

	if (!oEvent || window.smf_oCurrentResizeEditor == null)
		return true;

	var newCords = smf_mousePose(oEvent);

	if (this.osmc_EditorCurrentResize.old_rel_y == null)
		this.osmc_EditorCurrentResize.old_rel_y = newCords[1];
	else
	{
		var iNewHeight = newCords[1] - this.osmc_EditorCurrentResize.old_rel_y + this.osmc_EditorCurrentResize.cur_height;
		if (iNewHeight < 0)
			this.endResize();
		else
			this.resizeTextArea(iNewHeight + 'px', 0, false);
	}

	return false;
}

// This resizes an editor.
smc_Editor.prototype.resizeOverDocument = function (oEvent)
{
	if ('event' in window)
		oEvent = window.event;

	if (!oEvent || window.smf_oCurrentResizeEditor == null)
		return true;

	var newCords = smf_mousePose(oEvent);

	var iNewHeight = newCords[1] - this.osmc_EditorCurrentResize.old_y + this.osmc_EditorCurrentResize.cur_height;
	if (iNewHeight < 0)
		this.endResize();
	else
		this.resizeTextArea(iNewHeight + 'px', 0, false);

	return false;
}

smc_Editor.prototype.endResize = function (oEvent)
{
	if ('event' in window)
		oEvent = window.event;

	if (window.smf_oCurrentResizeEditor == null)
		return true;

	window.smf_oCurrentResizeEditor = null;

	// Remove the event...
	var oResizeEntity = is_ie ? document : window;
	oResizeEntity.removeEventListener('mousemove', this.aEventWrappers.resizeOverDocument, false);

	if (this.bRichTextPossible)
		this.oFrameDocument.removeEventListener('mousemove', this.aEventWrappers.resizeOverIframe, false);

	document.removeEventListener('mouseup', this.aEventWrappers.endResize, true);

	if (this.bRichTextPossible)
		this.oFrameDocument.removeEventListener('mouseup', this.aEventWrappers.endResize, true);

	return false;
}

// *** smc_SmileyBox class.
function smc_SmileyBox(oOptions)
{
	this.opt = oOptions;
	this.oSmileyRowsContent = {};
	this.oSmileyPopupWindow = null;
	this.init();
}

smc_SmileyBox.prototype.init = function ()
{
	// Get the HTML content of the smileys visible on the post screen.
	this.getSmileyRowsContent('postform');

	// Inject the HTML.
	setInnerHTML(document.getElementById(this.opt.sContainerDiv), this.opt.sSmileyBoxTemplate.easyReplace({
		smileyRows: this.oSmileyRowsContent.postform,
		moreSmileys: this.opt.oSmileyLocations.popup.length == 0 ? '' : this.opt.sMoreSmileysTemplate.easyReplace({
			moreSmileysId: this.opt.sUniqueId + '_addMoreSmileys'
		})
	}));

	// Initialize the smileys.
	this.initSmileys('postform', document);

	// Initialize the [more] button.
	if (this.opt.oSmileyLocations.popup.length > 0)
	{
		var oMoreLink = document.getElementById(this.opt.sUniqueId + '_addMoreSmileys');
		oMoreLink.instanceRef = this;
		oMoreLink.onclick = function () {
			this.instanceRef.handleShowMoreSmileys();
			return false;
		}
	}
}

// Loop through the smileys to setup the HTML.
smc_SmileyBox.prototype.getSmileyRowsContent = function (sLocation)
{
	// If it's already defined, don't bother.
	if (sLocation in this.oSmileyRowsContent)
		return;

	this.oSmileyRowsContent[sLocation] = '';

	for (var iSmileyRowIndex = 0, iSmileyRowCount = this.opt.oSmileyLocations[sLocation].length; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
	{
		var sSmileyRowContent = '';
		for (var iSmileyIndex = 0, iSmileyCount = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex].length; iSmileyIndex < iSmileyCount; iSmileyIndex++)
			sSmileyRowContent += this.opt.sSmileyTemplate.easyReplace({
				smileySource: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sSrc.php_htmlspecialchars(),
				smileyDescription: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sDescription.php_htmlspecialchars(),
				smileyCode: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sCode.php_htmlspecialchars(),
				smileyId: this.opt.sUniqueId + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString()
			});

		this.oSmileyRowsContent[sLocation] += this.opt.sSmileyRowTemplate.easyReplace({
			smileyRow: sSmileyRowContent
		});
	}
}

smc_SmileyBox.prototype.initSmileys = function (sLocation, oDocument)
{
	for (var iSmileyRowIndex = 0, iSmileyRowCount = this.opt.oSmileyLocations[sLocation].length; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
	{
		for (var iSmileyIndex = 0, iSmileyCount = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex].length; iSmileyIndex < iSmileyCount; iSmileyIndex++)
		{
			var oSmiley = oDocument.getElementById(this.opt.sUniqueId + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString());
			oSmiley.instanceRef = this;
			oSmiley.style.cursor = 'pointer';
			oSmiley.onclick = function () {
				this.instanceRef.clickHandler(this);
				return false;
			}
		}
	}
}

smc_SmileyBox.prototype.clickHandler = function (oSmileyImg)
{
	// Dissect the id...
	var aMatches = oSmileyImg.id.match(/([^_]+)_(\d+)_(\d+)$/);
	if (aMatches.length != 4)
		return false;

	// ...to determine its exact smiley properties.
	var sLocation = aMatches[1];
	var iSmileyRowIndex = aMatches[2];
	var iSmileyIndex = aMatches[3];
	var oProperties = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex];

	if ('sClickHandler' in this.opt)
		eval(this.opt.sClickHandler + '(oProperties)');

	return false;
}

smc_SmileyBox.prototype.handleShowMoreSmileys = function ()
{
	// Focus the window if it's already opened.
	if (this.oSmileyPopupWindow != null && 'closed' in this.oSmileyPopupWindow && !this.oSmileyPopupWindow.closed)
	{
		this.oSmileyPopupWindow.focus();
		return;
	}

	// Get the smiley HTML.
	this.getSmileyRowsContent('popup');

	// Open the popup.
	this.oSmileyPopupWindow = window.open('about:blank', this.opt.sUniqueId + '_addMoreSmileysPopup', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,width=480,height=220,resizable=yes');

	// Paste the template in the popup.
	this.oSmileyPopupWindow.document.open('text/html', 'replace');
	this.oSmileyPopupWindow.document.write(this.opt.sMoreSmileysPopupTemplate.easyReplace({
		smileyRows: this.oSmileyRowsContent.popup,
		moreSmileysCloseLinkId: this.opt.sUniqueId + '_closeMoreSmileys'
	}));
	this.oSmileyPopupWindow.document.close();

	// Initialize the smileys that are in the popup window.
	this.initSmileys('popup', this.oSmileyPopupWindow.document);

	// Add a function to the close window button.
	var aCloseLink = this.oSmileyPopupWindow.document.getElementById(this.opt.sUniqueId + '_closeMoreSmileys');
	aCloseLink.instanceRef = this;
	aCloseLink.onclick = function () {
		this.instanceRef.oSmileyPopupWindow.close();
		return false;
	}
}


// *** smc_BBCButtonBox class.
function smc_BBCButtonBox(oOptions)
{
	this.opt = oOptions;
	this.init();
}

smc_BBCButtonBox.prototype.init = function ()
{
	var sBbcContent = '';
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		var sRowContent = '';
		var bPreviousWasDivider = false;
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			switch (oCurButton.sType)
			{
				case 'button':
					if (oCurButton.bEnabled)
					{
						sRowContent += this.opt.sButtonTemplate.easyReplace({
							buttonId: this.opt.sUniqueId.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
							buttonSrc: oCurButton.sImage.php_htmlspecialchars(),
							buttonDescription: oCurButton.sDescription.php_htmlspecialchars()
						});

						bPreviousWasDivider = false;
					}
				break;

				case 'divider':
					if (!bPreviousWasDivider)
						sRowContent += this.opt.sDividerTemplate;

					bPreviousWasDivider = true;
				break;

				case 'select':
					var sOptions = '';

					// Fighting javascript's idea of order in a for loop... :P
					if ('' in oCurButton.oOptions)
						sOptions = '<option value="">' + oCurButton.oOptions[''].php_htmlspecialchars() + '</option>';
					for (var sSelectValue in oCurButton.oOptions)
						// we've been through this before
						if (sSelectValue != '')
							sOptions += '<option value="' + sSelectValue.php_htmlspecialchars() + '">' + oCurButton.oOptions[sSelectValue].php_htmlspecialchars() + '</option>';

					sRowContent += this.opt.sSelectTemplate.easyReplace({
						selectName: oCurButton.sName,
						selectId: this.opt.sUniqueId.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
						selectOptions: sOptions
					});

					bPreviousWasDivider = false;
				break;
			}
		}
		sBbcContent += this.opt.sButtonRowTemplate.easyReplace({
			buttonRow: sRowContent
		});
	}

	var oBbcContainer = document.getElementById(this.opt.sContainerDiv);
	setInnerHTML(oBbcContainer, sBbcContent);

	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			switch (oCurControl.sType)
			{
				case 'button':
					if (!oCurControl.bEnabled)
						break;

					oCurControl.oImg = document.getElementById(this.opt.sUniqueId.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());
					oCurControl.oImg.style.cursor = 'pointer';
					if ('sButtonBackgroundImage' in this.opt)
						oCurControl.oImg.style.backgroundImage = 'url(' + this.opt.sButtonBackgroundImage + ')';

					oCurControl.oImg.instanceRef = this;
					oCurControl.oImg.onmouseover = function () {
						this.instanceRef.handleButtonMouseOver(this);
					};
					oCurControl.oImg.onmouseout = function () {
						this.instanceRef.handleButtonMouseOut(this);
					};
					oCurControl.oImg.onclick = function () {
						this.instanceRef.handleButtonClick(this);
					};

					oCurControl.oImg.bIsActive = false;
					oCurControl.oImg.bHover = false;
				break;

				case 'select':
					oCurControl.oSelect = document.getElementById(this.opt.sUniqueId.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());

					oCurControl.oSelect.instanceRef = this;
					oCurControl.oSelect.onchange = oCurControl.onchange = function () {
						this.instanceRef.handleSelectChange(this);
					}
				break;
			}
		}
	}
}

smc_BBCButtonBox.prototype.handleButtonMouseOver = function (oButtonImg)
{
	oButtonImg.bHover = true;
	this.updateButtonStatus(oButtonImg);
}

smc_BBCButtonBox.prototype.handleButtonMouseOut = function (oButtonImg)
{
	oButtonImg.bHover = false;
	this.updateButtonStatus(oButtonImg);
}

smc_BBCButtonBox.prototype.updateButtonStatus = function (oButtonImg)
{
	var sNewURL = '';
	if (oButtonImg.bHover && oButtonImg.bIsActive && 'sActiveButtonBackgroundImageHover' in this.opt)
		sNewURL = 'url(' + this.opt.sActiveButtonBackgroundImageHover + ')';
	else if (!oButtonImg.bHover && oButtonImg.bIsActive && 'sActiveButtonBackgroundImage' in this.opt)
		sNewURL = 'url(' + this.opt.sActiveButtonBackgroundImage + ')';
	else if (oButtonImg.bHover && 'sButtonBackgroundImageHover' in this.opt)
		sNewURL = 'url(' + this.opt.sButtonBackgroundImageHover + ')';
	else if ('sButtonBackgroundImage' in this.opt)
		sNewURL = 'url(' + this.opt.sButtonBackgroundImage + ')';

	if (oButtonImg.style.backgroundImage != sNewURL)
		oButtonImg.style.backgroundImage = sNewURL;
}

smc_BBCButtonBox.prototype.handleButtonClick = function (oButtonImg)
{
	// Dissect the id attribute...
	var aMatches = oButtonImg.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var iButtonRowIndex = aMatches[1];
	var iButtonIndex = aMatches[2];
	var oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
	oProperties.bIsActive = oButtonImg.bIsActive;

	if ('sButtonClickHandler' in this.opt)
		eval(this.opt.sButtonClickHandler + '(oProperties)');

	return false;
}

smc_BBCButtonBox.prototype.handleSelectChange = function (oSelectControl)
{
	// Dissect the id attribute...
	var aMatches = oSelectControl.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var iButtonRowIndex = aMatches[1];
	var iButtonIndex = aMatches[2];
	var oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];

	if ('sSelectChangeHandler' in this.opt)
		eval(this.opt.sSelectChangeHandler + '(oProperties)');

	return true;
}

smc_BBCButtonBox.prototype.setActive = function (aButtons)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'button' && oCurControl.bEnabled)
			{
				oCurControl.oImg.bIsActive = in_array(oCurControl.sCode, aButtons);
				this.updateButtonStatus(oCurControl.oImg);
			}
		}
	}
}

smc_BBCButtonBox.prototype.emulateClick = function (sCode)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'button' && oCurControl.sCode == sCode)
			{
				eval(this.opt.sButtonClickHandler + '(oCurControl)');
				return true;
			}
		}
	}
	return false;
}

smc_BBCButtonBox.prototype.setSelect = function (sSelectName, sValue)
{
	if (!('sButtonClickHandler' in this.opt))
		return;

	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'select' && oCurControl.sName == sSelectName)
				oCurControl.oSelect.value = sValue;
		}
	}
}
