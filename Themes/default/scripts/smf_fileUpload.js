function smf_fileUpload(oOptions) {

	var previewNode = document.querySelector('#au-template');
		previewNode.id = '';
		previewTemplate = previewNode.parentNode.innerHTML;
		previewNode.parentNode.removeChild(previewNode);

	// Default values in case oOptions isn't defined.
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''),
		parallelUploads: 1,
		filesizeBase: 1024,
		paramName: 'attachment',
		uploadMultiple: true,
		previewsContainer: '#attachment_previews',
		previewTemplate: previewTemplate,
		acceptedFiles: '.doc,.gif,.jpg,.pdf,.png,.txt,.zip',
		thumbnailWidth: 100,
		thumbnailHeight: null,
		autoQueue: false,
		clickable: '.fileinput-button',
		currentUsedSize: 0,
		timeout: null,
		smf_insertBBC: function (file, w, h) {

			var mime_type = typeof file.type !== "undefined" ? file.type : (typeof file.mime_type !== "undefined" ? file.mime_type : ''),
				bbcOptionalParams = {
					width: mime_type.indexOf('image') == 0 && + w > 0 ? (' width=' + w) : '',
					height: mime_type.indexOf('image') == 0 && + h > 0 ? (' height=' + h) : '',
					name: typeof file.name !== "undefined" ? (' name=' + file.name) : '',
					type: ' type=' + mime_type,
				};

			return '[attach' + bbcOptionalParams.width + bbcOptionalParams.height + decodeURIComponent(bbcOptionalParams.name) + bbcOptionalParams.type + ']' + file.attachID + '[/attach]';
		},
		createMaxSizeBar: function () {

			// Update the MaxSize bar to reflect the new size percentage.
			var currentSize = Math.round(myDropzone.options.currentUsedSize / 1024),
				maxSize = myDropzone.options.maxTotalSize,
				usedPercentage = Math.round($.fn.percentToRange($.fn.rangeToPercent(currentSize, 0, maxSize), 0, 100));

			// 3 basic colors.
			if (usedPercentage <= 33)
				percentage_class = 'green';

			else if (usedPercentage >= 34 && usedPercentage <= 66)
				percentage_class = 'yellow';

			else
				percentage_class = 'red';

			$('#max_files_progress').removeClass().addClass('progress_bar progress_' + percentage_class).show();
			$('#max_files_progress_text').show();
			$('#max_files_progress .bar').width(usedPercentage + '%');

			// Show or update the text.
			$('#max_files_progress_text').text(myDropzone.options.text_max_size_progress.replace('{currentTotal}', maxSize).replace('{currentRemain}', currentSize));

			if (maxSize == 0) {
				$('#max_files_progress').hide();
				$('#max_files_progress_text').hide();
			}
		},
		accept: function (file, done) {

			var currentlyUsedKB = myDropzone.options.currentUsedSize / 1024,
				totalKB = myDropzone.options.maxTotalSize,
				fileKB = myDropzone.options.maxFileSize,
				uploadedFileKB = file.size / 1024;

			// Check against the max amount of files setting.
			if ((myDropzone.options.maxFileAmount != null) && (myDropzone.getAcceptedFiles().length) >= myDropzone.options.maxFileAmount)
				done(this.options.dictMaxFilesExceeded);

			// Need to check if the added file doesn't surpass the total max size setting.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize + file.size;

			// This file has reached the max total size per post.
			if (totalKB > 0 && currentlyUsedKB > totalKB) {
				done(myDropzone.options.text_totalMaxSize.replace('{currentTotal}', totalKB).replace('{currentRemain}', currentlyUsedKB));

				// Remove the file size from the total
				myDropzone.options.currentUsedSize - file.size;

				// File is cancel.
				file.status = Dropzone.CANCELED;
			}
			else if (fileKB > 0 && uploadedFileKB > fileKB) {
				done(myDropzone.options.dictFileTooBig);

				// File is cancel.
				file.status = Dropzone.CANCELED;

				// File wasn't accepted so remove its size.
				myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;
			}
			else {

				myDropzone.options.createMaxSizeBar();

				// All done!
				done();
			}
		},
	};

	if (oOptions.thumbnailHeight && oOptions.thumbnailWidth) {
		if (oOptions.thumbnailHeight > oOptions.thumbnailWidth) {
			oOptions.thumbnailWidth = null;
		}

		else {
			oOptions.thumbnailHeight = null;
		}
	}

	$.extend(true, dOptions, oOptions);

	var myDropzone = new Dropzone('div#attachment_upload', dOptions);

	myDropzone.on('addedfile', function (file) {

		_thisElement = $(file.previewElement);

		// If the attachment is an image and has a thumbnail, show it. Otherwise fallback to the generic thumbfile.
		if (!file.type.match(/image.*/)) {
			myDropzone.emit('thumbnail', file, smf_images_url + '/generic_attach.png');
		}

		// If the file is too small, it won't have a thumbnail, show the regular file.
		else if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			myDropzone.emit('thumbnail', file, smf_prepareScriptUrl(smf_scripturl) + 'action=dlattach;attach=' + (file.thumbID > 0 ? file.thumbID : file.attachID) + ';type=preview');
		}

		file.name = file.name.php_to8bit().php_urlencode();

		// Show the file info.
		_thisElement.find('.attach-ui').fadeIn();

		// Show the progress bar
		$('#max_files_progress').show();

		// Create a function to insert the BBC attach tag.
		file.insertAttachment = function (_innerElement, response) {
			insertButton = $('<a />')
				.addClass('button')
				.prop('disabled', false)
				.text(myDropzone.options.text_insertBBC)
				.on('click', function (e) {
					e.preventDefault();

					w = _innerElement.find('input[name="attached_BBC_width"]').val();
					h = _innerElement.find('input[name="attached_BBC_height"]').val();

					// Get the editor stuff.
					var e = $('#' + oEditorID).get(0);
					var oEditor = sceditor.instance(e);

					oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h));
				})
				.appendTo(_innerElement.find('.attach-ui'));
		};

		// Replace the file with a message when the attachment has been deleted.
		file.deleteAttachment = function (_innerElement, attachmentId, file) {

			deleteButton = $('<a />')
				.addClass('button')
				.prop('disabled', false)
				.text(myDropzone.options.text_deleteAttach)
				.one('click', function (e) {

					$this = $(this);

					// Perform the action only after receiving the confirmation.
					if (!confirm(smf_you_sure)) {
						return;
					}

					// Let the server know you want to delete the file you just recently uploaded...
					$.ajax({
						url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach=' + attachmentId + ';' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''),
						type: 'GET',
						dataType: 'json',
						beforeSend: function () {
							ajax_indicator(true);
						},
						complete: function (jqXHR, textStatus) {
							ajax_indicator(false);

							// Delete the button.
							$this.fadeOutAndRemove('slow');
						},
						success: function (data, textStatus, xhr) {

							// For dramatic purposes only!
							_innerElement.removeClass('infobox').addClass(data.type + 'box');

							// Remove the text field and show a nice confirmation message.
							_innerElement.find('.attached_BBC').text(data.text);
							_thisElement.find('.attachment_info a.insertBBC').fadeOut();

							// Do stuff only if the file was actually accepted and it doesn't have an error status.
							if (file.accepted && file.status != Dropzone.ERROR) {

								// Need to remove the file size to make sure theres plenty of room for another one.
								myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

								// Re-count!
								myDropzone.options.createMaxSizeBar();
							}
						},
						error: function (xhr, textStatus, errorThrown) {

							// Tell the user something horrible happen!
							_innerElement.find('span.error').append(textStatus.error.join('<br>')).css({
								'text-decoration': 'none'
							});

							// For dramatic purposes only!
							_innerElement.removeClass('infobox').addClass('errorbox');
						}
					});
				})
				.appendTo(_innerElement.find('.attach-ui'));

				// Show the current amount of remaining files
				$('.attach_remaining').html(myDropzone.getAcceptedFiles().length);
		};

		// Hookup the upload button.
		_thisElement.find('.upload').on('click', function () {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.addClass('descbox');

		// Show the upload and cancel all buttons only if there is something to cancel/upload.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 1) {
			$('div#attachment_upload').find('#attach_cancel_all, #attach_upload_all').fadeIn('slow', function() {
					$(this).css('display', 'inline-block');
			});
		}
	});

	// Stuff to do when a file gets cancel.
	myDropzone.on('removedfile', function (file) {

		// Do stuff only if the file was actually accepted and it doesn't have an error status.
		if (file.accepted && file.status != Dropzone.ERROR) {
			// Need to remove the file size to make sure theres plenty of room for another one.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

			// Re-count!
			myDropzone.options.createMaxSizeBar();
		}

		// Hide the cancel and upload all buttons if there is nothing to cancel/upload anymore.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 0) {
			$('div#attachment_upload').find('#attach_cancel_all, #attach_upload_all').fadeOut();
			$('#max_files_progress').fadeOut();
		}
	});

    // Event for when a file has been canceled
    myDropzone.on("canceled", function(file) {
        // Need to remove the file size to make sure theres plenty of room for another one.
        myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

        // Re-count!
        myDropzone.options.createMaxSizeBar();

        this.removeFile(file);
    });

	// Event for when the total amount of files exceeds the maxFiles option
    myDropzone.on("maxfilesexceeded", function(file) {

        // Need to remove the file size to make sure there is plenty of room for another one.
        myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

        // Re-count!
        myDropzone.options.createMaxSizeBar();

    	this.removeFile(file);
    });

	// Update the total progress bar.
	myDropzone.on('totaluploadprogress', function (progress) {
		$('#total_progress span').width(progress + '%');
	});

	myDropzone.on('error', function (file, errorMessage, xhr) {

		_thisElement = $(file.previewElement);

		// Remove the 'upload' button.
		_thisElement.find('.upload').fadeOutAndRemove('slow');

		// Set a nice css class to make it more obvious there is an error.
		_thisElement.addClass('errorbox').removeClass('descbox');
	});

	myDropzone.on('success', function (file, responseText, e) {

		_thisElement = $(file.previewElement);

		// Remove the 'upload' button.
		_thisElement.find('.upload').fadeOutAndRemove('slow');

		// Don't do anything if there is no response from server.
		if (!responseText) {
			return;
		}

		// There is a general error.
		if (responseText.generalErrors) {
			_thisElement.find('span.error').append(responseText.generalErrors.join('<br>'));
			return;
		}

		// Server returns an array.
		response = responseText.files[0];

		// Show the input field and insert button.
		_thisElement.find('.attachment_info div.attached_BBC').fadeIn();
		_thisElement.find('.attachment_info a.insertBBC').fadeIn();

		if (typeof response.mime_type == "undefined" || response.mime_type.indexOf('image') != 0) {
			_thisElement.find('.attachment_info .attached_BBC_width_height').hide();
		}

		// The request was complete but the server returned an error.
		if (typeof response.errors !== 'undefined' && response.errors.length > 0) {

			_thisElement.addClass('errorbox').removeClass('descbox');

			// Show the server error.
			_thisElement.find('span.error').append(response.errors.join('<br>'));
			return;
		}

		// If there wasn't any error, change the current cover.
		_thisElement.addClass('infobox').removeClass('descbox');

		// Append the BBC.
		w = _thisElement.find('input[name="attached_BBC_width"]').val();
		h = _thisElement.find('input[name="attached_BBC_height"]').val();
		_thisElement.find('input[name="attachBBC"]').val(myDropzone.options.smf_insertBBC(response, w, h));

		file.insertAttachment(_thisElement, response);

		// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
		_thisElement.find('a.cancel').fadeOutAndRemove('slow');

		// Fire up the delete button.
		file.deleteAttachment(_thisElement, response.attachID, file);
	});

	myDropzone.on('uploadprogress', function (file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Get the current file box progress bar, set its inner span's width accordingly.
		_thisElement.find('.progress_bar .bar').width(progress + '%');
	});

	myDropzone.on('complete', function (file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Hide the progress bar.
		_thisElement.find('.progress_bar').fadeOut();

		// Finishing up mocking!
		if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			// Show the input field.
			_thisElement.find('.attachment_info div.attached_BBC').fadeIn();
			_thisElement.find('.attachment_info a.insertBBC').fadeIn();

			if (typeof file.type == "undefined" || file.type.indexOf('image') != 0) {
				_thisElement.find('.attachment_info .attached_BBC_width_height').hide();
			}

			// If there wasn't any error, change the current cover.
			_thisElement.addClass('infobox').removeClass('descbox');

			// Remove the 'upload' button.
			_thisElement.find('.upload').fadeOutAndRemove('slow');

			// Append the BBC.
			w = _thisElement.find('input[name="attached_BBC_width"]').val();
			h = _thisElement.find('input[name="attached_BBC_height"]').val();
			_thisElement.find('input[name="attachBBC"]').val(myDropzone.options.smf_insertBBC(file, w, h));

			file.insertAttachment(_thisElement, file);

			// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
			_thisElement.find('a.cancel').fadeOutAndRemove('slow');

			// Fire up the delete button.
			file.deleteAttachment(_thisElement, file.attachID, file);

			// Need to count this towards the max limit.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize + file.size;

			// Re-count and display the bar.
			myDropzone.options.createMaxSizeBar();
		}
	});

	// Show each individual's progress bar.
	myDropzone.on('sending', function (file, xhr, formData) {

		_thisElement = $(file.previewElement);

		// Show the progress bar when upload starts.
		_thisElement.find('.progress_bar').fadeIn();

		// Show the total progress bar when upload starts.
		$("#total_progress").fadeIn();
	});

	// Update the total progress bar.
	myDropzone.on("totaluploadprogress", function (progress) {
		$("#total_progress span").width(progress + '%');
	});

	// Hide the total progress bar when nothing's uploading anymore.
	myDropzone.on("queuecomplete", function (progress) {
		$("#total_progress").fadeOut();
	});

	// Add an event for uploading and cancelling all files.
	$('a#attach_cancel_all').on('click', function () {

		if (!confirm(smf_you_sure))
			return;

		myDropzone.removeAllFiles(true);
		myDropzone.options.createMaxSizeBar();

		// Set to zero
		myDropzone.options.currentUsedSize = 0;
		myDropzone.options.maxTotalSize = 0;

	});

	$('a#attach_upload_all').on('click', function () {

		if (!confirm(smf_you_sure)) {
			return;
		}

		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
		myDropzone.options.createMaxSizeBar();
	});

	// Need to tell the user they cannot post until all files are either uploaded or canceled.
	$("input[name ='post']").on('click', function (e) {

		attachAdded = myDropzone.getFilesWithStatus(Dropzone.ADDED).length;
		attachQueued = myDropzone.getFilesWithStatus(Dropzone.QUEUED).length;

		if (attachAdded > 0 || attachQueued > 0) {
			alert(myDropzone.options.text_attachLeft);
			e.preventDefault();
			e.stopPropagation();
			return false;
		}
	});

	// Hide the default way to show already attached files.
	$('#postAttachment').fadeOutAndRemove('slow');

	// Show any attachments already uploaded.
	if (typeof current_attachments !== "undefined") {
		$.each(current_attachments, function (key, mock) {

			// Tell the world this is a mock file!
			mock.isMock = true;

			// Tell everyone this file was accepted.
			mock.status = Dropzone.ADDED;
			mock.accepted = true;

			myDropzone.emit("addedfile", mock);

			// This file is "completed".
			myDropzone.emit("complete", mock);
		});
	}
}
