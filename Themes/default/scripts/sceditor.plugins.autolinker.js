(function (sceditor) {
	'use strict';

	// Our custom autolinker plugin.
	sceditor.plugins.autolinker = function () {
		if (typeof autolinker_regexes === 'undefined') {
			return;
		}

		const testOnKeyDown = [
			'Enter',
			'ArrowLeft',
			'ArrowRight',
			'ArrowUp',
			'ArrowDown',
			'End',
			'Home',
			'PageDown',
			'PageUp',
		];

		// Detects and links plain text URLs when the user presses certain keys down.
		this.signalKeydownEvent = function (e) {
			if (this.inSourceMode() || !testOnKeyDown.includes(e.key)) {
				return;
			}

			const rangeHelper = this.getRangeHelper();
			const range = rangeHelper.selectedRange();

			// Are we in a link or a span that was specifically set not to autolink?
			if (
				range.endContainer.parentNode.closest('a')
				|| range.endContainer.parentNode.closest('span.nolink')
			) {
				return;
			}

			// Only do this when the caret is at the end of a string of non-space characters.
			if (range.endContainer.textContent.substring(range.endOffset).match(/^\S/)) {
				return;
			}

			// We want to search from the start of the current text node to the caret position.
			let str = range.endContainer.textContent.substring(0, range.endOffset);

			let found = false;

			for (const [name, regex] of autolinker_regexes.entries()) {
				if (!name.startsWith('keypress_')) {
					continue;
				}

				// Ensure the search always starts from the beginning.
				regex.lastIndex = 0;

				// Append a space so that the keyup regex will match.
				const url = regex.exec(str + " ");

				if (url !== null) {
					found = true;

					insertAutolink(this, str, url, regex, name, rangeHelper);

					break;
				}
			}

			if (!found) {
				removeAutolink(rangeHelper, range.startOffset);
			}
		};

		// Detects and links plain text URLs when user releases a key.
		this.signalKeyupEvent = function (e) {
			if (this.inSourceMode()) {
				return;
			}

			const rangeHelper = this.getRangeHelper();
			const range = rangeHelper.selectedRange();

			// Are we in a span that was specifically set not to autolink?
			if (range.endContainer.parentNode.closest('span.nolink')) {
				return;
			}

			// We want to search from the start of the current text node to the caret position.
			let str = range.endContainer.textContent.substring(0, range.endOffset);

			let found = false;

			if (!testOnKeyDown.includes(e.key)) {
				for (const [name, regex] of autolinker_regexes.entries()) {
					if (!name.startsWith('keypress_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					const url = regex.exec(str);

					if (url !== null) {
						found = true;

						insertAutolink(this, str, url, regex, name, rangeHelper);

						// Put the caret back where it was originally.
						rangeHelper.selectRange(range);

						break;
					}
				}
			}
		};

		// Used when editing an existing link or an "nolink" span.
		this.signalInputEvent = function (e) {
			if (this.inSourceMode() && ['insertText', 'insertLineBreak', 'insertParagraph'].includes(e.inputType)) {
				const caretPos = this.sourceEditorCaret().start;
				const val = this.val();
				const valBefore = val.substring(0, caretPos);
				const valAfter = val.substring(caretPos);

				for (const [name, regex] of autolinker_regexes.entries()) {
					if (!name.startsWith('keypress_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					let found = false;
					let url = regex.exec(valBefore);

					if (url !== null) {
						// Wrap in BBC tags.
						this.sourceEditorCaret({start: url.index, end: regex.lastIndex});

						const bbc_tag = name.endsWith('email') ? 'email' : url[0].startsWith(smf_scripturl) ? 'iurl' : 'url';

						const tag_param = name.endsWith('naked_domain') ? '="//' + url[0] + '"' : '';

						this.insert('[' + bbc_tag + tag_param + ']', '[/' + bbc_tag + ']');

						// Bump the caret along by the length of the inserted tags.
						this.sourceEditorCaret({start: caretPos + bbc_tag.length * 2 + tag_param.length + 5, end: caretPos + bbc_tag.length * 2 + tag_param.length + 5});

						// Don't try any more regular expressions.
						break;
					}
				}

				return;
			}

			const rangeHelper = this.getRangeHelper();
			const range = rangeHelper.selectedRange();
			const parent = rangeHelper.parentNode();
			const container = parent.parentNode;
			const containerParent = container.parentNode;

			// Adding text immediately after an existing link.
			if (
				e.inputType === 'insertText'
				&& parent.nodeType === Node.TEXT_NODE
				&& parent.textContent === e.data
				&& parent.previousSibling
				&& parent.previousSibling.nodeType === Node.ELEMENT_NODE
				&& parent.previousSibling.nodeName === 'A'
				&& parent.previousSibling.href.replace(/\/$/, '').replace(/^mailto:/, '').startsWith(parent.previousSibling.textContent.replace(/\/$/, '').replace(/^mailto:/, ''))
			) {
				// Turn the link back into plain text.
				parent.previousSibling.replaceWith(parent.previousSibling.textContent);
				containerParent.normalize();

				// Put the caret back where it was originally.
				rangeHelper.selectRange(range);
			}

			// Inside an existing link.
			if (
				container.nodeType === Node.ELEMENT_NODE
				&& container.nodeName === 'A'
			) {
				containerParent.normalize();
				const str = container.textContent;

				// Pressed backspace inside a link.
				if (e.inputType === 'deleteContentBackward') {
					const caretPos = range.startOffset;

					// Turn the link back into plain text.
					const strBefore = document.createTextNode(str.substring(0, caretPos));
					const strAfter = document.createTextNode(str.substring(caretPos));

					containerParent.insertBefore(strBefore, container);
					containerParent.replaceChild(strAfter, container);
					containerParent.normalize();

					// Put the caret back where it was originally.
					rangeHelper.selectRange(range);
					return;
				}

				// Any other edits.
				for (const [name, regex] of autolinker_regexes.entries()) {
					if (name.startsWith('keypress_') || name.startsWith('paste_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					// If text content is a URL, update the href.
					if (regex.test(str)) {
						container.href = (name === 'email' ? 'mailto:' : '') + str;
						break;
					}
				}

				return;
			}

			// Inside a span that was specifically set not to autolink.
			if (
				container.nodeType === Node.ELEMENT_NODE
				&& container.nodeName === 'SPAN'
				&& container.classList.contains('nolink')
			) {
				const caretPos = range.startOffset;

				containerParent.normalize();
				const str = container.textContent;

				let url = null;

				for (const [name, regex] of autolinker_regexes.entries()) {
					if (name.startsWith('keypress_') || name.startsWith('paste_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					url = regex.exec(str);

					if (url !== null) {
						break;
					}
				}

				// If the nolink span no longer contains a URL, remove the span.
				if (url === null) {
					const strBefore = document.createTextNode(str.substring(0, caretPos));
					const strAfter = document.createTextNode(str.substring(caretPos));

					containerParent.insertBefore(strBefore, container);
					containerParent.replaceChild(strAfter, container);
					containerParent.normalize();

					// Put the caret back where it was originally.
					rangeHelper.selectRange(range);
					return;
				}

				// Move any trailing spaces out of the nolink span.
				const trailing = str.match(/\s+$/);

				if (trailing !== null && str.replace(/\s+$/, '') === url[0]) {
					const newText = document.createTextNode(trailing[0]);
					const newSpan = document.createElement('span');
					newSpan.classList.add('nolink')
					newSpan.textContent = url[0];

					containerParent.insertBefore(newSpan, container);
					containerParent.insertBefore(newText, container);
					containerParent.removeChild(container);
					containerParent.normalize();

					// Put the caret back where it was originally.
					rangeHelper.selectRange(range);
				}
			}
		};

		// Autolink URLs that are pasted into the editor.
		this.signalPasteRaw = function (data) {
			if (!data.html && data.text) {
				data.html = data.text;
			}

			for (const [name, regex] of autolinker_regexes.entries()) {
				if (!name.startsWith('paste_')) {
					continue;
				}

				const url = regex.exec(data.html);

				if (url !== null) {
					const bbc_tag = name === 'paste_email' ? 'email' : (url[0].startsWith(smf_scripturl) ? 'iurl' : 'url');

					data.html = data.html.replace(regex, '<a data-type="' + bbc_tag + '" href="' + (bbc_tag === 'email' ? 'mailto:' : '') + url[0] + '">' + url[0] + '</a>');

					break;
				}
			}
		};

		// Helper for this.signalKeydownEvent and this.signalKeyupEvent.
		function insertAutolink(editor, str, url, regex, regex_name, rangeHelper) {
			// Trim off trailing brackets and quotes that aren't part of balanced pairs.
			let found_trailing_bracket_quote = false;
			do {
				for (const [opener, closer] of autolinker_balanced_pairs.entries()) {
					found_trailing_bracket_quote = url[0].endsWith(opener) || url[0].endsWith(closer);

					if (url[0].endsWith(opener)) {
						url[0] = url[0].slice(0, -1);
						regex.lastIndex--;
						break;
					}

					if (url[0].endsWith(closer)) {
						let allowed_closers = 0;

						for (const char of url[0]) {
						    if (char === opener) {
						    	allowed_closers++;
						    } else if (char === closer) {
						    	allowed_closers--;
						    }
						}

						if (allowed_closers < 0) {
							url[0] = url[0].slice(0, -1);
							regex.lastIndex--;
						} else {
							found_trailing_bracket_quote = false;
						}

						break;
					}
				}
			} while (found_trailing_bracket_quote);

			// Which BBC do we want to use?
			const bbc_tag = regex_name.endsWith('email') ? 'email' : (url[0].startsWith(smf_scripturl) ? 'iurl' : 'url');

			// Set start of selection to the start of the URL.
			rangeHelper.selectOuterText(str.length - url.index, 0);

			// Set end of selection to the end of the URL.
			let selectedRange = rangeHelper.selectedRange();
			selectedRange.setEnd(rangeHelper.parentNode(), selectedRange.endOffset - (str.length - url.index - url[0].length));

			// Prepend '//' to naked domains.
			if (regex_name.endsWith('naked_domain')) {
				url[0] = '//' + url[0];
			}

			// Insert the URL.
			editor.wysiwygEditorInsertHtml('<a data-type="' + bbc_tag + '" href="' + url[0] + '">' + url[0] + '</a>');
		}

		// Helper for this.signalKeydownEvent.
		function removeAutolink(rangeHelper, caretPos) {
			const container = rangeHelper.parentNode().parentNode;

			if (
				container.nodeType === Node.ELEMENT_NODE
				&& container.nodeName === 'A'
				&& container.href.replace(/\/$/, '').replace(/^mailto:/, '') === container.textContent.replace(/\/$/, '')
			) {
				const url = container.textContent;
				const containerParent = container.parentNode;

				if (caretPos === url.length) {
					container.replaceWith(url);
					containerParent.normalize();

					rangeHelper.selectOuterText(0, caretPos);
					let selectedRange = rangeHelper.selectedRange();
					selectedRange.setStart(rangeHelper.parentNode(), selectedRange.endOffset);
				}
			}
		}
	};
})(sceditor);