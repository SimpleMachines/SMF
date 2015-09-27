// Traverse the DOM tree in our spinoff of jQuery's closest()
function getClosest(el, divID)
{
	if (typeof divID == 'undefined' || divID == false)
		return null;

	do
	{
		// End the loop if quick edit is detected.
		if (el.nodeName === 'TEXTAREA' || el.nodeName === 'INPUT' || el.id === 'error_box')
			break;

		if (el.id === divID)
		{
			return el;
		}
	}
	while (el = el.parentNode);

	// not found :(
	return null;
}

function getSelectedText(divID)
{
	if (typeof divID == 'undefined' || divID == false)
		return false;

	var text = '',
	selection,
	found = 0,
	container = document.createElement("div");

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
			s = getClosest(selection.getRangeAt(i).startContainer, divID);
			e = getClosest(selection.getRangeAt(i).endContainer, divID);

			if (s !== null && e !== null)
			{
				found = 1;
				container.appendChild(selection.getRangeAt(i).cloneContents());
				text = container.innerHTML;
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
			complete: function(jqXHR, textStatus){
				ajax_indicator(false);
			},
			success: function (data, textStatus, xhr) {
				// Search the xml data to get the quote tag.
				text = $(data).find('quote').text();

				// Insert the selected text between the quotes BBC tags.
				text = text.match(/^\[quote(.*)]/ig) + oOptions.text + '[/quote]' + '\n\n';

				// Get the editor stuff.
				var oEditor = $('#' + oEditorID).data('sceditor');

				// Convert any HTML into BBC tags.
				text = oEditor.toBBCode(text);

				// Push the text to the editor.
				oEditor.insert(text);

				// Move the view to the quick reply box. If available.
				if (typeof oJumpAnchor != 'undefined'){
					if (navigator.appName == 'Microsoft Internet Explorer')
						window.location.hash = oJumpAnchor;
					else
						window.location.hash = '#' + oJumpAnchor;
				}
			},
			error: function (xhr, textStatus, errorThrown) {
				// @todo Show some error.
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