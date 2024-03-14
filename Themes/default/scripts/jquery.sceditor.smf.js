/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 3.0 Alpha 2
 */

(sceditor => {
	sceditor.plugins.smf = function ()
	{
		let editor;
		let opts;
		let line;

		const appendEmoticon = (code, {newrow, url, tooltip}) => {
			if (newrow)
				line.appendChild(document.createElement('br'));

			const i = document.createElement("img");
			i.src = opts.emoticonsRoot + url;
			i.alt = code;
			i.title = tooltip;
			i.addEventListener('click', function (e)
			{
				if (editor.inSourceMode())
					editor.insertText(' ' + this.alt + ' ');
				else
					editor.wysiwygEditorInsertHtml(' <img src="' + this.src + '" data-sceditor-emoticon="' + this.alt + '"> ');

				e.preventDefault();
			});
			line.appendChild(i);
		};

		const createPopup = el => {
			const t = document.createElement("div");
			const cover = document.createElement('div');
			const root = document.createElement('div');

			const hide = () => {
				cover.classList.remove('show');
				document.removeEventListener('keydown', esc);
			};

			var esc = ({keyCode}) => {
				if (keyCode === 27)
					hide();
			};

			const a = document.createElement('button');

			root.appendChild(a);
			cover.appendChild(root);
			document.body.appendChild(cover);
			root.id = 'popup-container';
			cover.id = 'popup';
			a.id = 'close';
			cover.addEventListener('click', ({target}) => {
				if (target.id === 'popup')
					hide();
			});
			a.addEventListener('click', hide);
			document.addEventListener('keydown', esc);
			root.appendChild(el);
			root.appendChild(a);
			cover.classList.add('show');
			editor.hidePopup = hide;
		};

		const ev = ({children, nextSibling}, col, row) => {
			for (let i = 1; i <= 144; i++)
				children[i - 1].className = Math.ceil(i / 12) <= col && (i % 12 || 12) <= row ? 'highlight2' : 'windowbg';

			nextSibling.textContent = col + 'x' + row;
		};

		const tbl = callback => {
			const content = document.createElement('div');
			content.className = 'sceditor-insert-table';
			const div = document.createElement('div');
			div.className = 'sceditor-insert-table-grid';
			div.addEventListener('mouseleave', ev.bind(null, div, 0, 0));
			const div2 = document.createElement('div');
			div2.className = 'largetext';
			div2.textContent = '0x0';

			for (let i = 1; i <= 144; i++)
			{
				const row = i % 12 || 12;
				const col = Math.ceil(i / 12);
				const span = document.createElement('span');
				span.className = 'windowbg';
				span.addEventListener('mouseenter', ev.bind(null, div, col, row));
				span.addEventListener('click', function (col, row) {
					callback(col, row);
					editor.hidePopup();
					editor.focus();
				}.bind(null, col, row));
				div.appendChild(span);
			}
			content.append(div, div2);
			createPopup(content);
		};

		this.init = function ()
		{
			editor = this;
			opts = editor.opts;

			if (opts.emoticonsEnabled)
			{
				const emoticons = opts.emoticons;
				content = opts.smileyContainer;
				if (emoticons.dropdown && content)
				{
					line = document.createElement('div');
					sceditor.utils.each(emoticons.dropdown, appendEmoticon);
					content.appendChild(line);
				}

				if (emoticons.more)
				{
					const moreButton = document.createElement('button');
					moreButton.type = 'button';
					moreButton.className = 'button';
					moreButton.textContent = editor._('More');
					moreButton.addEventListener('click', e => {
						line = document.createElement('div');
						sceditor.utils.each(emoticons.more, appendEmoticon);
						createPopup(line);

						e.preventDefault();
					});
					content.appendChild(moreButton);
				}
				content.className = 'sceditor-insertemoticon';
			}
			editor.commands.table = {
				state(parents, firstBlock) {
					return firstBlock && firstBlock.closest('table') ? 1 : 0;
				},
				exec() {
					tbl((cols, rows) => {
						editor.wysiwygEditorInsertHtml(
						'<table><tr><td>',
						'</td>'+ Array(cols).join('<td><br></td>') + Array(rows).join('</tr><tr>' + Array(cols+1).join('<td><br></td>')) + '</tr></table>'
						);
					});
				},
				txtExec() {
					tbl((cols, rows) => {
						editor.insertText(
						'[table]\n[tr]\n[td]',
						'[/td]'+ Array(cols).join('\n[td][/td]') + Array(rows).join('\n[/tr]\n[tr]' + Array(cols+1).join('\n[td][/td]')) + '\n[/tr]\n[/table]'
						);
					});
				},
			};

			const fn = editor.createDropDown;
			this.createDropDown = function (menuItem, name, content) {
				fn(menuItem, name, content);
				document.body.appendChild(document.querySelector('.sceditor-dropdown'));
			};
		};

		let buttons = {};

		this.signalReady = function ()
		{
			for (const group of this.opts.toolbarContainer.children[0].children) {
				for (const button of group.children) {
					const cmd = button.dataset.sceditorCommand;
					buttons[cmd] = button;

					if (this.opts.toolbar.includes(cmd + '||')) {
						button.parentNode.after(document.createElement('div'));
					}

					if (this.opts.customTextualCommands[cmd]) {
						button.firstChild.style.backgroundImage = 'url(' + smf_default_theme_url + '/images/bbc/' + this.opts.customTextualCommands[cmd].image + '.png)';
					}
				}
			}

			editor.insertQuoteFast = messageid =>
			{
				getXMLDocument(
					smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + messageid + ';xml',
					XMLDoc =>
					{
						var text = '';

						for (var i = 0, n = XMLDoc.getElementsByTagName('quote')[0].childNodes.length; i < n; i++)
							text += XMLDoc.getElementsByTagName('quote')[0].childNodes[i].nodeValue;
						editor.insert(text);

						// Manually move cursor to after the quote.
						var
							rangeHelper = editor.getRangeHelper(),
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
			};

			editor.addStyleshet = path =>
			{
				const iframe = editor.getContentAreaContainer();
				const el = iframe.contentDocument.createElement('link');
				el.type = 'text/css';
				el.href = path;

				iframe.contentDocument.head.appendChild(el);
			};

			// Copy variables from variants into ifrane.
			const iframe = editor.getContentAreaContainer();
			const el = iframe.contentDocument.createElement('style');
			el.type = 'text/css';

			for (const sheet of document.styleSheets) {
				if (sheet.href?.includes('/index_') || sheet.href?.includes('/variables')) {
					for (const rule of sheet.cssRules) {
						el.innerHTML += rule.cssText;
					}
				} else if (sheet.href?.includes('/minified_')) {
					for (const rule of sheet.cssRules) {
						if (rule.selectorText == ':root') {
							el.innerHTML += rule.cssText;
						}
					}
				}
			}

			iframe.contentDocument.head.appendChild(el);
		};
	};

	const setCustomTextualCommands = cmds => {
		for (let c in cmds) {
			const cmd = cmds[c];
			const obj = {
				tooltip: cmd.description || c
			};
			if (!sceditor.commands[c] && cmd.before) {
				obj.exec = function() {
					this.insertText(cmd.before, cmd.after || '');
				};
				obj.txtExec = [cmd.before, cmd.after || ''];
			}
			sceditor.command.set(c, obj);
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

	const createFn = sceditor.create;
	sceditor.create = (textarea, options, bbcContainer, smileyContainer) => {
		setCustomTextualCommands(options.customTextualCommands);
		options.original = textarea;

		if (typeof oQuickModify !== "undefined") {
			oQuickModify.opt.sceOptions = options;
		}

		if (typeof bbcContainer === 'string')
			options.toolbarContainer = document.getElementById(bbcContainer);

		if (typeof smileyContainer === 'string')
			options.smileyContainer = document.getElementById(smileyContainer);

		if (bbcContainer === true || !options.toolbarContainer) {
			options.toolbarContainer = document.createElement("div");
			textarea.before(options.toolbarContainer);
		} else {
			options.toolbar = '';
		}

		if (smileyContainer === true || !options.smileyContainer) {
			options.smileyContainer = document.createElement("div");
			textarea.before(options.smileyContainer);
		} else {
			options.emoticons = {};
		}

		// Fix for minor bug where the toolbar buttons wouldn't initially be active.
		if (options.autofocus) {
			const rangeHelper = instance.getRangeHelper();
			rangeHelper.saveRange();
			instance.blur();
			instance.focus();
			rangeHelper.restoreRange();
		}

		createFn(textarea, options);
	};
})(sceditor);

sceditor.command.set(
	'pre', {
		txtExec: ["[pre]", "[/pre]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<pre>', '</pre>');
		}
	}
).set(
	'link', {
		exec(caller) {
			const editor = this;

			editor.commands.link._dropDown(editor, caller, (url, text) => {
				if (!editor.getRangeHelper().selectedHtml() || text) {
					text = text || url;

					editor.wysiwygEditorInsertHtml(
						'<a data-type="url" href="' +
						sceditor.escapeEntities(url) + '">' +
						sceditor.escapeEntities(text, true) + '</a>'
					);
				} else {
					editor.wysiwygEditorInsertHtml(
						'<a data-type="url" href="' +
						sceditor.escapeEntities(url) + '">', '</a>'
					);
				}
			});
		}
	}
).set(
	'unlink', {
		state() {
			if (this.inSourceMode()) {
				return 0;
			}

			const rangeHelper = this.getRangeHelper();
			const container = rangeHelper.parentNode().parentNode;

			if (container.nodeType === Node.ELEMENT_NODE && container.nodeName === 'SPAN' && container.classList.contains('nolink')) {
				return 1;
			}

			if (container.nodeType !== Node.ELEMENT_NODE || container.nodeName !== 'A') {
				return -1;
			}

			return 0;
		},
		exec() {
			const rangeHelper = this.getRangeHelper();
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
		txtExec() {
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
).set(
	'bulletlist', {
		txtExec(caller, selected) {
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
).set(
	'orderedlist', {
		txtExec(caller, selected) {
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
).set(
	'floatleft', {
		txtExec: ["[float=left max=45%]", "[/float]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<div class="floatleft">', '</div>');
		}
	}
).set(
	'floatright', {
		txtExec: ["[float=right max=45%]", "[/float]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<div class="floatright">', '</div>');
		}
	}
).set(
	'youtube', {
		exec: function (caller) {
			var editor = this;

			editor.commands.youtube._dropDown(editor, caller, function (id, time) {
				editor.wysiwygEditorInsertHtml('<div class="videocontainer"><div><iframe frameborder="0" allowfullscreen src="https://www.youtube-nocookie.com/embed/' + id + '?wmode=opaque&start=' + time + '" data-youtube-id="' + id + '" loading="lazy"></iframe></div></div>');
			});
		}
	}
).set(
	'color', {
		_dropDown(editor, caller, callback)
		{
			const content = document.createElement('div');

			for (const [color, name] of editor.opts.colors)
			{
				const link = document.createElement('a');
				const span = document.createElement('span');
				link.setAttribute('data-color', color);
				link.textContent = name;
				span.style.backgroundColor = color;
				link.addEventListener('click', function (e) {
					callback(this.getAttribute('data-color'));
					editor.closeDropDown(true);
					e.preventDefault();
				});
				link.appendChild(span);
				content.appendChild(link);
			}

			editor.createDropDown(caller, 'color-picker', content);
		}
	}
).set(
	'size', {
		_dropDown(editor, caller, callback)
		{
			const content = document.createElement('div');

			for (let i = 1; i <= 7; i++)
			{
				const link = document.createElement('a');
				link.setAttribute('data-size', i);
				link.textContent = i;
				link.addEventListener('click', function (e) {
					callback(this.getAttribute('data-size'));
					editor.closeDropDown(true);
					e.preventDefault();
				});
				content.appendChild(link);
				link.style.fontSize = i * 6 + 'px';
			}

			editor.createDropDown(caller, 'fontsize-picker', content);
		}
	}
).set(
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
).set(
	'image', {
		exec(caller) {
			const editor = this;

			editor.commands.image._dropDown(
				editor,
				caller,
				'',
				(url, width, height) => {
					const attrs = ['src="' + sceditor.escapeEntities(url) + '"'];

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
let itemCodes = [
	['*', 'disc'],
	['@', 'disc'],
	['+', 'square'],
	['x', 'square'],
	['o', 'circle'],
	['O', 'circle'],
	['0', 'circle'],
];
for (const [code, attr] of itemCodes)
{
	sceditor.formats.bbcode.set(code, {
		tags: {
			li: {
				'data-itemcode': [code]
			}
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', '0', 'o', 'O'],
		excludeClosing: true,
		html: '<li type="' + attr + '" data-itemcode="' + code + '">{0}</li>',
		format: '[' + code + ']{0}',
	});
}
sceditor.formats.bbcode.set(
	'abbr', {
		tags: {
			abbr: {
				title: null
			}
		},
		format(element, content) {
			return '[abbr=' + element.getAttribute('title') + ']' + content + '[/abbr]';
		},
		html: '<abbr title="{defaultattr}">{0}</abbr>'
	}
).set(
	'list', {
		breakStart: true,
		isInline: false,
		// allowedChildren: ['*', 'li'], // Disabled for SCE 2.1.2 because it triggers a bug with inserting extra line breaks
		html(element, {type}, content) {
			let style = '';
			let code = 'ul';
			const olTypes = ['decimal', 'decimal-leading-zero', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha', 'lower-greek', 'upper-greek', 'lower-latin', 'upper-latin', 'hebrew', 'armenian', 'georgian', 'cjk-ideographic', 'hiragana', 'katakana', 'hiragana-iroha', 'katakana-iroha'];

			if (type) {
				style = ' style="list-style-type: ' + type + '"';

				if (olTypes.includes(type))
					code = 'ol';
			}
			else
				style = ' style="list-style-type: disc"';

			return '<' + code + style + '>' + content + '</' + code + '>';
		}
	}
).set(
	'ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		isInline: false,
		html: '<ul>{0}</ul>',
		format(element, content) {
			const type = element.getAttribute('type') || element.style.listStyleType;
			if (type == 'disc')
				return '[list]' + content + '[/list]';
			else
				return '[list type=' + type + ']' + content + '[/list]';
		}
	}
).set(
	'ol', {
		tags: {
			ol: null
		},
		breakStart: true,
		isInline: false,
		html: '<ol>{0}</ol>',
		format(element, content) {
			const type = element.getAttribute('type') || element.style.listStyleType;
			if (type == 'none')
				type = 'decimal';

			return '[list type=' + type + ']' + content + '[/list]';
		}
	}
).set(
	'li', {
		tags: {
			li: null
		},
		isInline: false,
		closedBy: ['/ul', '/ol', '/list', 'li', '*', '@', '+', 'x', 'o', 'O', '0'],
		html: '<li data-itemcode="li">{0}</li>',
		format(element, content) {
			let token = 'li';
			const tok = element.getAttribute('data-itemcode');
			const allowedTokens = ['li', '*', '@', '+', 'x', 'o', 'O', '0'];

			if (tok && allowedTokens.includes(tok))
				token = tok;

			return '[' + token + ']' + content + (token === 'li' ? '[/' + token + ']' : '');
		},
	}
).set(
	'img', {
		tags: {
			img: {
				src: null
			}
		},
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		format(element, content) {
			// check if this is an emoticon image
			if (element.hasAttribute('data-sceditor-emoticon'))
				return content;

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
			let attribs = '';
			const width = element.getAttribute('width') || element.style.width;
			const height = element.getAttribute('height') || element.style.height;

			if (width)
				attribs += " width=" + width;
			if (height)
				attribs += " height=" + height;
			if (element.alt)
				attribs += " alt=" + element.alt;
			if (element.title)
				attribs += " title=" + element.title;

			return '[img' + attribs + ']' + element.src + '[/img]';
		},
		html(token, {width, height, alt, title}, content) {
			let parts;
			let attribs = '';

			// handle [img width=340 height=240]url[/img]
			if (typeof width !== "undefined")
				attribs += ' width="' + width + '"';
			if (typeof height !== "undefined")
				attribs += ' height="' + height + '"';
			if (typeof alt !== "undefined")
				attribs += ' alt="' + alt + '"';
			if (typeof title !== "undefined")
				attribs += ' title="' + title + '"';

			return '<img' + attribs + ' src="' + content + '">';
		}
	}
).set(
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

				var contentUrl = smf_scripturl +'?action=dlattach;attach='+ id + ';type=preview;thumb';
				contentIMG = new Image();
					contentIMG.src = contentUrl;
			}

			// If not an image, show a boring ol' link
			if (typeof contentUrl === "undefined" || contentIMG.getAttribute('width') == 0)
				return '<a data-type="attach" href="' + smf_scripturl + '?action=dlattach;attach=' + id + ';type=preview;file"' + attribs + '>' + content + '</a>';
			// Show our purdy li'l picture
			else
				return '<img' + attribs + ' src="' + contentUrl + '">';
		}
	}
).set(
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
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		format(element, content) {
			if (element.getAttribute('data-type') != 'url')
				return content;

			if (decodeURI(element.href).replace(/\/$/, '') === content.replace(/\/$/, '')) {
				return '[url]' + content + '[/url]';
			}

			return '[url=' + decodeURI(element.href) + ']' + content + '[/url]';
		},
		html(token, {defaultattr}, content) {
			return '<a data-type="url" href="' + encodeURI(defaultattr || content) + '">' + content + '</a>';
		}
	}
).set(
	'iurl', {
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		tags: {
			a: {
				'data-type': ['iurl']
			}
		},
		format({href}, content) {
			return '[iurl=' + href + ']' + content + '[/iurl]';
		},
		html(token, {defaultattr}, content) {
			return '<a data-type="iurl" href="' + (defaultattr || content) + '">' + content + '</a>';
		}
	})
.set(
	'ftp', {
		allowsEmpty: true,
		quoteType: sceditor.BBCodeParser.QuoteType.never,
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
		format: '[nolink]{0}[/nolink]',
		html: '<span class="nolink">{0}</span>'
	}
);

sceditor.formats.bbcode.set(
	'pre', {
		tags: {
			a: {
				'data-type': ['ftp']
			}
		},
		format({href}, content) {
			return (href == content ? '[ftp]' : '[ftp=' + href + ']') + content + '[/ftp]';
		},
		html(token, {defaultattr}, content) {
			return '<a data-type="ftp" href="' + (defaultattr || content) + '">' + content + '</a>';
		}
	})
	.set('table', {
		breakStart: true,
		isHtmlInline: false,
		skipLastLineBreak: false,
	})
	.set('tr', {
		breakStart: true,
	})
	.set('tt', {
		tags: {
			tt: null,
			span: {'class': ['tt']}
		},
		format: '[tt]{0}[/tt]',
		html: '<span class="tt">{0}</span>'
	})
	.set('pre', {
		tags: {
			pre: null
		},
		isBlock: true,
		format: '[pre]{0}[/pre]',
		html: '<pre>{0}</pre>'
	})
	.set('me', {
		tags: {
			div: {
				'data-name' : null
			}
		},
		isInline: false,
		format(element, content) {
			return '[me=' + element.getAttribute('data-name') + ']' + content.replace(element.getAttribute('data-name') + ' ', '') + '[/me]';
		},
		html: '<div class="meaction" data-name="{defaultattr}">* {defaultattr} {0}</div>'
	})
.set(
	'php', {
		tags: {
			code: {
				class: 'php'
			},
			span: {
				class: 'phpcode'
			}
		},
		allowsEmpty: true,
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: "[php]{0}[/php]",
		html: '<code class="php">{0}</code>'
	}
);

sceditor.formats.bbcode.set(
	'code', {
		tags: {
			code: null,
			div: {
				class: 'codeheader'
			},
			pre: {
				class: 'bbc_code'
			}
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content) {
			let title = element.getAttribute('data-title');

			if (element.className === 'php')
				return content;
			else if (element.tagName === 'DIV')
				return '';
			else if (element.tagName === 'PRE')
				return content;
			else if (element.parentNode.tagName === 'PRE' && !title)
			{
				const t = element.parentNode.previousSibling.textContent;

				if (t.indexOf('(') != -1)
					title = t.replace(/^[^(]+\(/, '').replace(/\)? \[.+/, '');
			}

			const from = title ? ' =' + title : '';

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		html(element, {defaultattr}, content) {
			const from = defaultattr ? ' data-title="' + defaultattr + '"'  : '';

			return '<code data-name="' + sceditor.locale.code + '"' + from + '>' + content.replace('[', '&#91;') + '</code>'
		}
	}
).set(
	'quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		quoteType: sceditor.BBCodeParser.QuoteType.never,
		breakBefore: false,
		isInline: false,
		format(element, content) {
			let attrs = '';
			const author = element.getAttribute('data-author');
			const date = element.getAttribute('data-date');
			const link = element.getAttribute('data-link');

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
		html(element, attrs, content) {
			let attr_author = '';
			let author = '';
			let attr_date = '';
			let sDate = '';
			let attr_link = '';
			let link = '';

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
).set(
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
).set(
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
).set(
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
).set(
	'youtube', {
		tags: {
			div: {
				'data-youtube-id': null
			}
		},
		isInline: false,
		skipLastLineBreak: true,
		format: el => `[youtube]${el.getAttribute('data-youtube-id')}[/youtube]`,
		html: '<div data-youtube-id="{0}"><iframe frameborder="0" src="https://www.youtube-nocookie.com/embed/{0}?wmode=opaque" allowfullscreen></iframe></div>'
	}
);