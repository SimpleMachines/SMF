/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2025 Simple Machines and individual contributors
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
				children[i - 1].className = Math.ceil(i / 12) <= col && (i % 12 || 12) <= row ? 'active' : '';

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
		};

		let buttons = {};

		this.signalReady = function ()
		{
			for (const group of this.opts.toolbarContainer.children[0].children) {
				for (const button of group.children) {
					const cmd = button.dataset.sceditorCommand;
					buttons[cmd] = button;

					// Create a pseudo linebreak.
					if (this.opts.toolbar.includes(cmd + '||')) {
						button.parentNode.after(document.createElement('div'));
					}

					// Add icon to custom buttons.
					if (this.opts.customTextualCommands[cmd]) {
						button.firstChild.style.backgroundImage = 'url(' + smf_default_theme_url + '/images/bbc/' + this.opts.customTextualCommands[cmd].image + '.png)';
					}

					// Add arrowhead to buttons.
					if (this.opts.commandsWithDropdown[cmd]) {
						button.classList.add('with-dropdown');
					}

					// This button uses text without an icon.
					if (this.opts.textOnlyCommands[cmd]) {
						button.classList.add('text');
					}

					// Show text alongside the icon on this button.
					if (this.opts.commandsWithText[cmd]) {
						button.classList.add('text-icon');
					}
				}
			}

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

			// Override these functions in order to convince SCEditor not to
			// delete tabs.  Supporting Markdown means we need to keep them.
			const getSourceVal = editor.getSourceEditorValue;
			const setSourceVal = editor.setSourceEditorValue;
			const sourceEditor = editor.getContentAreaContainer().nextSibling;

			editor.getSourceEditorValue = function (filter) {
				if (filter !== false) {
					sourceEditor.value = sourceEditor.value.replaceAll(/\t/, '[tab]');
				}

				return getSourceVal(filter);
			};

			editor.setSourceEditorValue = function (value) {
				setSourceVal(value.replaceAll(/\[tab\]/, '\t'));
			};
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

		textarea.value = textarea.value.replaceAll(/\t/, '[tab]');

		// Call the original create function
		createFn(textarea, options);

		textarea.value = textarea.value.replaceAll(/\[tab\]/, '\t');
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
);

