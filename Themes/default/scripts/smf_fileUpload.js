function smf_fileUpload(oOptions)
{
	// Default values in case oOptions isn't defined.
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id,
		dataType: 'json',
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
		smf_containerDiv: '#files',
		smf_text: {}
	};

	$.extend(true, dOptions, oOptions);

	var uploadButton = $('<button/>')
		.addClass('button_submit uploadButton')
		.prop('disabled', true)
		.text(dOptions.smf_text.upload)
		.on('click', function (e) {
			e.preventDefault();
			data.submit().always(function () {
				$this.remove();
			});
		}),
	cancelButton = $('<button/>')
		.addClass('button_submit cancelButton')
		.prop('disabled', false)
		.text(dOptions.smf_text.cancel)
		.on('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data(),
				node = $(data.context);

			// Gotta remove this from the number of files.
			--numberOfFiles;

			$this.remove();
			data.currentNode.fadeOut();
		}),
	numberOfTimes = 0,
	numberOfFiles = 0;

	$(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {

			++numberOfTimes;

			// Create a master and empty div.
			data.context = $('<div/>').addClass('attach_container').appendTo(dOptions.smf_containerDiv);

			// Append the file.
			$.each(data.files, function (index, file) {
				var node = $('<div/>').addClass('attach_holder descbox')
				.attr('id', 'attach_holder_' + numberOfTimes)
				.html('<div class="file_details"></div><div class="file_info"></div><div class="file_buttons clear"><div class="progressBar"><span></span></div>');

				// Hide the progress bar, we don't want to show it just yet!
				node.find('.progressBar').hide();

				node.find('.file_details')
						.append($('<p/>').text(file.name));

				// Append the current node info so it would be easier for the buttons to target it.
				data.currentNode = node;

				node.find('.file_buttons')
						.append(cancelButton.clone(true).data(data))
						.append(uploadButton.clone(true).data(data));


				node.appendTo(data.context);
			});
		})
		.on('fileuploadsend', function (e, data) {

			// Show the progress bar.
			data.context.find('.progressBar').fadeIn();

		})
		.on('fileuploadprocessalways', function (e, data) {
			var index = data.index,
				file = data.files[index],
				node = $(data.context.children()[index]);

			if (file.preview) {
				node
					.find('.file_details')
					.append($('<p/>').prepend(file.preview));
			}
			if (file.error || numberOfFiles >= dOptions.maxNumberOfFiles) {
				// There isn't an error with the actual file, must be something else then!
				if (!file.error && numberOfFiles >= dOptions.maxNumberOfFiles)
					file.error = dOptions.messages.maxNumberOfFiles;

				data.abort();
			}
			if (index + 1 === data.files.length) {
				// "un-disable" the upload button :P
				data.context.find('.uploadButton')
					.prop('disabled', !!data.files.error);

				// The file has been appended, lets keep track of it!
				++numberOfFiles;

				// append some text here to tell the user what to do, hit Upload or hit Cancel...
				// or add some other indication that the file passed the client test.
			}console.log(numberOfFiles);
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

					var node = $(data.context.children()[index]);

					// Hide the progress bar.
					node.find('.progressBar').fadeOut();

					if (file.id) {
						var bbcTag = $('<p/>').append('<input type="text" name="attachBBC" value="[attach=' + data.result.data.id + ']" />');

						node
							.find('.file_info')
							.append(bbcTag);

						node.removeClass('descbox').addClass('infobox');

					}
					else if (file.errors) {
						var errors = $('<p/>').html('<dl>');

						$.each(data.result.error, function (index, singleError) {
							errors.append(singleError.toString());
						});

						// Close the dl
						errors.append('</dl>');

						node
							.find('.file_info')
							.append(errors);

						node.removeClass('descbox').addClass('errorbox');
					}
				});
			}
		})
		.on('fileuploadprogress', function (e, data) {
			data.context.find('.uploadButton')
				.text(dOptions.smf_text.processing);

			var progress = parseInt(data.loaded / data.total * 100, 10);
			data.context.find('.progressBar').children().css(
				'width',
				progress + '%'
			);
		})
		.on('fileuploadfail', function (e, data) {
			$.each(data.files, function (index, file) {
				var node = $(data.context.children()[index]);

				// Hide the progress bar.
				node.find('.progressBar').fadeOut();
				node
					.find('.file_info')
					.append($('<p/>').text((typeof file.error !== 'undefined' ? file.error : dOptions.smf_text.genericError)));

				node.removeClass('descbox').addClass('errorbox');
				node.find('.uploadButton').remove();
			});
		});
}
