/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines https://www.simplemachines.org
 * @copyright 2023 Simple Machines and individual contributors
 * @license https://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1.4
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

				var contentUrl = smf_scripturl +'?action=dlattach;attach='+ id + ';type=preview;thumb';
				contentIMG = new Image();
					contentIMG.src = contentUrl;
			}

			// If not an image, show a boring ol' link
			if (typeof contentUrl === "undefined" || contentIMG.getAttribute('width') == 0)
				return '<a href="' + smf_scripturl + '?action=dlattach;attach=' + id + ';type=preview;file"' + attribs + '>' + content + '</a>';
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
			return '[iurl=' + decodeURI(element.href) + ']' + content + '[/iurl]';
		},
		html: function (token, attrs, content)
		{
			return '<a data-type="iurl" href="' + encodeURI(attrs.defaultattr || content) + '">' + content + '</a>';
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