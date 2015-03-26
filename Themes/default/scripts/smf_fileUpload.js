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
			var $this = $(this),
				data = $this.data(),
				node = $(data.context);
			$this
				.off('click')
				.text(dOptions.smf_text.cancel)
				.one('click', function () {
					$this.remove();
					data.abort();
				});
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

			data.abort();
			$this.remove();
			data.currentNode.fadeOut();
		}),
	numberOfTimes = 0,
	numberOfFiles = 0;

	$(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {

			// Check if the user hasn't reach the attach limit.
			if (numberOfFiles >= dOptions.maxNumberOfFiles)
			{
				// Finish the current upload process.
				data.abort();

				// Tell the user about it.
				alert(dOptions.messages.maxNumberOfFiles);

				return;
			}

			// Keep track of the number of times this event was fired.
			data.numberOfTimes = ++numberOfTimes;

			// Create a master and empty div.
			data.context = $('<div/>').appendTo(dOptions.smf_containerDiv);

			// Append the file.
			$.each(data.files, function (index, file) {
				var node = $('<div/>').addClass('attach_holder descbox')
				.attr('id', 'attach_holder_' + data.numberOfTimes)
				.html('<div class="file_details"></div><div class="file_info"></div><div class="file_buttons clear"><div class="progressBar"><span></span></div>');

				// Hide the progress bar, we don't want to show it just yet!
				node.find('.progressBar').hide();

				node.find('.file_details')
						.append($('<p/>').text(file.name));

				// Got something, show some buttons.
				if (!index) {

					// Append the current node info so it would be easier for the buttons to target it.
					data.currentNode = node;

					node.find('.file_buttons')
						.append(cancelButton.clone(true).data(data))
						.append(uploadButton.clone(true).data(data));
				}

				node.appendTo(data.context);
			});

			// Append the file.
			$.each(data.files, function (index, file) {
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
			if (file.error) {
				node
					.find('.file_info')
					.append($('<p/>').text(file.error));

				node.removeClass('descbox').addClass('errorbox');

				node.find('.uploadButton').remove();
			}
			if (index + 1 === data.files.length) {
				// "un-disable" the upload button :P
				data.context.find('.uploadButton')
					.prop('disabled', !!data.files.error);

				// The file has been appended, lets keep track of it!
				++numberOfFiles;

				// append some text here to tell the user what to do, hit Upload or hit Cancel...
				// or add some other indication that the file passed the client test.
			}
		})
		.on('fileuploaddone', function (e, data) {

			node = $(data.context);
			// Hide the progress bar.
			node.find('.progressBar').fadeOut();
console.log(data.result);
				if (!data.result.error && data.result.data) {
					var bbcTag = $('<p/>').append('<input type="text" name="attachBBC" value="[attach=' + data.result.data.id + ']" />');

					node
						.find('.file_info')
						.append(bbcTag);

					node.removeClass('descbox').addClass('infobox');

				}
				else if (data.result.error) {
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
		})
		.on('fileuploadprogress', function (e, data) {
			data.context.find('.uploadButton')
				.text(dOptions.smf_text.processing);

			var progress = parseInt(data.loaded / data.total * 100, 10);
			data.context.find('.progressBar').children().css(
				'width',
				progress + '%'
			);
		});
}
