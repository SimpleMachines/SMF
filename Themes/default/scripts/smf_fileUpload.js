function smf_fileUpload(oOptions) {

	var previewNode = document.querySelector('#au-template');
		previewNode.id = '';
	var tmp = document.createElement('div');
		tmp.appendChild(previewNode.cloneNode(true));
		previewTemplate = tmp.innerHTML;
		previewNode.parentNode.removeChild(previewNode);

	var isNewTemplate = !!document.getElementById('post_attachments_area');

	if (typeof current_board == 'undefined')
		current_board = false;

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
		autoQueue: isNewTemplate,
		clickable: isNewTemplate ? ['.attachment_spacer', '#drop_zone_ui'] : '.fileinput-button',
		currentUsedSize: 0,
		timeout: null,
		smf_insertBBC: function (file, w, h) {

			var mime_type = typeof file.type !== "undefined" ? file.type : (typeof file.mime_type !== "undefined" ? file.mime_type : ''),
				bbcOptionalParams = {
					width: mime_type.indexOf('image') == 0 && + w > 0 ? (' width=' + w) : '',
					height: mime_type.indexOf('image') == 0 && + h > 0 ? (' height=' + h) : '',
				};

			return '[attach id=' + file.attachID + bbcOptionalParams.width + bbcOptionalParams.height + ']' + (typeof file.name !== "undefined" ? decodeURIComponent(file.name.replace(/\+/g,' ')) : '') + '[/attach]';
		},
		createMaxSizeBar: function () {

			// Update the MaxSize bar to reflect the new size percentage.
			var currentSize = Math.round(myDropzone.options.currentUsedSize / 1024),
				maxSize = myDropzone.options.maxTotalSize,
				usedPercentage = Math.round($.fn.percentToRange($.fn.rangeToPercent(currentSize, 0, maxSize), 0, 100));

			if (isNewTemplate && maxSize > 1024) {
				maxSize = Math.round(((maxSize / 1024) + Number.EPSILON) * 100) / 100;
				currentSize = Math.round(((currentSize / 1024) + Number.EPSILON) * 10) / 10;
			}

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
				fileKB = myDropzone.options.maxFilesize,
				uploadedFileKB = file.size / 1024;

			// Check against the max amount of files setting.
			if ((myDropzone.options.maxFileAmount != null) && (myDropzone.getAcceptedFiles().length) >= myDropzone.options.maxFileAmount)
			{
				$('.attach_drop_zone_label').text(myDropzone.options.text_attachLimitNag);
				done(this.options.dictMaxFilesExceeded);
			}
			else
				$('.attach_drop_zone_label').text(myDropzone.options.text_attachDropzoneLabel);

			// Need to check if the added file doesn't surpass the total max size setting.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize + file.size;

			// This file has reached the max total size per post.
			if (totalKB > 0 && currentlyUsedKB > totalKB) {
				done(myDropzone.options.text_totalMaxSize.replace('{currentTotal}', totalKB).replace('{currentRemain}', currentlyUsedKB));

				// Remove the file size from the total
				myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

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
		hideFileProgressAndAllButtonsIfNeeded: function () {
			// Hide the cancel and upload all buttons if there is nothing to cancel/upload anymore.
			if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 0) {
				$('div#attachment_upload').find('#attach_cancel_all, #attach_upload_all').hide();
			}
			if (myDropzone.getAcceptedFiles().length == 0) {
				$('#max_files_progress').hide();
				$('#max_files_progress_text').hide();
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

	// Highlight the dropzone target as soon as a file is dragged onto the window.
	if (isNewTemplate)
	{
		var dragTimer;
		$(document).on('dragover', function(e) {
			var dt = e.originalEvent.dataTransfer;
			if (dt.types && (dt.types.indexOf ? dt.types.indexOf('Files') != -1 : dt.types.contains('Files'))) {
				$("#attachment_upload").addClass('dz-drag-hover');
				window.clearTimeout(dragTimer);
			}
		});
		$(document).on('dragleave dragend', function(e) {
			dragTimer = window.setTimeout(function() {
				$("#attachment_upload").removeClass('dz-drag-hover');
			}, 25);
		});
	}

	myDropzone.on('addedfile', function (file) {

		_thisElement = $(file.previewElement);

		// If the attachment is an image and has a thumbnail, show it. Otherwise fallback to the generic thumbfile.
		if (!file.type.match(/image.*/)) {
			myDropzone.emit('thumbnail', file, smf_images_url + '/generic_attach.png');
		}

		// If the file is too small, it won't have a thumbnail, show the regular file.
		else if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			myDropzone.emit('thumbnail', file, smf_prepareScriptUrl(smf_scripturl) + 'action=dlattach;attach=' + (file.thumbID > 0 ? file.thumbID : file.attachID) + ';preview');
		}

		file.name = file.name.php_to8bit().php_urlencode();

		// Show the file info.
		_thisElement.find('.attach-ui').show();

		// Show the progress bar
		$('#max_files_progress').show();

		// Create a function to insert the BBC attach tag.
		file.insertAttachment = function (_innerElement, response) {
			// Backward compatibility for themes based on the pre-2.1.4 templates.
			if (!isNewTemplate) {
				insertButton = $('<a />')
					.addClass('button')
					.addClass('insertBBC')
					.prop('disabled', false)
					.text(myDropzone.options.text_insertBBC)
					.on('click', function (e) {
						e.preventDefault();

						w = _innerElement.find('input[name="attached_BBC_width"]').val();
						h = _innerElement.find('input[name="attached_BBC_height"]').val();

						// Get the editor stuff.
						var e = $('#' + oEditorID).get(0);
						var oEditor = sceditor.instance(e);

						oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h), ' ');
					})
					.appendTo(_innerElement.find('.attach-ui'));
			}
			// Insert as an image.
			else if (file.type.match(/image.*/)) {
				let attached_BBC_width_height = _innerElement.find('.attached_BBC_width_height');

				insertPanelButton = $('<a />')
					.addClass('main_icons')
					.addClass('select_above')
					.addClass('floatright')
					.addClass('insertBBC')
					.prop('disabled', false)
					.prop('title', myDropzone.options.text_insertBBC)
					.on('click', function (e) {
						attached_BBC_width_height.toggle();
					})
					.insertBefore(attached_BBC_width_height);

				insertButton = $('<a />')
					.addClass('button')
					.addClass('insertBBC')
					.addClass('floatright')
					.prop('disabled', false)
					.text(myDropzone.options.text_insertBBC)
					.on('click', function (e) {
						e.preventDefault();

						w = _innerElement.find('input[name="attached_BBC_width"]').val();
						h = _innerElement.find('input[name="attached_BBC_height"]').val();

						// Get the editor stuff.
						var e = $('#' + oEditorID).get(0);
						var oEditor = sceditor.instance(e);

						oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h), '');

						attached_BBC_width_height.hide();
					})
					.appendTo(attached_BBC_width_height);
			}
			// Insert as a plain link.
			else {
				insertButton = $('<a />')
					.addClass('main_icons')
					.addClass('select_above')
					.addClass('floatright')
					.addClass('insertBBC')
					.prop('disabled', false)
					.prop('title', myDropzone.options.text_insertBBC)
					.on('click', function (e) {
						e.preventDefault();

						// Get the editor stuff.
						var e = $('#' + oEditorID).get(0);
						var oEditor = sceditor.instance(e);

						oEditor.insert(myDropzone.options.smf_insertBBC(response, null, null), ' ');
					})
					.appendTo(_innerElement.find('.attach-ui'));
			}
		};

		// Replace the file with a message when the attachment has been deleted.
		file.deleteAttachment = function (_innerElement, attachmentId, file) {
			deleteButton = $('<a />')
				.addClass(!isNewTemplate ? 'button' : 'main_icons delete floatright')
				.prop('disabled', false)
				.prop('title', myDropzone.options.text_deleteAttach)
				.text(!isNewTemplate ? myDropzone.options.text_deleteAttach : '')
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
						headers: {
							"X-SMF-AJAX": 1
						},
						xhrFields: {
							withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
						},
						dataType: 'json',
						beforeSend: function () {
							ajax_indicator(true);
						},
						complete: function (jqXHR, textStatus) {
							ajax_indicator(false);

							// Delete the button.
							if (!isNewTemplate)
								$this.fadeOutAndRemove();
						},
						success: function (data, textStatus, xhr) {
							if (!isNewTemplate) {
								// For dramatic purposes only!
								_innerElement.removeClass('infobox').addClass(data.type + 'box');

								// Remove the text fields and insert button.
								_innerElement.find('.attached_BBC').fadeOut();
								_innerElement.find('.attachment_info a.insertBBC').fadeOut();
							}

							// Do stuff only if the file was actually accepted and it doesn't have an error status.
							if (file.accepted && file.status != Dropzone.ERROR) {

								// Need to remove the file size to make sure theres plenty of room for another one.
								myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

								// Re-count!
								myDropzone.options.createMaxSizeBar();

								file.accepted = false;

								// Show the current amount of remaining files
								$('.attach_remaining').html(Math.max(myDropzone.options.maxFileAmount - myDropzone.getAcceptedFiles().length, 0));

								// Check against the max amount of files setting.
								if (myDropzone.getAcceptedFiles().length >= myDropzone.options.maxFileAmount)
								{
									$('.attach_drop_zone_label').text(myDropzone.options.text_attachLimitNag);
								}
								else
									$('.attach_drop_zone_label').text(myDropzone.options.text_attachDropzoneLabel);

								myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();

								if (isNewTemplate)
									_innerElement.remove();
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

					// Remove BBC from the post text, if present.
					var attachBbcRegex = new RegExp('\\[attach[^\\]]+id=' + attachmentId + '[^\\]]*\\][^\\[\\]]*\\[/attach\\]', 'g');

					var e = $('#' + oEditorID).get(0);
					var oEditor = sceditor.instance(e);
					var newEditorVal = oEditor.val().replace(attachBbcRegex, '');

					oEditor.val(newEditorVal);
				});

				if (!isNewTemplate)
					deleteButton.appendTo(_innerElement.find('.attach-ui'));
				else
					deleteButton.prependTo(_innerElement.find('.attach-ui'));

				// Check against the max amount of files setting.
				if (myDropzone.getAcceptedFiles().length >= myDropzone.options.maxFileAmount)
				{
					$('.attach_drop_zone_label').text(myDropzone.options.text_attachLimitNag);
				}
				else
					$('.attach_drop_zone_label').text(myDropzone.options.text_attachDropzoneLabel);

				// Show the current amount of remaining files
				$('.attach_remaining').html(Math.max(myDropzone.options.maxFileAmount - myDropzone.getAcceptedFiles().length, 0));
		};

		// The editor needs this to know how to handle embedded attachements
		file.addToCurrentAttachmentsList = function (file, response) {
			current_attachments.push({
				name: file.name,
				size: file.size,
				attachID: response.attachID,
				type: file.type,
				thumbID: (response.thumbID > 0 ? response.thumbID : response.attachID)
			});
		}

		// Hookup the upload button.
		_thisElement.find('.upload').on('click', function () {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.addClass('descbox');

		// Show the upload and cancel all buttons only if there is something to cancel/upload.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 1) {
			$('div#attachment_upload').find('#attach_cancel_all, #attach_upload_all').css('display', 'inline-block');
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

		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
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
		_thisElement.find('.upload').fadeOutAndRemove();

		// Set a nice css class to make it more obvious there is an error.
		_thisElement.addClass('errorbox').removeClass('descbox');
	});

	myDropzone.on('success', function (file, responseText, e) {

		_thisElement = $(file.previewElement);

		// Remove the 'upload' button.
		_thisElement.find('.upload').fadeOutAndRemove();

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
		_thisElement.removeClass('descbox');
		if (!isNewTemplate)
			_thisElement.addClass('infobox');

		// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
		_thisElement.find('a.cancel').fadeOutAndRemove();

		// Fire up the delete button.
		file.deleteAttachment(_thisElement, response.attachID, file);

		// Append the BBC.
		w = _thisElement.find('input[name="attached_BBC_width"]').val();
		h = _thisElement.find('input[name="attached_BBC_height"]').val();
		_thisElement.find('input[name="attachBBC"]').val(myDropzone.options.smf_insertBBC(response, w, h));

		file.insertAttachment(_thisElement, response);

		// Let the editor know about this attachment so it can handle the BBC correctly
		file.addToCurrentAttachmentsList(file, response);
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

		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();

		// Finishing up mocking!
		if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			// Show the input field.
			_thisElement.find('.attachment_info div.attached_BBC').fadeIn();
			_thisElement.find('.attachment_info a.insertBBC').fadeIn();

			if (typeof file.type == "undefined" || file.type.indexOf('image') != 0) {
				_thisElement.find('.attachment_info .attached_BBC_width_height').hide();
			}

			// If there wasn't any error, change the current cover.
			_thisElement.removeClass('descbox');
			if (!isNewTemplate)
				_thisElement.addClass('infobox');

			// Remove the 'upload' button.
			_thisElement.find('.upload').fadeOutAndRemove();

			// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
			_thisElement.find('a.cancel').remove();

			// Fire up the delete button.
			file.deleteAttachment(_thisElement, file.attachID, file);

			// Append the BBC.
			w = _thisElement.find('input[name="attached_BBC_width"]').val();
			h = _thisElement.find('input[name="attached_BBC_height"]').val();
			_thisElement.find('input[name="attachBBC"]').val(myDropzone.options.smf_insertBBC(file, w, h));

			file.insertAttachment(_thisElement, file);

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

		myDropzone.getAddedFiles().forEach(function(file){ myDropzone.removeFile(file) });
		myDropzone.getFilesWithStatus(Dropzone.ERROR).forEach(function(file){ myDropzone.removeFile(file) });

		myDropzone.options.createMaxSizeBar();

		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
	});

	$('a#attach_upload_all').on('click', function () {

		if (!confirm(smf_you_sure)) {
			return;
		}

		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
		myDropzone.options.createMaxSizeBar();

		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
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
	$('#postAttachment').remove();

	$('#attachment_previews').css('display', !isNewTemplate ? 'block' : 'flex');

	// Hide this, too. The progress bar does a better job.
	$('.attach_available').remove();

	// Show the drag-and-drop instructions and buttons
	$('#drop_zone_ui').css('display', !isNewTemplate ? 'block' : 'flex');

	// Show any attachments already uploaded.
	if (typeof current_attachments !== "undefined") {
		$.each(current_attachments, function (key, mock) {

			// Tell the world this is a mock file!
			mock.isMock = true;

			// Tell everyone this file was accepted.
			mock.status = Dropzone.ADDED;
			mock.accepted = true;

			myDropzone.emit("addedfile", mock);

			// Add to the files list
			mock.status = Dropzone.SUCCESS;
			myDropzone.files.push(mock);

			// This file is "completed".
			myDropzone.emit("complete", mock);
		});
	}
}
