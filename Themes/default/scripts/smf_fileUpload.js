function smf_fileUpload(oOptions)
{
	// Check if the file should be accepted or not...
	Dropzone.prototype.accept = function(file, done) {
		if ((this.options.maxFiles != null) && this.getAcceptedFiles().length >= this.options.maxFiles) {
			done(this.options.dictMaxFilesExceeded);
			return this.emit("maxfilesexceeded", file);
		} else
			return this.options.accept.call(this, file, done);
	};

	var previewNode = document.querySelector('#au-template');
	previewNode.id = '';
	var previewTemplate = previewNode.parentNode.innerHTML;
	previewNode.parentNode.removeChild(previewNode);

	// Default values in case oOptions isn't defined.
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''),
		parallelUploads : 1,
		filesizeBase:1024,
		paramName: 'attachment',
		uploadMultiple:true,
		previewsContainer: '#au-previews',
		previewTemplate: previewTemplate,
		acceptedFiles: '.doc,.gif,.jpg,.pdf,.png,.txt,.zip',
		thumbnailWidth: 100,
		thumbnailHeight: null,
		autoQueue: false,
		clickable: '.fileinput-button',
		smf_insertBBC: function(file, w, h){

			var mime_type = typeof file.type !== "undefined" ? file.type : (typeof file.mime_type !== "undefined" ? file.mime_type : '');

			var bbcOptionalParams = {
				width: mime_type.indexOf('image') == 0 && +w > 0 ? (' width='+ w) : '',
				height: mime_type.indexOf('image') == 0 && +h > 0 ? (' height='+ h) : '',
				name: typeof file.name !== "undefined" ? (' name='+ file.name) : '',
				type: ' type=' + mime_type,
			};

			return '[attach' + bbcOptionalParams.width + bbcOptionalParams.height + decodeURIComponent(bbcOptionalParams.name) + bbcOptionalParams.type +']' + file.attachID + '[/attach]';
		},
		createMaxSizeBar: function(){

				// Update the MaxSize bar to reflect the new size percentage.
				var range_maxFile = Math.round($.fn.percentToRange($.fn.rangeToPercent(myDropzone.options.totalMaxSize, 0, myDropzone.options.maxLimitReferenceUploadSize), 0, 100));

				// 3 basic colors.
				if (range_maxFile <= 33)
					range_maxFile_class = 'green';

				else if (range_maxFile >= 34 && range_maxFile <= 66)
					range_maxFile_class = 'yellow';

				else
					range_maxFile_class = 'red';

				$('#maxFiles_progress').show();
				$('#maxFiles_progress_text').show();
				$('#maxFiles_progress').removeClass().addClass('progressBar progress_'+ range_maxFile_class);
				$('#maxFiles_progress span').width(range_maxFile + '%');

				// Show or update the text.
				$('#maxFiles_progress_text').text(myDropzone.options.text_max_size_progress.replace('{currentTotal}', (Math.round(myDropzone.options.maxLimitReferenceUploadSize / 1024))).replace('{currentRemain}', Math.round(myDropzone.options.totalMaxSize / 1024)));

				if (myDropzone.options.totalMaxSize == 0){
					$('#maxFiles_progress').hide();
					$('#maxFiles_progress_text').hide();
				}
		},
		accept: function(file, done) {

			// Need to check if the added file doesn't surpass the total max size setting.
			myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize + file.size;

			// This file has reached the max total size per post.
			if (myDropzone.options.maxLimitReferenceUploadSize > 0 && myDropzone.options.totalMaxSize > myDropzone.options.maxLimitReferenceUploadSize){
				done(myDropzone.options.text_totalMaxSize.replace('{currentTotal}', Math.round(myDropzone.options.maxLimitReferenceUploadSize / 1024)).replace('{currentRemain}', Math.round(myDropzone.options.totalMaxSize / 1024)));

				// File is cancel.
				file.status = Dropzone.CANCELED;
			}

			// The file is too big.
			if ((myDropzone.options.maxFilesize > 0) && (file.size > (myDropzone.options.maxFilesize * 1024))){
				done(myDropzone.options.dictFileTooBig);

				// File is cancel.
				file.status = Dropzone.CANCELED;

				// File wasn't accepted so remove its size.
				myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize - file.size;
			}
			else{

				myDropzone.options.createMaxSizeBar();

				// All done!
				done();
			}
		},
		totalMaxSize: 0
	};

	if(oOptions.thumbnailHeight && oOptions.thumbnailWidth) {
		if(oOptions.thumbnailHeight > oOptions.thumbnailWidth) {
			oOptions.thumbnailWidth = null;
		}

		else {
			oOptions.thumbnailHeight = null;
		}
	}

	$.extend(true, dOptions, oOptions);


	var myDropzone = new Dropzone('div#attachUpload', dOptions);

	myDropzone.on('addedfile', function(file) {

		_thisElement = $(file.previewElement);

		// If the attachment is an image and has a thumbnail, show it. Otherwise fallback to the generic thumbfile.
		if (!file.type.match(/image.*/)) {
			myDropzone.emit('thumbnail', file, smf_images_url +'/generic_attach.png');
		}

		// If the file is too small, it won't have a thumbnail, show the regular file.
		else if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			myDropzone.emit('thumbnail', file, smf_prepareScriptUrl(smf_scripturl) +'action=dlattach;attach='+ (file.thumbID > 0 ? file.thumbID : file.attachID) + ';type=preview');
		}

		file.name = file.name.php_to8bit().php_urlencode();

		// Show the file info.
		_thisElement.find('.attach-ui').fadeIn();

		// Create a function to insert the BBC attach tag.
		file.insertAttachment = function (_innerElement, response){
			insertButton = $('<a />')
			.addClass('button_submit')
			.prop('disabled', false)
			.text(myDropzone.options.text_insertBBC)
			.on('click', function (e) {
				e.preventDefault();

				w = _innerElement.find('input[name="attached_BBC_width"]').val();
				h = _innerElement.find('input[name="attached_BBC_height"]').val();

				// Get the editor stuff.
				var oEditor = $('#' + oEditorID).data('sceditor');

				oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h));
			})
			.appendTo(_innerElement.find('.attach-ui'));
		};

		// Replace the filled with a message when the attachment is deleted.
		file.deleteAttachment = function (_innerElement, attachmentId, file){

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
					url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach='+ attachmentId +';' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''),
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

						// For dramatic purposes only!
						_innerElement.removeClass('infobox').addClass(data.type +'box');

						// Remove the text field and show a nice confirmation message.
						_innerElement.find('.attached_BBC').text(data.text);
						_thisElement.find('.attach-info a.insertBBC').fadeOut();

						// Do stuff only if the file was actually accepted and it doesn't have an error status.
						if (file.accepted && file.status != Dropzone.ERROR) {

							// Need to remove the file size to make sure theres plenty of room for another one.
							myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize - file.size;

							// Re-count!
							myDropzone.options.createMaxSizeBar();
						}
					},
					error: function (xhr, textStatus, errorThrown) {

						// Tell the user something horrible happen!
						_innerElement.find('span.error').append(textStatus.error.join('<br>'));

						// For dramatic purposes only!
						_innerElement.removeClass('infobox').addClass('errorbox');
					}
				});
			})
			.appendTo(_innerElement.find('.attach-ui'));
		};

		// Hookup the upload button.
		_thisElement.find('.upload').on( 'click', function() {
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

		// Do stuff only if the file was actually accepted and it doesn't have an error status.
		if (file.accepted && file.status != Dropzone.ERROR) {

			// Need to remove the file size to make sure theres plenty of room for another one.
			myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize - file.size;

			// Re-count!
			myDropzone.options.createMaxSizeBar();
		}

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

		// Remove the 'upload' button.
		_thisElement.find('.upload').fadeOutAndRemove('slow');

		// Set a nice css class to make it more obvious theres an error.
		_thisElement.addClass('errorbox').removeClass('descbox');
	});

	myDropzone.on('success', function(file, responseText, e) {

		_thisElement = $(file.previewElement);

		// Remove the 'upload' button.
		_thisElement.find('.upload').fadeOutAndRemove('slow');

		// Don't do anything if there is no response from server.
		if (!responseText){
			return;
		}

		// There is a general error.
		if (responseText.generalErrors){
			_thisElement.find('span.error').append(responseText.generalErrors.join('<br>'));
			return;
		}

		// Server returns an array.
		response = responseText.files[0];

		// Show the input field and insert button.
		_thisElement.find('.attach-info div.attached_BBC').fadeIn();
		_thisElement.find('.attach-info a.insertBBC').fadeIn();

		if (typeof response.mime_type == "undefined" || response.mime_type.indexOf('image') != 0){
			_thisElement.find('.attach-info .attached_BBC_width_height').hide();
		}

		// The request was complete but the server returned an error.
		if (typeof response.errors !== 'undefined' && response.errors.length > 0){

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

	myDropzone.on('uploadprogress', function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Get the current file box progress bar, set its inner span's width accordingly.
		_thisElement.find('div.progressBar span').width(progress + '%');
	});

	myDropzone.on('complete', function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Hide the progress bar.
		_thisElement.find('div.progressBar').fadeOut();

		// Finishing up mocking!
		if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined"){
			// Show the input field.
			_thisElement.find('.attach-info div.attached_BBC').fadeIn();
			_thisElement.find('.attach-info a.insertBBC').fadeIn();

			if (typeof file.type == "undefined" || file.type.indexOf('image') != 0){
				_thisElement.find('.attach-info .attached_BBC_width_height').hide();
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
			myDropzone.options.totalMaxSize = myDropzone.options.totalMaxSize + file.size;

			// Re-count and display the bar.
			myDropzone.options.createMaxSizeBar();
		}
	});

	// Show each individual's progress bar.
	myDropzone.on('sending', function(file, xhr, formData) {

		_thisElement = $(file.previewElement);

		// Show the progress bar when upload starts.
		_thisElement.find('div.progressBar').fadeIn();

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
		myDropzone.options.createMaxSizeBar();
	});

	$('a#attach-uploadAll' ).on('click', function() {

		if (!confirm(smf_you_sure)){
			return;
		}

		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
		myDropzone.options.createMaxSizeBar();
	});

	// Need to tell the user they cannot post until all files are either uploaded or canceled.
	$("input[name ='post']").on('click', function(e) {

		attachAdded = myDropzone.getFilesWithStatus(Dropzone.ADDED).length;
		attachQueued = myDropzone.getFilesWithStatus(Dropzone.QUEUED).length;

		if (attachAdded > 0 || attachQueued > 0 ){
			alert(myDropzone.options.text_attachLeft);
			e.preventDefault();
			e.stopPropagation();
			return false;
		}
	});

	// Hide the default way to show already atached files.
	$('#postAttachment').fadeOutAndRemove('slow');

	// Show any attachments already uploaded.
	if (typeof current_attachments !== "undefined"){
		$.each(current_attachments, function(key, mock) {

			// Tell the world this is a mock file!
			mock.isMock = true;

			// Tell eveyone this file was accepted.
			mock.status = Dropzone.ADDED;
			mock.accepted = true;

			myDropzone.emit("addedfile", mock);

			// This file is "completed".
			myDropzone.emit("complete", mock);
		});
	}
}
