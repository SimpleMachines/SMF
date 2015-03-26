function smf_fileUpload(oOptions)
{
	var dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id,
		dataType: 'json',
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
				data = $this.data();
			$this
				.off('click')
				.text(dOptions.smf_text.cancel)
				.on('click', function () {
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

			data.abort();
			$this.remove();
			node.fadeOut();
		}),
	numberOfTimes = 0;

	$(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {

			// Keep track of the number of files.
			data.numberOfTimes = ++numberOfTimes;

			// Create a unique div holder for this file.
			data.context = $('<div/>').addClass('attach_holder descbox')
				.attr('id', 'attach_holder_' + data.numberOfTimes)
				.html('<div class="file_details"></div><div class="file_info"></div><div class="file_buttons clear"><div class="progressBar"><span></span></div>')
				.appendTo(dOptions.smf_containerDiv);

			// Hide the progress bar, we don't want to show it just yet!
			data.context.find('.progressBar').hide();

			// Append the file.
			$.each(data.files, function (index, file) {
				data.context.find('.file_details')
						.append($('<p/>').text(file.name));
				if (!index) {
					data.context.find('.file_buttons')
						.append(cancelButton.clone(true).data(data))
						.append(uploadButton.clone(true).data(data));
				}
			});
		})
		.on('fileuploadprocessalways', function (e, data) {
			var index = data.index,
				file = data.files[index],
				node = $(data.context);
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
				// append some text here to tell the user what to do, hit Upload or hit Cancel...
				// or add some other indication that the file passed the client test.
			}
		})
		.on('fileuploaddone', function (e, data) {
			$.each(data.result.files, function (index, file) {
				node = $(data.context.children()[index]);
				if (file.id) {
					var bbcTag = $('<span/>').text('[attach=' + file.id + ']');

					node
						.append(bbcTag)
						.addClass('infobox');

				} else if (file.errors) {
					var error = $('<span/>');

					$.each(file.errors, function (index, e) {

						error += e;
					});

					node.addClass('errorbox')
						.append('<br>')
						.append(error);
				}
			});
		})
		.on('fileuploadprogress', function (e, data) {
			data.context.find('.uploadButton')
				.text(dOptions.smf_text.processing)
				.prop('disabled', !!data.files.error);

			var progress = parseInt(data.loaded / data.total * 100, 10);
			data.context.find('.progressBar').children().css(
				'width',
				progress + '%'
			);
		});
}