/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2024 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

(function ($) {
	var extensionMethods = {
		insertQuoteFast: function (messageid)
		{
			var self = this;
			getXMLDocument(
				smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + messageid + ';xml',
				function(XMLDoc)
				{
					var text = '';

					for (var i = 0, n = XMLDoc.getElementsByTagName('quote')[0].childNodes.length; i < n; i++)
						text += XMLDoc.getElementsByTagName('quote')[0].childNodes[i].nodeValue;
					self.insert(text);

					// Manually move cursor to after the quote.
					var
						rangeHelper = self.getRangeHelper(),
						parent = rangeHelper.parentNode();
					if (parent && parent.nodeName === 'BLOCKQUOTE')
					{
						var range = rangeHelper.selectedRange();
						range.setStartAfter(parent);
						rangeHelper.selectRange(range);
					}

					ajax_indicator(false);
				}
			);
		},
		InsertText: function (text, bClear) {
			if (bClear)
				this.val('');

			this.insert(text);
		},
		getText: function (filter) {
			var current_value = '';

			if (this.inSourceMode())
				current_value = this.getSourceEditorValue(false);
			else
				current_value = this.getWysiwygEditorValue(filter);

			return current_value;
		},
		appendEmoticon: function (code, emoticon, description) {
			if (emoticon == '')
				line.append($('<br>'));
			else
				line.append($('<img>')
					.attr({
						src: emoticon,
						alt: code,
						title: description,
					})
					.click(function (e) {
						var	start = '', end = '';

						if (base.opts.emoticonsCompat)
						{
							start = '<span> ';
							end = ' </span>';
						}

						if (base.inSourceMode())
							base.sourceEditorInsertText(' ' + $(this).attr('alt') + ' ');
						else
							base.wysiwygEditorInsertHtml(start + '<img src="' + $(this).attr("src") + '" data-sceditor-emoticon="' + $(this).attr('alt') + '">' + end);

						e.preventDefault();
					})
				);
		},
		storeLastState: function (){
			this.wasSource = this.inSourceMode();
		},
		setTextMode: function () {
			if (!this.inSourceMode())
				this.toggleSourceMode();
		},
		createPermanentDropDown: function () {
			var emoticons = $.extend({}, this.opts.emoticons.dropdown);
			var popup_exists = false;
			content = $('<div class="sceditor-insertemoticon">');
			line = $('<div>');
			base = this;

			for (smiley_popup in this.opts.emoticons.popup)
			{
				popup_exists = true;
				break;
			}
			if (popup_exists)
			{
				base.opts.emoticons.more = base.opts.emoticons.popup;
				moreButton = $('<div class="sceditor-more-button sceditor-more button">').text(this._('More')).click(function () {
					if ($(".sceditor-smileyPopup").length > 0)
					{
						$(".sceditor-smileyPopup").fadeIn('fast');
					}
					else
					{
						var emoticons = $.extend({}, base.opts.emoticons.popup);
						var popup_position;
						var titlebar = $('<div class="catbg sceditor-popup-grip"/>');
						popupContent = $('<div id="sceditor-popup"/>');
						allowHide = true;
						line = $('<div id="sceditor-popup-smiley"/>');
						adjheight = 0;

						popupContent.append(titlebar);
						closeButton = $('<span class="button">').text(base._('Close')).click(function () {
							$(".sceditor-smileyPopup").fadeOut('fast');
						});
						$(document).mouseup(function (e) {
							if (allowHide && !popupContent.is(e.target) && popupContent.has(e.target).length === 0)
								$(smileyPopup).fadeOut('fast');
						}).keyup(function (e) {
							if (e.keyCode === 27)
								$(smileyPopup).fadeOut('fast');
						});

						$.each(emoticons, function( code, emoticon ) {
							base.appendEmoticon(code, emoticon, base.opts.emoticonsDescriptions[code]);
						});

						if (line.children().length > 0)
							popupContent.append(line);
						if (typeof closeButton !== "undefined")
							popupContent.append(closeButton);

						// IE needs unselectable attr to stop it from unselecting the text in the editor.
						// The editor can cope if IE does unselect the text it's just not nice.
						if (base.ieUnselectable !== false) {
							content = $(content);
							content.find(':not(input,textarea)').filter(function () { return this.nodeType===1; }).attr('unselectable', 'on');
						}

						dropdownIgnoreLastClick = true;
						adjheight = closeButton.height() + titlebar.height();
						$dropdown = $('<div class="centerbox sceditor-smileyPopup">')
							.append(popupContent)
							.appendTo($('.sceditor-container'));

						$('.sceditor-smileyPopup').animaDrag({
							speed: 150,
							interval: 120,
							during: function (e) {
								$(this).height(this.startheight);
								$(this).width(this.startwidth);
							},
							before: function (e) {
								this.startheight = $(this).innerHeight();
								this.startwidth = $(this).innerWidth();
							},
							grip: '.sceditor-popup-grip'
						});
						// stop clicks within the dropdown from being handled
						$dropdown.click(function (e) {
							e.stopPropagation();
						});
					}
				});
			}
			$.each(emoticons, function( code, emoticon ) {
				base.appendEmoticon(code, emoticon, base.opts.emoticonsDescriptions[code]);
			});
			if (line.children().length > 0)
				content.append(line);
			$(".sceditor-toolbar").append(content);
			if (typeof moreButton !== "undefined")
				content.append($('<center/>').append(moreButton));
		}
	};

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

			// Wrap the URL in BBC tags.
			editor.insert('[' + bbc_tag + '="' + url[0] + '"]', '[/' + bbc_tag + ']');
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

	var createFn = sceditor.create;
	var isPatched = false;

	sceditor.create = function (textarea, options) {
		// Call the original create function
		createFn(textarea, options);

		// Constructor isn't exposed so get reference to it when
		// creating the first instance and extend it then
		var instance = sceditor.instance(textarea);
		if (!isPatched && instance) {
			sceditor.utils.extend(instance.constructor.prototype, extensionMethods);
			window.addEventListener('beforeunload', instance.updateOriginal, false);

			/*
			 * Stop SCEditor from resizing the entire container. Long
			 * toolbars and tons of smilies play havoc with this.
			 * Only resize the text areas instead.
			 */
			document.querySelector(".sceditor-container").removeAttribute("style");
			document.querySelector(".sceditor-container textarea").style.height = options.height;
			document.querySelector(".sceditor-container textarea").style.flexBasis = options.height;

			isPatched = true;
		}

		// Fix for minor bug where the toolbar buttons wouldn't initially be active.
		if (options.autofocus) {
			const rangeHelper = instance.getRangeHelper();
			rangeHelper.saveRange();
			instance.blur();
			instance.focus();
			rangeHelper.restoreRange();
		}
	};
})(jQuery);

