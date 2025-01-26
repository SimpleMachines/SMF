(function (sceditor) {
	'use strict';

	sceditor.plugins.xmlPreview = function ()
	{
		var editor, form, previewButton, opts;

		var preview = function (event)
		{
			event.preventDefault();

			sendXMLDocument(opts.sUrl, opts.oSendData, response);

			document.getElementById(opts.sPreviewSectionContainerID).style.display = '';
			setInnerHTML(document.getElementById(opts.sPreviewSubjectContainerID), opts.sTxtPreviewTitle);
			setInnerHTML(document.getElementById(opts.sPreviewBodyContainerID), opts.sTxtPreviewFetch);
		};

		var response = function (XMLDoc)
		{
			if (!XMLDoc) {
				previewButton.onclick = () => true;
				previewButton.click();
			}

			// Show the preview section.
			var smfNode = XMLDoc.getElementsByTagName('smf')[0];
			var preview = smfNode.getElementsByTagName('preview')[0];
			var bodyNode = preview.getElementsByTagName('body')[0];

			// Set the preview subject
			var previewSubjectEl = document.getElementById(opts.sPreviewSubjectContainerID);
			if (previewSubjectEl) {
				previewSubjectEl.innerHTML = preview.getElementsByTagName('subject')[0].textContent || '';
			}

			// Extract and set the preview body text
			var previewBodyEl = document.getElementById(opts.sPreviewBodyContainerID);
			if (previewBodyEl) {
				previewBodyEl.innerHTML = bodyNode.textContent || '';
				attachBbCodeEvents(previewBodyEl);
				previewBodyEl.className = 'windowbg';
			}

			// Show a list of errors (if any).
			var errors = smfNode.getElementsByTagName('errors')[0];
			var errorElements = errors.getElementsByTagName('error');
			var errorList = [];

			for (let error of errorElements) {
				errorList.push(error.textContent);
			}

			var errorsContainer = document.getElementById(opts.sErrorsContainerID);
			var errorsSeriousContainer = document.getElementById(opts.sErrorsSeriousContainerID);
			var errorsListContainer = document.getElementById(opts.sErrorsListContainerID);

			if (errorsContainer) {
				errorsContainer.style.display = errorElements.length === 0 ? 'none' : '';
				errorsContainer.className = errors.getAttribute('serious') == 1 ? 'errorbox' : 'noticebox';
			}

			if (errorsSeriousContainer) {
				errorsSeriousContainer.style.display = errorElements.length === 0 ? 'none' : '';
			}

			if (errorsListContainer) {
				errorsListContainer.innerHTML = errorElements.length === 0 ? '' : errorList.join('<br>');
			}

			// Adjust the color of captions if the given data is erroneous.
			var captions = errors.getElementsByTagName('caption');
			for (let caption of captions) {
				let captionEl = document.getElementById(opts.sCaptionContainerID.replace('%ID%', caption.getAttribute('name')));
				if (captionEl) {
					captionEl.className = caption.getAttribute('class');
				}
			}

			// Highlight post box if there's a post error
			if (errors.getElementsByTagName('post_error').length === 1) {
				// iframe
				editor.getContentAreaContainer().style.border = '1px solid red';
				// textarea
				editor.getContentAreaContainer().nextSibling.style.border = '1px solid red';
			}

			if (opts.funcOnPreviewReceived) {
				opts.funcOnPreviewReceived.call(editor, XMLDoc);
			}

			location.hash = '#' + opts.sPreviewSectionContainerID;
		};

		this.signalReady = function ()
		{
			editor = this;
			opts = editor.opts.previewOptions;
			opts.oSendData ??= new FormData(editor.opts.original.form);
			form = editor.opts.original.form;
			previewButton = form[opts.sPreviewButtonName || 'preview'];

			previewButton.onclick = preview;
		};
	};

	sceditor.plugins.messagePreview = function ()
	{
		this.init = function ()
		{
			const editor = this;
			const opts = oPreviewPost.opts;
			opts.sUrl = [
				smf_prepareScriptUrl(smf_scripturl) + 'action=post2',
				opts.iCurrentBoard ? ';board=' + opts.iCurrentBoard : '',
				opts.bMakePoll ? ';poll' : '',
				';preview;xml'
			].join('');
			opts.funcOnPreviewReceived = oPreviewPost.onDocSent;
			opts.oSendData = new FormData(editor.opts.original.form);
			editor.opts.previewOptions = opts;
		};
	};
})(sceditor);
