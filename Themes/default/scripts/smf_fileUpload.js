function smf_fileUpload(oOptions) {
	const previewNode = document.querySelector('#au-template');
	previewNode.id = '';
	const tmp = document.createElement('div');
	tmp.appendChild(previewNode.cloneNode(true));
	previewTemplate = tmp.innerHTML;
	previewNode.parentNode.removeChild(previewNode);

	const isNewTemplate = !!document.getElementById('post_attachments_area');

	if (typeof current_board == 'undefined')
		current_board = false;

	// Default values in case oOptions isn't defined.
	const dOptions = {
		url: smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=add;' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''),
		parallelUploads: 1,
		filesizeBase: 1024,
		paramName: 'attachment',
		uploadMultiple: true,
		previewsContainer: '#attachment_previews',
		previewTemplate,
		acceptedFiles: '.doc,.gif,.jpg,.pdf,.png,.txt,.zip',
		thumbnailWidth: 100,
		thumbnailHeight: null,
		autoQueue: isNewTemplate,
		clickable: isNewTemplate ? ['.attachment_spacer', '#drop_zone_ui'] : '.fileinput-button',
		currentUsedSize: 0,
		timeout: null,
		smf_insertBBC(file, w, h) {
			const mime_type = typeof file.type !== "undefined" ? file.type : (typeof file.mime_type !== "undefined" ? file.mime_type : '');
			const bbcOptionalParams = {
				width: mime_type.indexOf('image') == 0 && +w > 0 ? (' width=' + w) : '',
				height: mime_type.indexOf('image') == 0 && +h > 0 ? (' height=' + h) : '',
			};

			return '[attach id=' + file.attachID + bbcOptionalParams.width + bbcOptionalParams.height + ']' + (typeof file.name !== "undefined" ? decodeURIComponent(file.name.replace(/\+/g, ' ')) : '') + '[/attach]';
		},
		createMaxSizeBar() {
			let currentSize = Math.round(myDropzone.options.currentUsedSize / 1024);
			let maxSize = myDropzone.options.maxTotalSize;
			const usedPercentage = Math.round((currentSize / maxSize) * 100);

			if (isNewTemplate && maxSize > 1024) {
				maxSize = Math.round(((maxSize / 1024) + Number.EPSILON) * 100) / 100;
				currentSize = Math.round(((currentSize / 1024) + Number.EPSILON) * 10) / 10;
			}

			let percentage_class;
			if (usedPercentage <= 33)
				percentage_class = 'green';
			else if (usedPercentage >= 34 && usedPercentage <= 66)
				percentage_class = 'yellow';
			else
				percentage_class = 'red';

			document.querySelector('#max_files_progress').className = 'progress_bar progress_' + percentage_class;
			document.querySelector('#max_files_progress').style.display = 'block';
			document.querySelector('#max_files_progress_text').style.display = 'block';
			document.querySelector('#max_files_progress .bar').style.width = usedPercentage + '%';
			document.querySelector('#max_files_progress_text').textContent = myDropzone.options.text_max_size_progress.replace('{currentTotal}', maxSize).replace('{currentRemain}', currentSize);

			if (maxSize == 0) {
				document.querySelector('#max_files_progress').style.display = 'none';
				document.querySelector('#max_files_progress_text').style.display = 'none';
			}
		},
		accept(file, done) {
			const currentlyUsedKB = myDropzone.options.currentUsedSize / 1024;
			const totalKB = myDropzone.options.maxTotalSize;
			const fileKB = myDropzone.options.maxFilesize;
			const uploadedFileKB = file.size / 1024;

			if (myDropzone.options.maxFileAmount != null && myDropzone.getAcceptedFiles().length >= myDropzone.options.maxFileAmount) {
				document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachLimitNag;
				done(this.options.dictMaxFilesExceeded);
			} else {
				document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachDropzoneLabel;
			}

			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize + file.size;

			if (totalKB > 0 && currentlyUsedKB > totalKB) {
				done(myDropzone.options.text_totalMaxSize.replace('{currentTotal}', totalKB).replace('{currentRemain}', currentlyUsedKB));
				myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;
				file.status = Dropzone.CANCELED;
			} else if (fileKB > 0 && uploadedFileKB > fileKB) {
				done(myDropzone.options.dictFileTooBig);
				file.status = Dropzone.CANCELED;
				myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;
			} else {
				myDropzone.options.createMaxSizeBar();
				done();
			}
		},
		hideFileProgressAndAllButtonsIfNeeded() {
			if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 0) {
				document.querySelector('div#attachment_upload').querySelector('#attach_cancel_all, #attach_upload_all').style.display = 'none';
			}
			if (myDropzone.getAcceptedFiles().length == 0) {
				document.querySelector('#max_files_progress').style.display = 'none';
				document.querySelector('#max_files_progress_text').style.display = 'none';
			}
		},
	};

	if (oOptions.thumbnailHeight && oOptions.thumbnailWidth) {
		if (oOptions.thumbnailHeight > oOptions.thumbnailWidth) {
			oOptions.thumbnailWidth = null;
		} else {
			oOptions.thumbnailHeight = null;
		}
	}

	Object.assign(dOptions, oOptions);

	const myDropzone = new Dropzone('div#attachment_upload', dOptions);

	// Highlight the dropzone target as soon as a file is dragged onto the window.
	if (isNewTemplate) {
		let dragTimer;
		document.addEventListener('dragover', e => {
			const dt = e.dataTransfer;
			if (dt.types && (dt.types.indexOf ? dt.types.includes('Files') : dt.types.contains('Files'))) {
				document.querySelector("#attachment_upload").classList.add('dz-drag-hover');
				window.clearTimeout(dragTimer);
			}
		});
		document.addEventListener('dragleave', e => {
			dragTimer = window.setTimeout(() => {
				document.querySelector("#attachment_upload").classList.remove('dz-drag-hover');
			}, 25);
		});
		document.addEventListener('dragend', e => {
			dragTimer = window.setTimeout(() => {
				document.querySelector("#attachment_upload").classList.remove('dz-drag-hover');
			}, 25);
		});
	}

	myDropzone.on('addedfile', file => {

		const _thisElement = file.previewElement;

		// If the attachment is an image and has a thumbnail, show it. Otherwise fallback to the generic thumbfile.
		if (!file.type.match(/image.*/)) {
			myDropzone.emit('thumbnail', file, smf_images_url + '/generic_attach.png');
		} else if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			myDropzone.emit('thumbnail', file, smf_prepareScriptUrl(smf_scripturl) + 'action=dlattach;attach=' + (file.thumbID > 0 ? file.thumbID : file.attachID) + ';preview');
		}

		file.name = file.name.php_to8bit().php_urlencode();

		// Show the file info.
		_thisElement.querySelector('.attach-ui').style.display = 'block';

		// Show the progress bar
		document.querySelector('#max_files_progress').style.display = 'block';

		// Create a function to insert the BBC attach tag.
		file.insertAttachment = (_innerElement, response) => {
			// Backward compatibility for themes based on the pre-2.1.4 templates.
			if (!isNewTemplate) {
				insertButton = document.createElement('a');
				insertButton.className = 'button insertBBC';
				insertButton.disabled = false;
				insertButton.textContent = myDropzone.options.text_insertBBC;
				insertButton.addEventListener('click', e => {
					e.preventDefault();

					w = _innerElement.querySelector('input[name="attached_BBC_width"]').value;
					h = _innerElement.querySelector('input[name="attached_BBC_height"]').value;

					// Get the editor stuff.
					var e = document.getElementById(oEditorID);
					const oEditor = sceditor.instance(e);

					oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h), ' ');
				});
				_innerElement.querySelector('.attach-ui').appendChild(insertButton);
			}
			// Insert as an image.
			else if (file.type.match(/image.*/)) {
				let attached_BBC_width_height = _innerElement.querySelector('.attached_BBC_width_height');

				insertPanelButton = document.createElement('a');
				insertPanelButton.className = 'main_icons select_above floatright insertBBC';
				insertPanelButton.disabled = false;
				insertPanelButton.title = myDropzone.options.text_insertBBC;
				insertPanelButton.addEventListener('click', e => {
					attached_BBC_width_height.style.display = attached_BBC_width_height.style.display === 'none' ? 'block' : 'none';
				});
				attached_BBC_width_height.parentNode.insertBefore(insertPanelButton, attached_BBC_width_height);

				insertButton = document.createElement('a');
				insertButton.className = 'button insertBBC floatright';
				insertButton.disabled = false;
				insertButton.textContent = myDropzone.options.text_insertBBC;
				insertButton.addEventListener('click', e => {
					e.preventDefault();

					w = _innerElement.querySelector('input[name="attached_BBC_width"]').value;
					h = _innerElement.querySelector('input[name="attached_BBC_height"]').value;

					// Get the editor stuff.
					var e = document.getElementById(oEditorID);
					const oEditor = sceditor.instance(e);

					oEditor.insert(myDropzone.options.smf_insertBBC(response, w, h), '');

					attached_BBC_width_height.style.display = 'none';
				});
				attached_BBC_width_height.appendChild(insertButton);
			}
			// Insert as a plain link.
			else {
				insertButton = document.createElement('a');
				insertButton.className = 'main_icons select_above floatright insertBBC';
				insertButton.disabled = false;
				insertButton.title = myDropzone.options.text_insertBBC;
				insertButton.addEventListener('click', e => {
					e.preventDefault();

					// Get the editor stuff.
					var e = document.getElementById(oEditorID);
					const oEditor = sceditor.instance(e);

					oEditor.insert(myDropzone.options.smf_insertBBC(response, null, null), ' ');
				});
				_innerElement.querySelector('.attach-ui').appendChild(insertButton);
			}
		};

		// Replace the file with a message when the attachment has been deleted.
		file.deleteAttachment = (_innerElement, attachmentId, file) => {
			deleteButton = document.createElement('a');
			deleteButton.className = !isNewTemplate ? 'button' : 'main_icons delete floatright';
			deleteButton.disabled = false;
			deleteButton.title = myDropzone.options.text_deleteAttach;
			deleteButton.textContent = !isNewTemplate ? myDropzone.options.text_deleteAttach : '';
			deleteButton.addEventListener('click', function (e) {

				$this = this;

				// Perform the action only after receiving the confirmation.
				if (!confirm(smf_you_sure)) {
					return;
				}

				// Let the server know you want to delete the file you just recently uploaded...
				fetch(smf_prepareScriptUrl(smf_scripturl) + 'action=uploadAttach;sa=delete;attach=' + attachmentId + ';' + smf_session_var + '=' + smf_session_id + (current_board ? ';board=' + current_board : ''), {
					method: 'GET',
					headers: {
						"X-SMF-AJAX": 1
					},
					credentials: typeof allow_xhjr_credentials !== "undefined" ? 'include' : 'omit'
				}).then(response => {
					if (!response.ok) {
						throw new Error('Network response was not ok');
					}
					return response.json();
				}).then(data => {
					if (!isNewTemplate) {
						// For dramatic purposes only!
						_innerElement.classList.remove('infobox');
						_innerElement.classList.add(data.type + 'box');

						// Remove the text fields and insert button.
						_innerElement.querySelector('.attached_BBC').style.display = 'none';
						_innerElement.querySelector('.attachment_info a.insertBBC').style.display = 'none';
					}

					// Do stuff only if the file was actually accepted and it doesn't have an error status.
					if (file.accepted && file.status != Dropzone.ERROR) {

						// Need to remove the file size to make sure there's plenty of room for another one.
						myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

						// Re-count!
						myDropzone.options.createMaxSizeBar();

						file.accepted = false;

						// Show the current amount of remaining files
						document.querySelector('.attach_remaining').innerHTML = Math.max(myDropzone.options.maxFileAmount - myDropzone.getAcceptedFiles().length, 0);

						// Check against the max amount of files setting.
						if (myDropzone.getAcceptedFiles().length >= myDropzone.options.maxFileAmount) {
							document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachLimitNag;
						} else {
							document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachDropzoneLabel;
						}

						myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();

						if (isNewTemplate)
							_innerElement.remove();
					}
				}).catch(error => {
					// Tell the user something horrible happen!
					_innerElement.querySelector('span.error').innerHTML = error.message;
					_innerElement.querySelector('span.error').style.textDecoration = 'none';

					// For dramatic purposes only!
					_innerElement.classList.remove('infobox');
					_innerElement.classList.add('errorbox');
				});

				// Remove BBC from the post text, if present.
				const attachBbcRegex = new RegExp('\\[attach[^\\]]+id=' + attachmentId + '[^\\]]*\\][^\\[\\]]*\\[/attach\\]', 'g');

				var e = document.getElementById(oEditorID);
				const oEditor = sceditor.instance(e);
				const newEditorVal = oEditor.val().replace(attachBbcRegex, '');

				oEditor.val(newEditorVal);
			});

			if (!isNewTemplate)
				_innerElement.querySelector('.attach-ui').appendChild(deleteButton);
			else
				_innerElement.querySelector('.attach-ui').prepend(deleteButton);

			// Check against the max amount of files setting.
			if (myDropzone.getAcceptedFiles().length >= myDropzone.options.maxFileAmount) {
				document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachLimitNag;
			} else {
				document.querySelector('.attach_drop_zone_label').textContent = myDropzone.options.text_attachDropzoneLabel;
			}

			// Show the current amount of remaining files
			document.querySelector('.attach_remaining').innerHTML = Math.max(myDropzone.options.maxFileAmount - myDropzone.getAcceptedFiles().length, 0);
		};

		// The editor needs this to know how to handle embedded attachments
		file.addToCurrentAttachmentsList = (file, response) => {
			current_attachments.push({
				name: file.name,
				size: file.size,
				attachID: response.attachID,
				type: file.type,
				thumbID: (response.thumbID > 0 ? response.thumbID : response.attachID)
			});
		}

		// Hookup the upload button.
		_thisElement.querySelector('.upload').addEventListener('click', () => {
			myDropzone.enqueueFile(file);
		});

		// Show the main stuff!
		_thisElement.classList.add('descbox');

		// Show the upload and cancel all buttons only if there is something to cancel/upload.
		if (myDropzone.getFilesWithStatus(Dropzone.ADDED).length == 1) {
			document.querySelector('div#attachment_upload').querySelector('#attach_cancel_all, #attach_upload_all').style.display = 'inline-block';
		}
	});

	// Stuff to do when a file gets cancel.
	myDropzone.on('removedfile', file => {

		// Do stuff only if the file was actually accepted and it doesn't have an error status.
		if (file.accepted && file.status != Dropzone.ERROR) {
			// Need to remove the file size to make sure there's plenty of room for another one.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize - file.size;

			// Re-count!
			myDropzone.options.createMaxSizeBar();
		}

		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
	});

	// Event for when a file has been canceled
	myDropzone.on("canceled", function(file) {
		// Need to remove the file size to make sure there's plenty of room for another one.
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
	myDropzone.on('totaluploadprogress', progress => {
		document.querySelector('#total_progress span').style.width = progress + '%';
	});

	myDropzone.on('error', (file, errorMessage, xhr) => {
		const previewElement = file.previewElement;
		// Remove the 'upload' button.
		previewElement.querySelector('.upload').style.display = 'none';
		// Set a nice css class to make it more obvious there is an error.
		previewElement.classList.add('errorbox');
		previewElement.classList.remove('descbox');
	});

	myDropzone.on('success', (file, responseText, e) => {
		const previewElement = file.previewElement;
		// Remove the 'upload' button.
		previewElement.querySelector('.upload').style.display = 'none';
		// Don't do anything if there is no response from server.
		if (!responseText) {
			return;
		}
		// There is a general error.
		if (responseText.generalErrors) {
			previewElement.querySelector('span.error').innerHTML = responseText.generalErrors.join('<br>');
			return;
		}
		// Server returns an array.
		const response = responseText.files[0];
		// Show the input field and insert button.
		previewElement.querySelector('.attachment_info div.attached_BBC').style.display = 'block';
		previewElement.querySelector('.attachment_info a.insertBBC').style.display = 'block';
		if (typeof response.mime_type == "undefined" || response.mime_type.indexOf('image') != 0) {
			previewElement.querySelector('.attachment_info .attached_BBC_width_height').style.display = 'none';
		}
		// The request was complete but the server returned an error.
		if (typeof response.errors !== 'undefined' && response.errors.length > 0) {
			previewElement.classList.add('errorbox');
			previewElement.classList.remove('descbox');
			// Show the server error.
			previewElement.querySelector('span.error').innerHTML = response.errors.join('<br>');
			return;
		}
		// If there wasn't any error, change the current cover.
		previewElement.classList.remove('descbox');
		if (!isNewTemplate)
			previewElement.classList.add('infobox');
		// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
		previewElement.querySelector('a.cancel').style.display = 'none';
		// Append the BBC.
		const w = previewElement.querySelector('input[name="attached_BBC_width"]').value;
		const h = previewElement.querySelector('input[name="attached_BBC_height"]').value;
		previewElement.querySelector('input[name="attachBBC"]').value = myDropzone.options.smf_insertBBC(response, w, h);
		file.insertAttachment(previewElement, response);
		// Let the editor know about this attachment so it can handle the BBC correctly
		file.addToCurrentAttachmentsList(file, response);
	});

	myDropzone.on('uploadprogress', (file, progress, bytesSent) => {
		const previewElement = file.previewElement;
		// Get the current file box progress bar, set its inner span's width accordingly.
		previewElement.querySelector('.progress_bar .bar').style.width = progress + '%';
	});

	myDropzone.on('complete', file => {
		const previewElement = file.previewElement;
		// Hide the progress bar.
		previewElement.querySelector('.progress_bar').style.display = 'none';
		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
		// Finishing up mocking!
		if (typeof file.isMock !== "undefined" && typeof file.attachID !== "undefined") {
			// Show the input field.
			previewElement.querySelector('.attachment_info div.attached_BBC').style.display = 'block';
			previewElement.querySelector('.attachment_info a.insertBBC').style.display = 'block';
			if (typeof file.type == "undefined" || file.type.indexOf('image') != 0) {
				previewElement.querySelector('.attachment_info .attached_BBC_width_height').style.display = 'none';
			}
			// If there wasn't any error, change the current cover.
			previewElement.classList.remove('descbox');
			if (!isNewTemplate)
				previewElement.classList.add('infobox');
			// Remove the 'upload' button.
			previewElement.querySelector('.upload').remove();
			// You have already loaded this attachment, to prevent abuse, you cannot cancel it and upload a new one.
			previewElement.querySelector('a.cancel').remove();
			// Append the BBC.
			const w = previewElement.querySelector('input[name="attached_BBC_width"]').value;
			const h = previewElement.querySelector('input[name="attached_BBC_height"]').value;
			previewElement.querySelector('input[name="attachBBC"]').value = myDropzone.options.smf_insertBBC(file, w, h);
			file.insertAttachment(previewElement, file);
			// Need to count this towards the max limit.
			myDropzone.options.currentUsedSize = myDropzone.options.currentUsedSize + file.size;
			// Re-count and display the bar.
			myDropzone.options.createMaxSizeBar();
		}
	});

	// Show each individual's progress bar.
	myDropzone.on('sending', (file, xhr, formData) => {
		const previewElement = file.previewElement;
		// Show the progress bar when upload starts.
		previewElement.querySelector('.progress_bar').style.display = 'block';
		// Show the total progress bar when upload starts.
		document.getElementById("total_progress").style.display = 'block';
	});

	// Update the total progress bar.
	myDropzone.on("totaluploadprogress", progress => {
		document.querySelector("#total_progress span").style.width = progress + '%';
	});

	// Hide the total progress bar when nothing's uploading anymore.
	myDropzone.on("queuecomplete", () => {
		document.getElementById("total_progress").style.display = 'none';
	});

	// Add an event for uploading and cancelling all files.
	document.querySelector('a#attach_cancel_all').addEventListener('click', () => {
		if (!confirm(smf_you_sure)) return;

		myDropzone.getAddedFiles().forEach(file => {
			myDropzone.removeFile(file);
		});

		myDropzone.getFilesWithStatus(Dropzone.ERROR).forEach(file => {
			myDropzone.removeFile(file);
		});

		myDropzone.options.createMaxSizeBar();
		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
	});

	document.querySelector('a#attach_upload_all').addEventListener('click', () => {
		if (!confirm(smf_you_sure)) return;

		myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
		myDropzone.options.createMaxSizeBar();
		myDropzone.options.hideFileProgressAndAllButtonsIfNeeded();
	});

	// Need to tell the user they cannot post until all files are either uploaded or canceled.
	document.querySelector("input[name ='post']").addEventListener('click', e => {
		const attachAdded = myDropzone.getFilesWithStatus(Dropzone.ADDED).length;
		const attachQueued = myDropzone.getFilesWithStatus(Dropzone.QUEUED).length;

		if (attachAdded > 0 || attachQueued > 0) {
			alert(myDropzone.options.text_attachLeft);
			e.preventDefault();
			e.stopPropagation();
			return false;
		}
	});

	// Hide the default way to show already attached files.
	document.getElementById('postAttachment').remove();

	// Show any attachments already uploaded.
	if (typeof current_attachments !== "undefined") {
		current_attachments.forEach(mock => {
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

	// Hide this, too. The progress bar does a better job.
	document.querySelectorAll('.attach_available').forEach(element => {
		element.remove();
	});

	// Show the drag-and-drop instructions and buttons
	const dropZoneUi = document.getElementById('drop_zone_ui');
	dropZoneUi.style.display = !isNewTemplate ? 'block' : 'flex';

	// Show the attachment previews container
	const attachmentPreviews = document.getElementById('attachment_previews');
	attachmentPreviews.style.display = !isNewTemplate ? 'block' : 'flex';
}