function smf_fileUpload(oOptions)
{
	var previewNode = document.querySelector('#au-template');
	previewNode.id = '';
	var previewTemplate = previewNode.parentNode.innerHTML;
	previewNode.parentNode.removeChild(previewNode);

	// Default values in case oOptions isn't defined.
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id,
		parallelUploads : 1,
		filesizeBase:1000,
		paramName: 'attachment',
		uploadMultiple:true,
		previewsContainer: '#au-previews',
		previewTemplate: previewTemplate,
		acceptedFiles: '.doc,.gif,.jpg,.pdf,.png,.txt,.zip',
		thumbnailWidth: 100,
		thumbnailHeight: null,
		autoQueue: false,
		clickable: '.fileinput-button',
	};

	$.extend(true, dOptions, oOptions);

	var myDropzone = new Dropzone('div#attachUpload', dOptions);

	myDropzone.on('addedfile', function(file) {

		_thisElement = $(file.previewElement);

		_thisElement.find('.attach-ui').fadeIn();

		// Hookup the start button.
		_thisElement.find('.start').on( 'click', function() {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.addClass('descbox');

		// Show the upload and cancel all buttons only if there is something to cancel/upload.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length > 0){
			$('div#attachUpload').find('#attach-cancelAll, #attach-uploadAll').fadeIn();
		}
	});

	// Stuff to do when a file gets cancel.
	myDropzone.on('removedfile', function(progress) {

		// Hide the cancel and upload all buttons if there is nothing to cancel/upload anymore.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 0){
			$('div#attachUpload').find('#attach-cancelAll, #attach-uploadAll').fadeOut();
		}
	});

	// Update the total progress bar.
	myDropzone.on('totaluploadprogress', function(progress) {
		$('#total-progress span').width(progress + '%');
	});

	myDropzone.on('error', function(file, errorMessage, xhr) {

		_thisElement = $(file.previewElement);

		// Remove the 'start' button.
		_thisElement.find('.start').fadeOutAndRemove('slow');

		// Set a nice css class to make it more obvious theres an error.
		_thisElement.addClass('errorbox').removeClass('descbox');
	});

	myDropzone.on('success', function(file, responseText, e) {

		_thisElement = $(file.previewElement);

		// Remove the 'start' button.
		_thisElement.find('.start').fadeOutAndRemove('slow');

		// There is a general error.
		if (responseText.generalErrors){
			_thisElement.find('p.error').append(responseText.generalErrors.join('<br>'));
			return;
		}

		// Server returns an array.
		$.each(responseText.files, function( key, response ) {

			// The request was complete but the server returned an error.
			if (typeof response.errors !== 'undefined' && response.errors.length > 0){

				_thisElement.addClass('errorbox').removeClass('descbox');

				// Show the server error.
				_thisElement.find('p.error').append(response.errors.join('<br>'));
				return;
			}

			// If there wasn't any error, change the current cover.
			_thisElement.addClass('infobox').removeClass('descbox');

			bbcTag = '[attach]' + response.attachID + '[/attach]',
			inputField = $('<input type="text" name="attachBBC" value="'+ bbcTag +'" readonly>'),
			insertBBC = $('<a />')
			.addClass('button_submit insertBBC')
			.prop('disabled', false)
			.text(myDropzone.options.text_insertBBC)
			.on('click', function (e) {
				e.preventDefault();
				$('#' + oEditorID).data('sceditor').sourceEditorInsertText(_thisElement.find('input[name=attachBBC]').val());
			}),
			fieldTag = $('<p class="attached_BBC" />').append(insertBBC)
			.append(inputField);

			_thisElement.find('div.attach-info').append(fieldTag);

			// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
			_thisElement.find('a.delete').fadeOutAndRemove('slow');

			// Create a delete button.
			deleteButton = $('<a />')
			.addClass('button_submit')
			.prop('disabled', false)
			.text(myDropzone.options.text_deleteAttach)
			.one('click', function (e) {

				$this = $(this);

				// Perform the action only after receiving the confirmation.
				if (!confirm(smf_you_sure)){
					return;
				}

				// Let the server know you want to delete the file you just recently uploaded...
				$.ajax({
					url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach='+ response.attachID +';' + smf_session_var + '=' + smf_session_id,
					type: 'GET',
					dataType: 'json',
					beforeSend: function(){
						ajax_indicator(true);
					},
					complete: function(jqXHR, textStatus){
						ajax_indicator(false);
					},
					success: function (data, textStatus, xhr) {

						// Remove the text field and show a nice confirmation message.
						_thisElement.find('.attached_BBC').fadeOutAndRemove('slow');
						_thisElement.find('p.message').text(myDropzone.options.text_attachDeleted);

						// Remove this button and enable the cancel one.
						$this.fadeOutAndRemove('slow');
						_thisElement.find('.delete').prop('disabled', false);
					},
					error: function (xhr, textStatus, errorThrown) {

						// Tell the user something horrible happen!
						// @todo, catch the error and append it to our p.error tag.

						// For dramatic purposes only!
						node.removeClass('infobox').addClass('errorbox');
					}
				});
			})
			.appendTo(_thisElement.find('.attach-ui'));
		});
	});

	myDropzone.on('uploadprogress', function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Get the current file box progress bar, set its inner span's width accordingly.
		_thisElement.find('p.progressBar span').width(progress + '%');
	});

	myDropzone.on('complete', function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Hide the progress bar.
		_thisElement.find('p.progressBar').fadeOut();
	});

	// Show each individual's progress bar.
	myDropzone.on('sending', function(file) {

		_thisElement = $(file.previewElement);

		// Show the progress bar when upload starts.
		_thisElement.find('p.progressBar').fadeIn();

		// Show the total progress bar when upload starts.
		$("#total-progress").fadeIn();
	});

	// Update the total progress bar.
	myDropzone.on("totaluploadprogress", function(progress) {
		$("#total-progress span").width(progress + '%');
	});

	// Hide the total progress bar when nothing's uploading anymore.
	myDropzone.on("queuecomplete", function(progress) {
		$("#total-progress").fadeOut();
	});

	// Add an event for uploading and cancelling all files.
	$('a#attach-cancelAll' ).on('click', function() {
		myDropzone.removeAllFiles(true);
	});
	$('a#attach-uploadAll' ).on('click', function() {
		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
	});
}
