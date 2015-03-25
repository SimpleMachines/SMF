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
				data = $this.data();

			data.abort();
			$this.remove();
			$('#attach_holder_' + data.numberOfTimes).remove();
		}),
	numberOfTimes = 0;

	$(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {
			data.numberOfTimes = ++numberOfTimes;
			data.context = $('<div/>').attr('id', 'attach_holder_' + data.numberOfTimes).appendTo(dOptions.smf_containerDiv);
			$.each(data.files, function (index, file) {
				var node = $('<p/>')
						.addClass('attach_node')
						.append($('<span/>').text(file.name));
				if (!index) {
					node
						.append('<br>')
						.append(cancelButton.clone(true).data(data))
						.append(uploadButton.clone(true).data(data));
				}
				node.appendTo(data.context);
			});
		})
		.on('fileuploadprocessalways', function (e, data) {
			var index = data.index,
				file = data.files[index],
				node = $(data.context.children()[index]);
			if (file.preview) {
				node
					.prepend('<br>')
					.prepend(file.preview);
			}
			if (file.error) {
				node
					.append('<br>')
					.append($('<span/>').text(file.error));

				node.addClass('errorbox');

				data.context.find('.uploadButton').remove();
			}
			if (index + 1 === data.files.length) {
				data.context.find('.uploadButton')
					.text(dOptions.smf_text.processing)
					.prop('disabled', !!data.files.error);
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
		var progress = parseInt(data.loaded / data.total * 100, 10);
		$('#progress .progress-bar').css(
			'width',
			progress + '%'
		);
	});
}