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
		accept: function(file, done) {

			// Need to check if the added file doesn't surpass the total max size setting.
			myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize + file.size;

			if (myDropzone.options.totalMaxSize > myDropzone.options.limitMultiFileUploadSize){
				done(myDropzone.options.text_totalMaxSize.replace('{currentTotal}', myDropzone.options.limitMultiFileUploadSize * 0.001).replace('{currentRemain}', myDropzone.options.totalMaxSize * 0.001));
			}
			else{
				done();
			}
		},
		totalMaxSize: 0
	};

	$.extend(true, dOptions, oOptions);

	var myDropzone = new Dropzone('div#attachUpload', dOptions);

	myDropzone.on('addedfile', function(file) {

		_thisElement = $(file.previewElement);

		// Create a generic thumb for non image files.
		if (!file.type.match(/image.*/)) {
			myDropzone.emit('thumbnail', file, smf_images_url +'/generic_attach.png');
		}

		// Show the file info.
		_thisElement.find('.attach-ui').fadeIn();

		// Create a function to insert the BBC attach tag.
		file.insertAttachment = function (_innerElement, attachmentId){

			_innerElement.find('.insertBBC').on('click', function (e) {
				e.preventDefault();
				$('#' + oEditorID).data('sceditor').sourceEditorInsertText('[attach]' + attachmentId + '[/attach]');
			});
		};

		// Replace the field with a message when the attachment is deleted.
		file.deleteAttachment = function (_innerElement){

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

						// Delete the button.
						$this.fadeOutAndRemove('slow');
					},
					success: function (data, textStatus, xhr) {

						// Remove the text field and show a nice confirmation message.
						_innerElement.find('.attached_BBC').text(myDropzone.options.text_attachDeleted);
					},
					error: function (xhr, textStatus, errorThrown) {

						// Tell the user something horrible happen!
						_thisElement.find('p.error').append(textStatus.error.join('<br>'));

						// For dramatic purposes only!
						_thisElement.removeClass('infobox').addClass('errorbox');
					}
				});
			})
			.appendTo(_innerElement.find('.attach-ui'));
		};

		// Hookup the start button.
		_thisElement.find('.start').on( 'click', function() {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.addClass('descbox');

		// Show the upload and cancel all buttons only if there is something to cancel/upload.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 1){
			$('div#attachUpload').find('#attach-cancelAll, #attach-uploadAll').fadeIn();
		}
	});

	// Stuff to do when a file gets cancel.
	myDropzone.on('removedfile', function(file) {

		// Need to remove the file size to make sure theres plenty of room for another one.
		myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize - file.size;

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

		// Don't do anything if there is no response from server.
		if (!responseText){
			return;
		}

		// There is a general error.
		if (responseText.generalErrors){
			_thisElement.find('p.error').append(responseText.generalErrors.join('<br>'));
			return;
		}

		// Server returns an array.
		response = responseText.files[0];

		// Show the input field.
		_thisElement.find('.attach-info p.attached_BBC').fadeIn();

		// The request was complete but the server returned an error.
		if (typeof response.errors !== 'undefined' && response.errors.length > 0){

			_thisElement.addClass('errorbox').removeClass('descbox');

			// Show the server error.
			_thisElement.find('p.error').append(response.errors.join('<br>'));
			return;
		}

		// If there wasn't any error, change the current cover.
		_thisElement.addClass('infobox').removeClass('descbox');

		// Append the BBC.
		_thisElement.find('input[name="attachBBC"]').val('[attach]' + response.attachID + '[/attach]');

		file.insertAttachment(_thisElement, response.attachID);

		// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
		_thisElement.find('a.delete').fadeOutAndRemove('slow');

		// Fire up the delete button.
		file.deleteAttachment(_thisElement);
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

		// @todo prepare the "already uploaded" template.
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

		if (!confirm(smf_you_sure)){
			return;
		}

		myDropzone.removeAllFiles(true);
	});
	$('a#attach-uploadAll' ).on('click', function() {

		if (!confirm(smf_you_sure)){
			return;
		}

		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
	});

	// Need to tell the user they cannot post until all files are either uploaded or canceled.
	$("input[name ='post']").on('click', function(e) {

		attachAdded = myDropzone.getFilesWithStatus(Dropzone.ADDED).length;
		attachQueued = myDropzone.getFilesWithStatus(Dropzone.QUEUED).length;

		if (attachAdded > 0 || attachQueued > 0 ){
			alert(myDropzone.options.text_attachLeft);
			e.preventDefault();
			e.preventDefault();
			return false;
		}
	});

	// Hide the default way to show already atachments.
	$('#postAttachment').fadeOutAndRemove('slow');

	// Show any attachments already uploaded.
	if (typeof current_attachments !== "undefined"){
		$.each(current_attachments, function(key, mock) {

			myDropzone.emit("addedfile", mock);

			// If the attachment is an image and has a thumbnail, show it. Otherwise fallback to the generic thumbfile.
			if (!mock.type.match(/image.*/)) {
				myDropzone.emit('thumbnail', mock, smf_images_url +'/generic_attach.png');
			}

			// Build a preview image.
			else if (typeof mock.thumbID !== "undefined"){
				myDropzone.emit('thumbnail', mock, smf_prepareScriptUrl(smf_scripturl) +'action=dlattach;attach='+ mock.thumbID + ';type=preview');
			}

			// This file is "completed".
			myDropzone.emit("complete", mock);
		});
	}

}
