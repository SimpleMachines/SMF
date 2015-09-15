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

		// Hookup the start button
		file.previewElement.querySelector('.start').onclick = function() { myDropzone.enqueueFile(file); };

		// Show the main stuff!
		file.previewElement.setAttribute("class", "descbox");
	});

	// Update the total progress bar
	myDropzone.on('totaluploadprogress', function(progress) {
		$('#total-progress span').width(progress + '%');
	});

	myDropzone.on('error', function(file, errorMessage, xhr) {

		var _thisElement = $(file.previewElement);

		// Remove the "start" button.
		_thisElement.find('p.start').remove();

		// Set a nice css class to make it more obvious theres an error.
		_thisElement.addClass("errorbox").removeClass("descbox");
	});

	myDropzone.on('success', function(file, responseText, e) {

		var _thisElement = $(file.previewElement);

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

			var bbcTag = '[attach]' + response.attachID + '[/attach]',
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
		});
	});

	myDropzone.on("uploadprogress", function(file, progress, bytesSent) {

		var _thisElement = $(file.previewElement);
		_thisElement.find('p.progressBar span').width(progress + "%");
	});

	myDropzone.on("complete", function(file, progress, bytesSent) {

		var _thisElement = $(file.previewElement);

		// Hide the progrss bar.
		_thisElement.find('p.progressBar').fadeOut();
	});

	myDropzone.on('sending', function(file) {

		var _thisElement = $(file.previewElement);

		// Show the progress bar when upload starts.
		_thisElement.find('p.progressBar').fadeIn();
	});
}