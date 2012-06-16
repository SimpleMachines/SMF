// *** smc_Editor class.
function smc_Editor(oOptions)
{
	this.opt = oOptions;

	var editor = $('#' + oOptions.sUniqueId);
	this.sUniqueId = this.opt.sUniqueId;
	this.bRichTextEnabled = true;
}

// Return the current text.
smc_Editor.prototype.getText = function(bPrepareEntities, bModeOverride)
{
	return $('#' + this.sUniqueId).data("sceditor").getText();
}

// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
smc_Editor.prototype.doSubmit = function()
{}

// Populate the box with text.
smc_Editor.prototype.insertText = function(sText, bClear, bForceEntityReverse, iMoveCursorBack)
{
	$('#' + this.sUniqueId).data("sceditor").InsertText(sText.replace(/<br \/>/gi, ''), bClear);
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
