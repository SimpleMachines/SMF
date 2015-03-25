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
		previewCrop: false
		smf_mainDiv: '#fileupload',
		smf_text: {},
	};

	$.extend(dOptions, oOptions);

	var uploadButton = $('<button/>')
		.addClass('button_submit uploadButton')
		.prop('disabled', true)
		.text(dOptions.smf_text.processing)
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
		.text(dOptions.smf_text.processing)
		.on('click', function (e) {
			e.preventDefault();
			var $this = $(this),
				data = $this.data();

			data.abort();
			$this.remove();
			$this.closest('div').remove();
		});

	$(dOptions.smf_mainDiv).fileupload(dOptions)
		.on('fileuploadadd', function (e, data) {
			data.context = $('<div/>').addClass('attach_place_holder').appendTo('#files');
			$.each(data.files, function (index, file) {
				var node = $('<p/>')
						.append($('<span/>').text(file.name));
				if (!index) {
					node
						.append('<br>')
						.append(cancelButton.clone(true).data(data))
						.append(uploadButton.clone(true).data(data));
				}
				node.appendTo(data.context);
			});
		});
}