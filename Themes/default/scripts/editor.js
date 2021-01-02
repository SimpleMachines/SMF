// *** smc_Editor class.
/*
 Kept for compatibility with SMF 2.0 editor
 */
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
	var e = $('#' + this.sUniqueId).get(0);
	return sceditor.instance(e).getText();
}

// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
smc_Editor.prototype.doSubmit = function()
{}

// Populate the box with text.
smc_Editor.prototype.insertText = function(sText, bClear, bForceEntityReverse, iMoveCursorBack)
{
	var e = $('#' + this.sUniqueId).get(0);
	sceditor.instance(e).InsertText(sText.replace(/<br \/>/gi, ''), bClear);
}
