function smf_fileUpload(oOptions)
{
	// Default values in case oOptions isn't defined.
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id,
		dataType: 'json',
		dropZone: $('.drop_zone'),
		singleFileUploads:false,
		forceIframeTransport: false,
		autoUpload: false,
		paramName: 'attachment[]',
		acceptFileTypes: /(\.|\/)(doc|gif|jpg|mpg|pdf|png|txt|zip)$/i,
		maxFileSize: 128000, // 128 KB
		disableImageResize: /Android(?!.*Chrome)|Opera/
			.test(window.navigator.userAgent),
		previewMaxWidth: 100,
		previewMaxHeight: 100,
		previewCrop: false,
		maxNumberOfFiles: 10,
		limitMultiFileUploads: 10,
		limitMultiFileUploadSize: 1000000,
		smf_mainDiv: '#fileupload',
		smf_containerDiv: '#postAttachment',
		smf_text: {}
	};

	$.extend(true, dOptions, oOptions);

	if ($.support.fileInput) {
		$(dOptions.smf_mainDiv).prop('multiple', true);
		dOptions.dropZone.find('h3').show();
	}

	var uploadButton = $('<a/>')
		.addClass('button_submit uploadButton')
		.prop('disabled', true)
		.text(dOptions.smf_text.upload)
		.one('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data(),
				node = $('#attach_holder_' + data.uniqueID);

			// Show the progress bar.
			node.find('.progressBar').fadeIn();

			data.instance.submit();
			$this.remove();
		}),
		cancelButton = $('<a/>')
		.addClass('button_submit cancelButton')
		.prop('disabled', false)
		.text(dOptions.smf_text.cancel)
		.one('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data(),
				node = $(data.instance.context);

			// Gotta remove this from the number of files.
			--numberOfFiles;

			// And remove this file's size from the total.
			totalSize = totalSize - data.currentFile.size;

			// Need to remove this entry from the track array
			fileUpload.track = $.grep(fileUpload.track,
				function(o,i) { return o === data.index; },
			true);

			// And actually remove the button and the node.
			$this.remove();
			data.currentNode.fadeOut('slow', function() {
				data.currentNode.remove();
				data.instance.abort();
			});
		}),
	deleteButton = $('<a />')
		.addClass('button_submit deleteButton you_sure')
		.prop('disabled', false)
		.text(dOptions.smf_text.deleteAttach)
		.on('click', function (e) {

			var $this = $(this),
				mainData = $this.data(),
				node = $(mainData.context);

			// Let the server know you want to delete the file you just recently uploaded...
			$.ajax({
				url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach='+ mainData.currentFile['attachID'] +';' + smf_session_var + '=' + smf_session_id,
				type: 'GET',
				dataType: 'json',
				success: function (data, textStatus, xhr) {
					// Gotta remove this from the number of files.
					--numberOfFiles;

					// And remove this file's size from the total.
					totalSize = totalSize - mainData.currentFile['size'];

					// Replace the delete button with the "cancel" one.
					$this.replaceWith(cancelButton.clone(true).data(mainData));

					// Don't remove the entire node, just leave a message.
					node.find('.file_details').fadeOut('slow', function() {
						node.find('.file_details').text(dOptions.smf_text.attachDeleted);
						node.find('.file_details').fadeIn('slow', function() {
						});
					});

					// Remove the BBC tag.
					node.find('.file_info').empty();

					// Lastly, abort the whole thing.
					mainData.abort();
				},
				error: function (xhr, textStatus, errorThrown) {
					// Lastly, abort the whole thing.
					mainData.abort();

					node
						.find('.file_info')
						.append($('<p/>').text((typeof file.error !== 'undefined' ? file.error : dOptions.smf_text.genericError)));

					node.removeClass('descbox').addClass('errorbox');
					node.find('.uploadButton').remove();
				}
			});
		}),
	insertBBC = $('<a />')
		.addClass('button_submit insertBBC')
		.prop('disabled', false)
		.text(dOptions.smf_text.insertBBC)
		.on('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data(),
				node = $(data.context);

			// Get the text field value.
			oTag = node.find('input[name=attachBBC]').val();

			$('#' + oEditorID).data('sceditor').sourceEditorInsertText(oTag);
		}),
	uploadAll = $('<a/>')
		.addClass('button_submit uploadAllButton')
		.prop('disabled', true)
		.text(dOptions.smf_text.uploadAll)
		.one('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data();

			// Show the progress bar.
			data.context.find('.progressBar').fadeIn();

			data.submit();
		}),
	cancelAll = $('<a/>')
		.addClass('button_submit cancelAllButton')
		.prop('disabled', false)
		.text(dOptions.smf_text.cancelAll)
		.on('click', function (e) {
			e.preventDefault();

			var $this = $(this),
				data = $this.data(),
				node = $(data.context);

			// Gotta remove everything.
			numberOfFiles = 0;

			// And this stuff too!.
			totalSize = 0;

			// Reset!
			fileIndicator = false;

			$(fileUpload.track).each(function(index, i) {

				$('#attach_holder_' + i).fadeOut('slow', function() {
					$('#attach_holder_' + i).remove();
				});
			});

			// Remove both upload and cancel all buttons.
			$('.cancelAllButton').fadeOut('slow', function() {
				$(this).remove();
			});

			$('.uploadAllButton').fadeOut('slow', function() {
				$(this).remove();
			});

			// Reset the track array too!
			fileUpload.track = [];

			// Finally, abort all the things!
			data.abort();
		}),
	numberOfTimes = 0,
	numberOfFiles = 0,
	totalSize = 0,
	fileIndicator = false,
	uniqueID = 0

	fileUpload =  $(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {

			// Track the number of times this event has been fired.
			++numberOfTimes;

			// Create a master and empty div.
			data.context = $(dOptions.smf_containerDiv);

			// Show some general controls.
			if (!fileIndicator){
				// Keep track of each unique file added
				fileUpload.track = [];
			}

			// Append the file.
			$.each(data.files, function (index, file) {

				// Set a unique identifier for this file.
				++uniqueID;

				var node = $('<dd/>').addClass('attach_holder smalltext')
				.attr('id', 'attach_holder_' + uniqueID)
				.html('<div class="file_details"><div class="file_info"></div></div><div class="file_buttons clear"><div class="progressBar"><span></span></div>');

				// Hide the progress bar, we don't want to show it just yet!
				node.find('.progressBar').hide();

				node.find('.file_details')
						.prepend($('<p/>').text(file.name + ' (' + Math.round(file.size / 1024) + ' KB)'));

				// Need to pass some stuff to the upload and cancel buttons.
				toButtons = {'uniqueID': uniqueID, 'currentFile': file, 'currentNode': node, 'instance': data};

				// Append the current node info so it would be easier for the buttons to target it.
				node.find('.file_buttons')
						.append(cancelButton.clone(true).data(toButtons))
						.append(uploadButton.clone(true).data(toButtons));

				node.appendTo(data.context);
				fileUpload.track.push(uniqueID);
				data.files[index]['uniqueID'] = uniqueID;
			});

			// Show some general controls.
			if (!fileIndicator){
				fileIndicator = true;

				$('.attachControl')
					.append(cancelAll.clone(true).data(data))
					.append(uploadAll.clone(true).data(data));
			}
		})
		.on('fileuploadprocessalways', function (e, data) {
			$.each(data.files, function (index, file) {
				var node = $('#attach_holder_' + file.uniqueID);

				// Track the file size.
				totalSize = totalSize + file.size;

				// Show a nice preview.
				if (file.preview) {
					node
						.prepend($('<div class="file_preview"/>').prepend(file.preview));
				}

				if (file.error || numberOfFiles >= dOptions.maxNumberOfFiles || totalSize >= dOptions.limitMultiFileUploadSize) {
					// There isn't an error with the actual file, must be something else then!
					if (!file.error && numberOfFiles >= dOptions.maxNumberOfFiles)
						file.error = dOptions.messages.maxNumberOfFiles;

					// You reached the uploads total size.
					else if (totalSize >= dOptions.limitMultiFileUploadSize){
						file.error = dOptions.messages.maxTotalSize
							.replace('{currentTotal}', smf_fileUpload_bytesToSize(dOptions.limitMultiFileUploadSize))
							.replace('{currentRemain}', smf_fileUpload_bytesToSize(totalSize));
					}

					// Cancel the current upload.
					node
						.find('.file_info')
						.html($('<p/>').text((typeof file.error !== 'undefined' ? file.error : dOptions.smf_text.genericError)));

					node.removeClass('descbox').addClass('errorbox');
					data.abort();
				}

				// The file was added.
				if (index + 1 === data.files.length) {
					// "un-disable" the upload button :P
					data.context.find('.uploadButton')
						.prop('disabled', !!data.files.error);

					// The file has been appended, lets keep track of it!
					++numberOfFiles;

					// append some text here to tell the user what to do, hit Upload or hit Cancel...
					// or add some other indication that the file passed the client tests.
				}
			});
		})
		.on('fileuploaddone', function (e, data) {

			// Check out the general errors first...
			if (data.result.generalErrors){

				// Show the big fat generic error list!
				genericErrors = $('#attachGenericErrors ul');
				$.each(data.result.generalErrors, function (index, error) {
					genericErrors.append('<li>'+ error + '</li>');
				});

				genericErrors.fadeIn();
			}

			else{
				$.each(data.result.files, function (index, file) {

					var node = $(data.context.children('.attach_holder')[index]);

					// Hide the progress bar.
					node.find('.progressBar').fadeOut(function() {
						// Show the brand new attach ID bbc tag.
						if (file.attachID) {
							var bbcTag = $('<p class="attached_BBC" />').append(dOptions.smf_text.insertAttach + '<input type="text" name="attachBBC" value="[attach]' + file.attachID + '[/attach]" readonly>');

							// Replace the cancel button with a lovely "Delete" one.
							node.find('.cancelButton').replaceWith(deleteButton.clone(true).data(data));

							node
								.find('.file_info')
								.append(bbcTag);
							node
								.find('.file_buttons')
								.prepend(insertBBC.clone(true).data(data));

							// Append the current node/file to make it easier to handle it.
							data.currentNode = node;
							data.currentFile = file;
						}

						// Nope!
						else if (file.errors) {
							var errors = $('<p/>').html('<ul>');

							$.each(data.result.error, function (index, singleError) {
								errors.append('<li>' + singleError + '</li>');
							});

							// Close the ul
							errors.append('</ul>');

							node
								.find('.file_info')
								.append(errors);

							node.removeClass('descbox').addClass('errorbox');
						}
					});
				});
			}
		})
		.on('fileuploadprogress', function (e, data) {
			data.context.find('.uploadButton')
				.text(dOptions.smf_text.processing).fadeOut('slow', function() {
					data.context.find('.uploadButton').remove();
				});

			var progress = parseInt(data.loaded / data.total * 100, 10);
			data.context.find('.progressBar').children().css(
				'width',
				progress + '%'
			);
		});
}

function smf_fileUpload_bytesToSize(bytes) {

	if(typeof bytes == 'undefined' || bytes == 0){
		return 0;
	}

	var k = 1000; // change to 1024 for binary stuff
	var i = Math.floor(Math.log(bytes) / Math.log(k));
	return (bytes / Math.pow(k, i)).toPrecision(3);
}

$(function() {
	if (typeof oEditorID !== 'undefined'){
		$('.editeIinsertBBC').on('click', function() {
				// Get the text field value.
				var oValue = $(this).data('attach'),
					oTag = $('input[name=editedAttachBBC_'+ oValue +']').val();

				$('#' + oEditorID).data('sceditor').sourceEditorInsertText(oTag);
		});
	}

	// Since people will inevitably drop their files outside the drop area...
	window.addEventListener('dragover', function(e){
		e = e || event;
		e.preventDefault();
	},false);
	window.addEventListener('drop', function(e){
		e = e || event;
		e.preventDefault();
	},false);
});
