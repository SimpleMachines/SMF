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
		_thisElement.find('.start').on( "click", function() {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.addClass("descbox");
	});

	// Update the total progress bar.
	myDropzone.on('totaluploadprogress', function(progress) {
		$('#total-progress span').width(progress + '%');
	});

	myDropzone.on('error', function(file, errorMessage, xhr) {

		_thisElement = $(file.previewElement);

		// Remove the "start" button.
		_thisElement.find('p.start').remove();

		// Set a nice css class to make it more obvious theres an error.
		_thisElement.addClass("errorbox").removeClass("descbox");
	});

	myDropzone.on('success', function(file, responseText, e) {

		_thisElement = $(file.previewElement);

		// Remove the "start" button.
		_thisElement.find('.start').remove();

		// There is a general error.
		if (responseText.generalErrors){
			_thisElement.find('p.error').append(responseText.generalErrors.join('<br>'));
			return;
		}

		// Server returns an array.
		$.each(responseText.files, function( key, response ) {

			// The request was complete but the server returned an error.
			if (typeof response.errors !== 'undefined' && response.errors.length > 0){

				_thisElement.addClass("errorbox").removeClass("descbox");

				// Show the server error.
				_thisElement.find('p.error').append(response.errors.join('<br>'));
				return;
			}

			// If there wasn't any error, change the current cover.
			_thisElement.addClass("infobox").removeClass("descbox");

			bbcTag = '[attach]' + response.attachID + '[/attach]',
				insertBBC = $('<a />')
				.addClass('button_submit insertBBC')
				.prop('disabled', false)
				.text(myDropzone.options.text_insertBBC)
				.on('click', function (e) {
					e.preventDefault();
					$('#' + oEditorID).data('sceditor').sourceEditorInsertText(bbcTag);
				}),
				fieldTag = $('<p class="attached_BBC" />').append('<input type="text" name="attachBBC" value="'+ bbcTag +'" readonly>').append(insertBBC);

			_thisElement.find('div.attach-info').append(fieldTag);

			// Hold the cancel button.
			_thisElement.find('.delete').prop('disabled', true);

			// Create a delete button.
			deleteButton = $('<a />')
			.addClass('button_submit deleteButton you_sure')
			.prop('disabled', false)
			.text(myDropzone.options.text_deleteAttach)
			.on('click.deleteAttach', function (e) {

				$this = $(this);

				// Let the server know you want to delete the file you just recently uploaded...
				$.ajax({
					url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach='+ response.attachID +';' + smf_session_var + '=' + smf_session_id,
					type: 'GET',
					dataType: 'json',
					success: function (data, textStatus, xhr) {

						// Remove the text field and show a nice confirmation message.
						_thisElement.find('.attached_BBC').fadeOut();
						_thisElement.find('p.message').text(myDropzone.options.text_attachDeleted);

						// Remove this button and enable the cancel one.
						$this.remove();
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

	myDropzone.on("uploadprogress", function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Get the current file box progress bar, set its inner span's width accordingly.
		_thisElement.find('p.progressBar span').width(progress + "%");
	});

	myDropzone.on("complete", function(file, progress, bytesSent) {

		_thisElement = $(file.previewElement);

		// Hide the progress bar.
		_thisElement.find('p.progressBar').fadeOut();
	});

	myDropzone.on('sending', function(file) {

		_thisElement = $(file.previewElement);

		// Show the progress bar when upload starts.
		_thisElement.find('p.progressBar').fadeIn();
	});
}