sceditor.command.set(
	'heading', {
		_dropDown: function (editor, caller, callback) {
			var	content = document.createElement('div');

			for (var i = 1; i <= 6; i++) {
				let opt = document.createElement('a');
				opt.href = '#',
				opt.dataset.tag = 'h' + i;
				opt.innerText = 'H' + i;
				opt.style.display = 'block';
				opt.classList.add('bbc_h' + i);
				content.appendChild(opt);
			}

			if (!editor.sourceMode()) {
				let opt = document.createElement('a');
				opt.href = '#',
				opt.dataset.tag = '';
				opt.innerText = "\u2014";
				opt.style.display = 'block';
				content.appendChild(opt);
			}

			for (const elem of content.querySelectorAll("a")) {
				elem.addEventListener("click", function (e) {
					callback(elem.dataset.tag);
					editor.closeDropDown(true);
					e.preventDefault();
				});
			}

			editor.createDropDown(caller, 'item-picker', content);
		},
		state: function (parent, firstBlock) {
			return sceditor.dom.closest(this.currentNode(), 'h1, h2, h3, h4, h5, h6') ? 1 : 0;
		},
		txtExec: function (caller) {
			var editor = this;

			editor.commands.heading._dropDown(editor, caller, function (tag) {
				editor.insert('[' + tag + ']', '[/' + tag + ']');
			});
		},
		exec: function (caller) {
			var editor = this;

			editor.commands.heading._dropDown(editor, caller, function (tag) {
				const rangeHelper = editor.getRangeHelper();
				const container = rangeHelper.parentNode().parentNode.closest('h1, h2, h3, h4, h5, h6');

				if (!container) {
					return;
				}

				if (
					container.nodeType === Node.ELEMENT_NODE
					&& container.nodeName.match(/H[1-6]/)
				) {
					const content = container.innerHTML;
					let newElement = document.createElement(tag.match(/h[1-6]/) ? tag : 'p');
					newElement.innerHTML = content;
					container.replaceWith(newElement);
					container.parentNode.normalize();
					const range = rangeHelper.selectedRange();
					range.setStartAfter(newElement);
					rangeHelper.selectRange(range);
				} else if (tag.match(/h[1-6]/)) {
					editor.insert('[' + tag + ']', '[/' + tag + ']');
				}
			});
		},
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

			editor.createDropDown(caller, 'item-picker', content);
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

			editor.createDropDown(caller, 'item-picker', content);
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

sceditor.command.set(
	'tt', {
		state: function (parent, firstBlock) {
			if (this.inSourceMode()) {
				return 0;
			}

			let currNode = sceditor.dom.closest(this.currentNode(), 'font');

			if (!currNode) {
				return 0;
			}

			let font = currNode.getAttribute('face');

			return (font === 'monospace') ? 1 : 0;
		},
		exec: function(caller) {
			let currNode = sceditor.dom.closest(this.currentNode(), 'font');

			if (!currNode) {
				this.execCommand('fontname', 'monospace');
			} else {
				let font = currNode.getAttribute('face');

				if (font === 'monospace') {
					this.execCommand('removeFormat');
				} else {
					this.execCommand('fontname', 'monospace');
				}
			}
		},
		txtExec: function(caller) {
			this.insert('[tt]', '[/tt]');
		}
	}
);

sceditor.command.set(
	'details', {
		_dropDown: function (editor, caller, callback) {
			const input_wrapper = document.createElement('div');
			const label = document.createElement('label');
			label.innerText = editor._('summaryPrompt');
			const input = document.createElement('input');
			input.type = 'text';
			input.id = 'summary';
			input.value = editor._('spoiler');
			input_wrapper.appendChild(label);
			input_wrapper.appendChild(input);

			const button_wrapper = document.createElement('div');
			const button = document.createElement('input');
			button.type = 'button';
			button.className = 'button';
			button.value = editor._('Insert');
			button_wrapper.appendChild(button);

			const content = document.createElement('div');
			content.appendChild(input_wrapper);
			content.appendChild(button_wrapper);

			button.addEventListener("click", function (e) {
				callback(input.value);
				editor.closeDropDown(true);
				e.preventDefault();
			});

			editor.createDropDown(caller, 'details-panel', content);
		},
		exec: function(caller) {
			var editor = this;

			editor.commands.details._dropDown(editor, caller, function (summary) {
				summary = summary.replace('"', '\\"');

				// If no summary was provided, use a default value.
				if (summary.length < 1) {
					summary = editor._('spoiler');
				}

				editor.insert('[details summary="' + summary + '"]', '[/details]');
			});
		},
		txtExec: function(caller) {
			var editor = this;

			editor.commands.details._dropDown(editor, caller, function (summary) {
				summary = summary.replace('"', '\\"');

				if (summary.length < 1) {
					summary = editor._('spoiler');
					return;
				}

				editor.insert('[details summary="' + summary + '"]', '[/details]');
			});
		}
	}
);

sceditor.command.set(
	'spoiler', {
		state: function (parent, firstBlock) {
			if (this.inSourceMode()) {
				return 0;
			}

			return sceditor.dom.closest(this.currentNode(), '.bbc_inline_spoiler') ? 1 : 0;
		},
		exec: function(caller) {
			// If we are currently inside an inline spoiler span, remove it.
			const spoilerElement = sceditor.dom.closest(this.currentNode(), '.bbc_inline_spoiler');

			if (spoilerElement) {
				const rangeHelper = this.getRangeHelper();
				rangeHelper.insertMarkers();
				rangeHelper.saveRange();
				spoilerElement.insertAdjacentHTML('beforebegin', spoilerElement.innerHTML);
				rangeHelper.restoreRange();
				spoilerElement.remove();
				return;
			}

			// Otherwise, insert it.
			this.insert('[spoiler]', '[/spoiler]');
		},
		txtExec: function(caller) {
			this.insert('[spoiler]', '[/spoiler]');
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

// This pseudo-BBCode exists solely to convince SCEditor not to delete tab characters.
sceditor.formats.bbcode.set(
	'tab', {
		tags: {
			span: {
				class: 'tab'
			}
		},
		allowsEmpty: true,
		isSelfClosing: true,
		isInline: true,
		format: '\t',
		html: '<span style="white-space: pre;" class="tab">\t</span>'
	}
);

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

sceditor.formats.bbcode
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
			code: {'class': ['bbc_tt']},
			font: {
				'face': 'monospace'
			}
		},
		format: '[tt]{0}[/tt]',
		html: '<font face="monospace">{0}</font>'
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
				class: 'phpcode'
			}
		},
		allowsEmpty: true,
		isInline: true,
		allowedChildren: ['#', '#newline'],
		format: "[php]{0}[/php]",
		html: '<code class="php">{0}</code>'
	}
)
set(
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

			if (element.className === 'phpcode' || element.className === 'bbc_tt')
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

			return '<pre class="bbc_code"><code data-name="' + this.opts.txtVars.code + '"' + from + '>' + content.replace('[', '&#91;').replaceAll(/\[tab\]/, '<span style="white-space: pre;" class="tab">\t</span>') + '</code></pre>';
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

			// To make [tt] work, we need to add an exception to the [font] BBC.
			if (font === 'monospace') {
				return content;
			}

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

for (var i = 1; i <= 6; i++) {
	sceditor.formats.bbcode.set('h' + i, {
		tags: {
			['h' + i]: null
		},
		isInline: false,
		skipLastLineBreak: true,
		format: '[h' + i + ']{0}[/h' + i + ']',
		html: '<h' + i + '>{0}</h' + i + '>'
	});
}

sceditor.formats.bbcode.set(
	'nobbc', {
		tags: {
			span: {
				class: 'nobbc',
			},
			div: {
				class: 'nobbc',
			},
		},
		format: function (element, content) {
			if (!content.includes('[nobbc]')) {
				content = '[nobbc]' + content;
			}

			if (!content.includes('[/nobbc]')) {
				content = content + '[/nobbc]';
			}

			return content;
		},
		html: function (token, attrs, content) {
			let tag;

			if (content.includes("\n")) {
				tag = 'div';
			} else {
				tag = 'span';
			}

			return '<' + tag + ' class="nobbc">[nobbc]' + content + '[/nobbc]</' + tag + '>';
		},
		allowedChildren: ['#']
	}
);

sceditor.formats.bbcode.set(
	'details', {
		tags: {
			details: null,
			summary: null,
		},
		format: function (element, content) {
			const elem = $(element)[0];

			if ($(elem)[0].tagName.toLowerCase() === 'summary') {
				return '';
			}

			if ($(elem)[0].tagName.toLowerCase() === 'details') {
				const summary = $(elem).children('summary') ? $(elem).children('summary').text().replace('"', '\\"') : Object.values(sceditor.locale)[0].details;

				return '[details summary="' + summary + '"]' + content + '[/details]'
			}
		},
		html: function (token, attrs, content) {
			const summary = typeof attrs.summary === "undefined" ? Object.values(sceditor.locale)[0].details : attrs.summary.replace('"', '\\"');

			content = content.replace(/^<br ?\/?>/, '').replace(/<br ?\/?>$/, '');

			return '<details open class="bbc_details"><summary class="bbc_summary">' + summary + '</summary><div class="bbc_details_content">' + content + '</div></details>';
		},
		allowsEmpty: true,
		isInline: false,
		breakEnd: true,
		breakAfter: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.always,
	}
);

sceditor.formats.bbcode.set(
	'spoiler', {
		tags: {
			span: {
				class: 'bbc_inline_spoiler',
			},
		},
		format: '[spoiler]{0}[/spoiler]',
		html: function (token, attrs, content) {
			// If we find something like `[spoiler="foo"]bar[/spoiler]`, turn
			// it into a details element.
			if (
				typeof attrs.defaultattr !== "undefined"
				|| typeof attrs.text !== "undefined"
				|| /<br ?\/?>/.test(content)
			) {
				const summary = typeof attrs.defaultattr !== "undefined" ? attrs.defaultattr.replace('"', '\\"') : (typeof attrs.text !== "undefined" ? attrs.text.replace('"', '\\"') : Object.values(sceditor.locale)[0].spoiler);

				content = content.replace(/^<br ?\/?>/, '').replace(/<br ?\/?>$/, '');

				return '<details open class="bbc_details"><summary class="bbc_summary">' + summary + '</summary><div class="bbc_details_content">' + content + '</div></details>';
			}

			return '<span class="bbc_inline_spoiler">' + content + '</span>';
		},
		skipLastLineBreak: true,
		isInline: true,
		allowsEmpty: true,
	}
);