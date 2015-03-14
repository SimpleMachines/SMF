function getSelectedText(divID)
{
	if (typeof divID == 'undefined' || divID == false)
		return false;

	var text = '',
	selection,
	found = 0;

	if (window.getSelection)
	{
		selection = window.getSelection();
		text = selection.toString();
	}
	else if (document.selection && document.selection.type != 'Control')
	{
		selection = document.selection.createRange();
		text = selection.text;
	}

	// Need to be sure the selected text does belong to the right div.
	for (var i = 0; i < selection.rangeCount; i++) {
			s = selection.getRangeAt(i).startContainer.parentNode.id;
			e = selection.getRangeAt(i).endContainer.parentNode.id;

			if (s == divID || (s != divID && e == 'child'))
			{
				found = 1;
				break;
			}
		}

	return found === 1 ? text : false;
}

function quotedTextClick(oOptions)
{
		text = '';

		// The process has been started, hide the button.
		$('#quoteSelected_' + oOptions.msgID).hide();

		// Do a call to make sure this is a valid message.
		$.ajax({
			url: smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + oOptions.msgID + ';xml;pb='+ oEditorID + ';mode=' + (oEditorObject.bRichTextEnabled ? 1 : 0),
			type: 'GET',
			dataType: 'xml',
			beforeSend: function () {
				ajax_indicator(true);
			},
			success: function (data, textStatus, xhr) {
				// Search the xml data to get the quote tag.
				text = $(data).find('quote').text();

				// Insert the selected text between the quotes BBC tags.
				text = text.match(/^\[quote(.*)]/ig) + oOptions.text + '[/quote]' + '\n\n';

				// Add the whole text to the editor's instance.
				$('#' + oEditorID).data('sceditor').InsertText(text);

				// Move the view to the quick reply box. If available.
				if (typeof oJumpAnchor != 'undefined'){
					if (navigator.appName == 'Microsoft Internet Explorer')
						window.location.hash = oJumpAnchor;
					else
						window.location.hash = '#' + oJumpAnchor;
				}

				ajax_indicator(false);
			},
			error: function (xhr, textStatus, errorThrown) {
				ajax_indicator(false);
			}
		});
}

$(function() {

	// Event for handling selected quotes.
	$(document).on('mouseup', '.inner, .list_posts', function() {

		// Get everything we need.
		var oSelected = {
			divID : $(this).attr('id'),
			msgID : $(this).data('msgid'),
		};

		// If the button is already visible, hide it!
		$('#quoteSelected_' + oSelected.msgID).hide();

		// Get any selected text.
		oSelected.text = getSelectedText(oSelected.divID);

		// Do we have some selected text?
		if (typeof oSelected.text == 'undefined' || oSelected.text == false)
			return false;

		// Show the "quote this" button.
		$('#quoteSelected_' + oSelected.msgID).show();

			$(document).one('click', '#quoteSelected_' + oSelected.msgID + ' a', function(e){
				e.preventDefault();
				quotedTextClick(oSelected);
			});

		return false;
	});
});