sceditor.command.set(
	'pre', {
		txtExec: ["[pre]", "[/pre]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<pre>', '</pre>');
		}
	}
);
sceditor.command.set(
	'link', {
		exec: function (caller) {
			var editor = this;

			editor.commands.link._dropDown(editor, caller, function (url, text) {
				if (!editor.getRangeHelper().selectedHtml() || text) {
					text = text || url;

					editor.wysiwygEditorInsertHtml(
						'<a data-type="url" href="' +
						sceditor.escapeEntities(url) + '">' +
						sceditor.escapeEntities(text, true) + '</a>'
					);
				} else {
					// Can't just use `editor.execCommand('createlink', url)`
					// because we need to set a custom attribute.
					editor.wysiwygEditorInsertHtml(
						'<a data-type="url" href="' +
						sceditor.escapeEntities(url) + '">', '</a>'
					);
				}
			});
		}
	}
);
sceditor.command.set(
	'unlink', {
		state: function () {
			if (this.inSourceMode()) {
				return 0;
			}

			const rangeHelper = this.getRangeHelper()
			const container = rangeHelper.parentNode().parentNode;

			if (container.nodeType === Node.ELEMENT_NODE && container.nodeName === 'SPAN' && container.classList.contains('nolink')) {
				return 1;
			}

			if (container.nodeType !== Node.ELEMENT_NODE || container.nodeName !== 'A') {
				return -1;
			}

			return 0;
		},
		exec: function () {
			const rangeHelper = this.getRangeHelper()
			const container = rangeHelper.parentNode().parentNode;

			if (
				container.nodeType === Node.ELEMENT_NODE
				&& container.nodeName === 'A'
			) {
				const containerParent = container.parentNode;
				const caretPos = rangeHelper.selectedRange().startOffset;

				const url = container.textContent;
				container.replaceWith(url);
				containerParent.normalize();

				rangeHelper.selectOuterText(0, url.length);
				this.insert('[nolink]', '[/nolink]');
			} else if (
				container.nodeType === Node.ELEMENT_NODE
				&& container.nodeName === 'SPAN'
				&& container.classList.contains('nolink')
			) {
				const containerParent = container.parentNode;
				const caretPos = rangeHelper.selectedRange().startOffset;

				const url = container.textContent;
				container.replaceWith(url);
				containerParent.normalize();

				const bbc_tag = autolinker_regexes.get('email').test(url) ? 'email' : (url.startsWith(smf_scripturl) ? 'iurl' : 'url');

				rangeHelper.selectOuterText(0, url.length);

				if (autolinker_regexes.get('naked_domain').test(url)) {
					this.insert('[' + bbc_tag + '="//' + url + '"]', '[/' + bbc_tag + ']');
				} else {
					this.insert('[' + bbc_tag + ']', '[/' + bbc_tag + ']');
				}
			}
		},
		txtExec: function () {
			let caretPos = this.sourceEditorCaret().start;
			const val = this.val();
			const valBefore = val.substring(0, caretPos);
			const valAfter = val.substring(caretPos);

			const urlBbcBefore = new RegExp('\\[(i?url|email)([^\\]]*)\\]([^\\[\\]\\s]*)$', 'i');
			const urlBbcAfter = new RegExp('^([^\\[\\]\\s]*)\\[\\/(i?url|email)\\]', 'i');
			const nolinkBbcBefore = new RegExp('\\[nolink\\]([^\\[]|\\[(?!/?nolink))*$', 'im');
			const nolinkBbcAfter = new RegExp('^([^\\]]|(?<!nolink)\\])*\\[\\/nolink\\]', 'im');

			if (valBefore.match(nolinkBbcBefore) && valAfter.match(nolinkBbcAfter)) {
				const before = nolinkBbcBefore.exec(valBefore);
				const after = nolinkBbcAfter.exec(valAfter);

				const beforePos = caretPos - before[0].length;

				let possibleUrl = before[0] + after[0];
				possibleUrl = possibleUrl.substring(8, possibleUrl.length - 9);

				for (const [name, regex] of autolinker_regexes.entries()) {
					if (name.startsWith('keypress_') || name.startsWith('paste_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					let found = false;
					let url = regex.exec(possibleUrl);

					if (url !== null) {
						this.val(val.replace(before[0] + after[0], url[0]));

						this.sourceEditorCaret({start: caretPos - before[0].length, end: caretPos - before[0].length + url[0].length});

						const bbc_tag = name.endsWith('email') ? 'email' : possibleUrl.startsWith(smf_scripturl) ? 'iurl' : 'url';

						if (name.endsWith('naked_domain')) {
							this.insert('[' + bbc_tag + '="//' + url[0] + '"]', '[/' + bbc_tag + ']');
						} else {
							this.insert('[' + bbc_tag + ']', '[/' + bbc_tag + ']');
						}

						break;
					}
				}
			} else if (valBefore.match(urlBbcBefore) && valAfter.match(urlBbcAfter)) {
				const before = urlBbcBefore.exec(valBefore);
				caretPos = caretPos - (before[1].length + before[2].length - 6);

				this.val(valBefore.replace(urlBbcBefore, '[nolink]$3') + valAfter.replace(urlBbcAfter, '$1[/nolink]'));

				this.sourceEditorCaret({start: caretPos, end: caretPos});
			} else {
				for (const [name, regex] of autolinker_regexes.entries()) {
					if (name.startsWith('keypress_') || name.startsWith('paste_')) {
						continue;
					}

					// Ensure the search always starts from the beginning.
					regex.lastIndex = 0;

					let found = false;
					let url = regex.exec(val);

					while (url !== null) {
						if (regex.lastIndex < caretPos) {
							url = regex.exec(val);
						} else if (url.index > caretPos) {
							break;
						} else {
							found = true;
							break;
						}
					}

					if (found) {
						// Wrap in nolink tags.
						this.sourceEditorCaret({start: url.index, end: regex.lastIndex});
						this.insert('[nolink]', '[/nolink]');

						// Bump the caret along by the length of the opening tag.
						this.sourceEditorCaret({start: caretPos + 8, end: caretPos + 8});

						// Don't try any more regular expressions.
						break;
					}
				}
			}
		},
	}
);

sceditor.command.set(
	'bulletlist', {
		txtExec: function (caller, selected) {
			if (selected)
				this.insertText(
					'[list]\n[li]' +
					selected.split(/\r?\n/).join('[/li]\n[li]') +
					'[/li]\n[/list]'
				);
			else
				this.insertText('[list]\n[li]', '[/li]\n[li][/li]\n[/list]');
		}
	}
);

sceditor.command.set(
	'orderedlist', {
		txtExec: function (caller, selected) {
			if (selected)
				this.insertText(
					'[list type=decimal]\n[li]' +
					selected.split(/\r?\n/).join('[/li]\n[li]') +
					'[/li]\n[/list]'
				);
			else
				this.insertText('[list type=decimal]\n[li]', '[/li]\n[li][/li]\n[/list]');
		}
	}
);

sceditor.command.set(
	'table', {
		txtExec: ["[table]\n[tr]\n[td]", "[/td]\n[/tr]\n[/table]"]
	}
);

sceditor.command.set(
	'floatleft', {
		txtExec: ["[float=left max=45%]", "[/float]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<div class="floatleft">', '</div>');
		}
	}
);

sceditor.command.set(
	'floatright', {
		txtExec: ["[float=right max=45%]", "[/float]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<div class="floatright">', '</div>');
		}
	}
);

sceditor.command.set(
	'maximize', {
		shortcut: ''
	}
);

sceditor.command.set(
	'source', {
		shortcut: ''
	}
);

sceditor.command.set(
	'youtube', {
		exec: function (caller) {
			var editor = this;

			editor.commands.youtube._dropDown(editor, caller, function (id, time) {
				editor.wysiwygEditorInsertHtml('<div class="videocontainer"><div><iframe frameborder="0" allowfullscreen src="https://www.youtube-nocookie.com/embed/' + id + '?wmode=opaque&start=' + time + '" data-youtube-id="' + id + '" loading="lazy"></iframe></div></div>');
			});
		}
	}
);

sceditor.command.set(
	'email', {
		exec: function (caller)
		{
			var editor = this;

			editor.commands.email._dropDown(
				editor,
				caller,
				function (email, text)
				{
					if (!editor.getRangeHelper().selectedHtml() || text)
						editor.wysiwygEditorInsertHtml(
							'<a data-type="email" href="' +
							'mailto:' + sceditor.escapeEntities(email) + '">' +
								sceditor.escapeEntities(text || email) +
							'</a>'
						);
					else
						// Can't just use `editor.execCommand('createlink', email)`
						// because we need to set a custom attribute.
						editor.wysiwygEditorInsertHtml(
							'<a data-type="email" href="mailto:' +
							sceditor.escapeEntities(email) + '">', '</a>'
						);
				}
			);
		},
	}
);

sceditor.command.set(
	'image', {
		exec: function (caller)
		{
			var editor = this;

			editor.commands.image._dropDown(
				editor,
				caller,
				'',
				function (url, width, height)
				{
					var attrs = ['src="' + sceditor.escapeEntities(url) + '"'];

					if (width)
						attrs.push('width="' + sceditor.escapeEntities(width, true) + '"');

					if (height)
						attrs.push('height="' + sceditor.escapeEntities(height, true) + '"');

					editor.wysiwygEditorInsertHtml(
						'<img ' + attrs.join(' ') + '>'
					);
				}
			);
		}
	}
);

sceditor.formats.bbcode.set(
	'abbr', {
		tags: {
			abbr: {
				title: null
			}
		},
		format: function (element, content) {
			return '[abbr=' + $(element).attr('title') + ']' + content + '[/abbr]';
		},
		html: function (element, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;

			return '<abbr title="' + attrs.defaultattr + '">' + content + '</abbr>';
		}
	}
);

sceditor.formats.bbcode.set(
	'list', {
		breakStart: true,
		isInline: false,
		// allowedChildren: ['*', 'li'], // Disabled for SCE 2.1.2 because it triggers a bug with inserting extra line breaks
		html: function (element, attrs, content) {
			var style = '';
			var code = 'ul';
			var olTypes = new Array('decimal', 'decimal-leading-zero', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha', 'lower-greek', 'upper-greek', 'lower-latin', 'upper-latin', 'hebrew', 'armenian', 'georgian', 'cjk-ideographic', 'hiragana', 'katakana', 'hiragana-iroha', 'katakana-iroha');

			if (attrs.type) {
				style = ' style="list-style-type: ' + attrs.type + '"';

				if (olTypes.indexOf(attrs.type) > -1)
					code = 'ol';
			}
			else
				style = ' style="list-style-type: disc"';

			return '<' + code + style + '>' + content + '</' + code + '>';
		}
	}
);

sceditor.formats.bbcode.set(
	'ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		isInline: false,
		html: '<ul>{0}</ul>',
		format: function (element, content) {
			if ($(element).css('list-style-type') == 'disc')
				return '[list]' + content + '[/list]';
			else
				return '[list type=' + $(element).css('list-style-type') + ']' + content + '[/list]';
		}
	}
);

sceditor.formats.bbcode.set(
	'ol', {
		tags: {
			ol: null
		},
		breakStart: true,
		isInline: false,
		html: '<ol>{0}</ol>',
		format: function (element, content) {
			if ($(element).css('list-style-type') == 'none')
				return '[list type=decimal]' + content + '[/list]';
			else
				return '[list type=' + $(element).css('list-style-type') + ']' + content + '[/list]';
		}
	}
);

sceditor.formats.bbcode.set(
	'li', {
		tags: {
			li: null
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		html: '<li data-bbc-tag="li">{0}</li>',
		format: function (element, content) {
			var	element = $(element),
				token = 'li',
				allowedTokens = ['li', '*', '@', '+', 'x', 'o', 'O', '0'];

			if (element.attr('data-bbc-tag') && allowedTokens.indexOf(element.attr('data-bbc-tag') > -1))
				token = element.attr('data-bbc-tag');

			return '[' + token + ']' + content + (token === 'li' ? '[/' + token + ']' : '');
		},
	}
);
sceditor.formats.bbcode.set(
	'*', {
		tags: {
			li: {
				'data-bbc-tag': ['*']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="disc" data-bbc-tag="*">{0}</li>',
		format: '[*]{0}',
	}
);
sceditor.formats.bbcode.set(
	'@', {
		tags: {
			li: {
				'data-bbc-tag': ['@']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="disc" data-bbc-tag="@">{0}</li>',
		format: '[@]{0}',
	}
);
sceditor.formats.bbcode.set(
	'+', {
		tags: {
			li: {
				'data-bbc-tag': ['+']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="square" data-bbc-tag="+">{0}</li>',
		format: '[+]{0}',
	}
);
sceditor.formats.bbcode.set(
	'x', {
		tags: {
			li: {
				'data-bbc-tag': ['x']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="square" data-bbc-tag="x">{0}</li>',
		format: '[x]{0}',
	}
);
sceditor.formats.bbcode.set(
	'o', {
		tags: {
			li: {
				'data-bbc-tag': ['o']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="circle" data-bbc-tag="o">{0}</li>',
		format: '[o]{0}',
	}
);
sceditor.formats.bbcode.set(
	'O', {
		tags: {
			li: {
				'data-bbc-tag': ['O']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="circle" data-bbc-tag="O">{0}</li>',
		format: '[o]{0}',
	}
);
sceditor.formats.bbcode.set(
	'0', {
		tags: {
			li: {
				'data-bbc-tag': ['0']
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		excludeClosing: true,
		html: '<li type="circle" data-bbc-tag="0">{0}</li>',
		format: '[o]{0}',
	}
);

sceditor.formats.bbcode.set(
	'img', {
		tags: {
			img: {
				src: null
			}
		},
		allowsEmpty: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content) {
			var	element = $(element),
				attribs = '',
				style = function (name) {
					return element.style ? element.style[name] : null;
				};

			// check if this is an emoticon image
			if (typeof element.attr('data-sceditor-emoticon') !== "undefined")
				return content;

			// only add width and height if one is specified
			if (element.attr('width') || style('width'))
				attribs += " width=" + element.attr('width');
			if (element.attr('height') || style('height'))
				attribs += " height=" + element.attr('height');
			if (element.attr('alt'))
				attribs += " alt=" + element.attr('alt');

			// Is this an attachment?
			if (element.attr('data-attachment'))
			{
				attribs = " id=" + element.attr('data-attachment') + attribs;
				if (element.attr('data-type'))
					attribs += " type=" + element.attr('data-type');

				return '[attach' + attribs + ']' + element.attr('title') + '[/attach]';
			}
			else if (element.attr('title'))
				attribs += " title=" + element.attr('title');

			return '[img' + attribs + ']' + element.attr('src') + '[/img]';
		},
		html: function (token, attrs, content) {
			var	parts,
				attribs = '';

			// handle [img width=340 height=240]url[/img]
			if (typeof attrs.width !== "undefined")
				attribs += ' width="' + attrs.width + '"';
			if (typeof attrs.height !== "undefined")
				attribs += ' height="' + attrs.height + '"';
			if (typeof attrs.alt !== "undefined")
				attribs += ' alt="' + attrs.alt + '"';
			if (typeof attrs.title !== "undefined")
				attribs += ' title="' + attrs.title + '"';

			return '<img' + attribs + ' src="' + content + '">';
		}
	}
);

sceditor.formats.bbcode.set(
	'attach', {
		tags: {
			img: {
				'data-attachment': null
			},
			a: {
				'data-attachment': null
			}
		},
		allowsEmpty: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content) {
			var	element = $(element),
				attribs = '',
				attach_type,
				style = function (name) {
					return element.style ? element.style[name] : null;
				},
				index;

			attribs += " id=" + element.attr('data-attachment');
			if (element.attr('width') || style('width'))
				attribs += " width=" + element.attr('width');
			if (element.attr('height') || style('height'))
				attribs += " height=" + element.attr('height');
			if (element.attr('alt'))
				attribs += " alt=" + element.attr('alt');

			if (typeof current_attachments !== "undefined")
				for (index = 0; index < current_attachments.length; ++index) {
					if (current_attachments[index]['attachID'] == element.attr('data-attachment')) {
						attach_type = current_attachments[index]['type'];
						break;
					}
				}

			if (element.attr('title') && attach_type.indexOf("image") === 0)
				content = element.attr('title');

			return '[attach' + attribs + ']' + content + '[/attach]';
		},
		html: function (token, attrs, content) {
			var parts,
				attribs = '',
				attach_type,
				index;

			// Handles SMF 2.1 final format
			if (typeof attrs.id !== "undefined")
				var id = attrs.id;
			// Handles format from SMF 2.1 betas
			else {
				var id = content;
				if (typeof attrs.name !== "undefined")
					content = attrs.name;
			}

			if (typeof current_attachments !== "undefined")
				for (index = 0; index < current_attachments.length; ++index) {
					if (current_attachments[index]['attachID'] == id) {
						attach_type = current_attachments[index]['type'];
						break;
					}
				}

			// If id is not an integer, bail out
			if (!$.isNumeric(id) || Math.floor(id) != +id || +id <= 0) {

				attribs += ' id=' + id;
				if (typeof attrs.width !== "undefined")
					attribs += ' width=' + attrs.width;
				if (typeof attrs.height !== "undefined")
					attribs += ' height=' + attrs.height;
				if (typeof attrs.alt !== "undefined")
					attribs += ' alt=' + attrs.alt;

				return '[attach' + attribs + ']' + content + '[/attach]';
			}

			attribs += ' data-type="attachment" data-attachment="' + id + '"';
			if (typeof attrs.alt !== "undefined")
				attribs += ' alt="' + attrs.alt + '"';

			// Is this an image?
			if ((typeof attach_type !== "undefined" && attach_type.indexOf("image") === 0)) {
				attribs += ' title="' + content + '"';
				if (typeof attrs.width !== "undefined")
					attribs += ' width="' + attrs.width + '"';
				if (typeof attrs.height !== "undefined")
					attribs += ' height="' + attrs.height + '"';

				var contentUrl = smf_scripturl +'?action=dlattach;attach='+ id + ';preview;image';
				contentIMG = new Image();
					contentIMG.src = contentUrl;
			}

			// If not an image, show a boring ol' link
			if (typeof contentUrl === "undefined" || contentIMG.getAttribute('width') == 0)
				return '<a href="' + smf_scripturl + '?action=dlattach;attach=' + id + ';file"' + attribs + '>' + content + '</a>';
			// Show our purdy li'l picture
			else
				return '<img' + attribs + ' src="' + contentUrl + '">';
		}
	}
);

sceditor.formats.bbcode.set(
	'email', {
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		tags: {
			a: {
				'data-type': ['email']
			}
		},
		format: function (element, content)
		{
			if (decodeURI(element.href.substr(7)) === content) {
				return '[email]' + content + '[/email]';
			}

			return '[email=' + element.href.substr(7) + ']' + content + '[/email]';
		},
		html: function (token, attrs, content)
		{
			return '<a data-type="email" href="mailto:' + sceditor.escapeEntities(attrs.defaultattr || content, true) + '">' + content + '</a>';
		}
	}
);

sceditor.formats.bbcode.set(
	'url', {
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.always,
		format(element, content)
		{
			if (element.hasAttribute('data-type') && element.getAttribute('data-type') != 'url')
				return content;

			if (decodeURI(element.href).replace(/\/$/, '') === content.replace(/\/$/, '')) {
				return '[url]' + content + '[/url]';
			}

			return '[url=' + decodeURI(element.href) + ']' + content + '[/url]';
		},
		html: function (token, attrs, content)
		{
			return '<a data-type="url" href="' + encodeURI(attrs.defaultattr || content) + '">' + content + '</a>';
		}
	}
);

sceditor.formats.bbcode.set(
	'iurl', {
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.always,
		tags: {
			a: {
				'data-type': ['iurl']
			}
		},
		format: function (element, content)
		{
			if (decodeURI(element.href).replace(/\/$/, '') === content.replace(/\/$/, '')) {
				return '[iurl]' + content + '[/iurl]';
			}

			return '[iurl=' + decodeURI(element.href) + ']' + content + '[/iurl]';
		},
		html: function (token, attrs, content)
		{
			return '<a data-type="iurl" href="' + encodeURI(attrs.defaultattr || content) + '">' + content + '</a>';
		}
	}
);

// This pseudo-BBCode exists only to help the autolinker plugin.
sceditor.formats.bbcode.set(
	'nolink', {
		tags: {
			span: {
				'class': 'nolink'
			},
		},
		format: function (element, content) {
			return '[nolink]' + content + '[/nolink]';
		},
		html: function (token, attrs, content)
		{
			return '<span class="nolink">' + content + '</span>';
		}
	}
);

sceditor.formats.bbcode.set(
	'pre', {
		tags: {
			pre: null
		},
		isBlock: true,
		format: "[pre]{0}[/pre]",
		html: "<pre>{0}</pre>\n"
	}
);

sceditor.formats.bbcode.set(
	'php', {
		isInline: false,
		format: "[php]{0}[/php]",
		html: '<code class="php">{0}</code>'
	}
);

sceditor.formats.bbcode.set(
	'code', {
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content) {
			if ($(element).hasClass('php'))
				return '[php]' + content.replace('&#91;', '[') + '[/php]';

			var
				dom = sceditor.dom,
				attr = dom.attr,
				title = attr(element, 'data-title'),
				from = title ?' =' + title : '';

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		html: function (element, attrs, content) {
			var from = attrs.defaultattr ? ' data-title="' + attrs.defaultattr + '"'  : '';

			return '<code data-name="' + this.opts.txtVars.code + '"' + from + '>' + content.replace('[', '&#91;') + '</code>'
		}
	}
);

sceditor.formats.bbcode.set(
	'quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		breakBefore: false,
		isInline: false,
		format: function (element, content)
		{
			var attrs = '';
			var author = element.getAttribute('data-author');
			var date = element.getAttribute('data-date');
			var link = element.getAttribute('data-link');

			// The <cite> contains only the graphic for the quote, so we can skip it
			if (element.tagName === 'CITE')
				return '';

			if (author)
				attrs += ' author=' + author.php_unhtmlspecialchars();
			if (link)
				attrs += ' link=' + link;
			if (date)
				attrs += ' date=' + date;

			return '[quote' + attrs + ']' + content + '[/quote]';
		},
		html: function (element, attrs, content)
		{
			var attr_author = '', author = '';
			var attr_date = '', sDate = '';
			var attr_link = '', link = '';

			if (attrs.author || attrs.defaultattr)
			{
				attr_author = attrs.author || attrs.defaultattr;
				author = bbc_quote_from + ': ' + attr_author;
			}

			if (attrs.link)
			{
				attr_link = attrs.link;
				link = attr_link.substr(0, 7) == 'http://' ? attr_link : smf_prepareScriptUrl(smf_scripturl) + attr_link;
				author = '<a href="' + link + '">' + (author || bbc_quote_from + ': ' + link) + '</a>';
			}

			if (attrs.date)
			{
				attr_date = attrs.date;
				sDate = '<date timestamp="' + attr_date + '">' + new Date(attr_date * 1000).toLocaleString() + '</date>';

				if (author !== '')
					author += ' ' + bbc_search_on;
			}

			return '<blockquote data-author="' + attr_author + '" data-date="' + attr_date + '" data-link="' + attr_link + '"><cite>' + (author || bbc_quote) + ' ' + sDate + '</cite>' + content + '</blockquote>';
		}
	}
);

sceditor.formats.bbcode.set(
	'font', {
		format: function (element, content) {
			var element = $(element);
			var font;

			// Get the raw font value from the DOM
			if (!element.is('font') || !(font = element.attr('face'))) {
				font = element.css('font-family');
			}

			// Strip all quotes
			font = font.replace(/['"]/g, '');

			return '[font=' + font + ']' + content + '[/font]';
		}
	}
);

sceditor.formats.bbcode.set(
	'member', {
		isInline: true,
		tags: {
			a: {
				'data-mention': null
			}
		},
		format: function (element, content) {
			return '[member='+ $(element).attr('data-mention') +']'+ content.replace('@','') +'[/member]';
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a href="' + smf_scripturl +'?action=profile;u='+ attrs.defaultattr + '" class="mention" data-type="mention" data-mention="'+ attrs.defaultattr + '">@'+ content.replace('@', '') +'</a>';
		}
	}
);

sceditor.formats.bbcode.set(
	'float', {
		tags: {
			div: {
				"class": ["floatleft", "floatright"],
			},
		},
		isInline: false,
		skipLastLineBreak: true,
		format: function (element, content) {
			var element = $(element);
			if (!element.css('float'))
				return content;

			side = (element[0].className == 'floatleft' ? 'left' : 'right');
			max = ' max=' + (element.css('max-width') != "none" ? element.css('max-width') : '45%');

			return '[float=' + side + max + ']' + content + '[/float]';
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined")
				return content;

			floatclass = attrs.defaultattr.indexOf('left') == 0 ? 'floatleft' : 'floatright';
			style = typeof attrs.max !== "undefined" ? ' style="max-width:' + attrs.max + (+attrs.max === parseInt(attrs.max) ? 'px' : '') + ';"' : '';

			return '<div class="' + floatclass + '"' + style + '>' + content + '</div>';
		}
	}
);

sceditor.formats.bbcode.set(
	'youtube', {
		allowsEmpty: true,
		tags: {
			div: {
				class: 'videocontainer'
			}
		},
		isInline: false,
		skipLastLineBreak: true,
		format: function (element, content) {
			youtube_id = $(element).find('iframe').data('youtube-id');

			if (typeof youtube_id !== "undefined")
				return '[youtube]' + youtube_id + '[/youtube]';
			else
				return content;
		},
		html: '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube-nocookie.com/embed/{0}?wmode=opaque" data-youtube-id="{0}" loading="lazy" allowfullscreen></iframe></div></div>'
	}
);