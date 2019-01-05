/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2019 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 RC1
 */

(function ($) {
	var extensionMethods = {
		InsertText: function (text, bClear) {
			var bIsSource = this.inSourceMode();

			// @TODO make it put the quote close to the current selection

			if (!bIsSource)
				this.toggleSourceMode();

			var current_value = bClear ? text : this.getSourceEditorValue(false) + text;
			this.setSourceEditorValue(current_value);

			if (!bIsSource)
				this.toggleSourceMode();
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
			$.extend(true, instance.constructor.prototype, extensionMethods);
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
	'email', {
		txtExec: function (caller, selected) {
			var	display = selected && selected.indexOf('@') > -1 ? null : selected,
				email	= prompt(this._("Enter the e-mail address:"), (display ? '' : selected));
			if (email)
			{
				var text	= prompt(this._("Enter the displayed text:"), display || email) || email;
				this.insertText("[email=" + email + "]" + text + "[/email]");
			}
		}
	}
);
sceditor.command.set(
	'link', {
		txtExec: function (caller, selected) {
			var	display = selected && selected.indexOf('http://') > -1 ? null : selected,
				url	= prompt(this._("Enter URL:"), (display ? 'http://' : selected));
			if (url)
			{
				var text	= prompt(this._("Enter the displayed text:"), display || url) || url;
				this.insertText("[url=\"" + url + "\"]" + text + "[/url]");
			}
		},
		exec: function (caller) {
			var editor = this;

			editor.commands.link._dropDown(editor, caller, function (url, text) {
				// needed for IE to restore the last range
				editor.focus();

				// If there is no selected text then must set the URL as
				// the text. Most browsers do this automatically, sadly
				// IE doesn't.
				if (!editor.getRangeHelper().selectedHtml() || text) {
					text = text || url;

					editor.wysiwygEditorInsertHtml(
						'<a target="_blank" rel="noopener" href="' + url + '">' + text + '</a>'
					);
				} else {
					// Can't just use `editor.execCommand('createlink', url)`
					// because we need to set the target attribute.
					editor.wysiwygEditorInsertHtml(
						'<a target="_blank" rel="noopener" href="' + url + '">', '</a>'
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
			{
				var content = '';

				each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				this.insertText('[list]\n' + content + '\n[/list]');
			}
			else
				this.insertText('[list]\n[li]', '[/li]\n[li][/li]\n[/list]');
		}
	}
);

sceditor.command.set(
	'orderedlist', {
		txtExec: function (caller, selected) {
			if (selected)
			{
				var content = '';

				each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				this.insertText('[list type=decimal]\n' + content + '\n[/list]');
			}
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
	'youtube', {
		exec: function (caller) {
			var editor = this;

			editor.commands.youtube._dropDown(editor, caller, function (id, time) {
				editor.wysiwygEditorInsertHtml('<div class="videocontainer"><div><iframe frameborder="0" allowfullscreen src="https://www.youtube.com/embed/' + id + '?wmode=opaque&start=' + time + '" data-youtube-id="' + id + '"></iframe></div></div>');
			});
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
				if (element.attr('title'))
					attribs += ' name=' + element.attr('title');
				if (element.attr('data-type'))
					attribs += ' type=' + 	element.attr('data-type');

				return '[attach' + attribs + ']' + element.attr('data-attachment') + '[/attach]';
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
			attach: {
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

			// only add width and height if one is specified
			if (element.attr('width') || style('width'))
				attribs += " width=" + element.attr('width');
			if (element.attr('height') || style('height'))
				attribs += " height=" + element.attr('height');
			if (element.attr('alt'))
				attribs += " alt=" + element.attr('alt');
			if (element.attr('title'))
				attribs += " name=" + element.attr('title');
			if (element.attr('data-type'))
				attribs += " type=" + element.attr('data-type');

			return '[attach' + attribs + ']' + (element.attr('data-attachment') ? element.attr('data-attachment') : content) + '[/attach]';
		},
		html: function (token, attrs, id) {
			var parts,
				attribs = '';

			// If id is not an integer, bail out
			if (!$.isNumeric(id) || Math.floor(id) != +id || +id <= 0) {

				if (typeof attrs.width !== "undefined")
					attribs += ' width=' + attrs.width;
				if (typeof attrs.height !== "undefined")
					attribs += ' height=' + attrs.height;
				if (typeof attrs.alt !== "undefined")
					attribs += ' alt=' + attrs.alt;
				if (typeof attrs.name !== "undefined")
					attribs += ' name=' + attrs.name;
				if (typeof attrs.type !== "undefined")
					attribs += ' type=' + attrs.type;

				return '[attach' + attribs + ']' + id + '[/attach]';
			}

			attribs += ' data-attachment="' + id + '"'
			if (typeof attrs.width !== "undefined")
				attribs += ' width="' + attrs.width + '"';
			if (typeof attrs.height !== "undefined")
				attribs += ' height="' + attrs.height + '"';
			if (typeof attrs.alt !== "undefined")
				attribs += ' alt="' + attrs.alt + '"';
			if (typeof attrs.type !== "undefined")
				attribs += ' data-type="' + attrs.type + '"';
			if (typeof attrs.name !== "undefined")
				attribs += ' title="' + attrs.name + '"';

			// Is this an image?
			if ((typeof attrs.type !== "undefined" && attrs.type.indexOf("image") === 0)) {
				var contentUrl = smf_scripturl +'?action=dlattach;attach='+ id + ';type=preview;thumb';
				contentIMG = new Image();
					contentIMG.src = contentUrl;
			}

			// If not an image, show a boring ol' link
			if (typeof contentUrl === "undefined" || contentIMG.getAttribute('width') == 0)
				return '<a href="' + smf_scripturl + '?action=dlattach;attach=' + id + ';type=preview;file"' + attribs + '>' + (typeof attrs.name !== "undefined" ? attrs.name : id) + '</a>';
			// Show our purdy li'l picture
			else
				return '<img' + attribs + ' src="' + contentUrl + '">';
		}
	}
);

sceditor.formats.bbcode.set(
	'url', {
		allowsEmpty: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		tags: {
			a: {
				href: null
			}
		},
		format: function (element, content) {
			var element = $(element),
				url = element.attr('href');

			// make sure this link is not an e-mail, if it is return e-mail BBCode
			if (url.substr(0, 7) === 'mailto:')
				return '[email=' + url.substr(7) + ']' + content + '[/email]';

			if (typeof element.attr('target') !== "undefined")
				return '[url=\"' + decodeURI(url) + '\"]' + content + '[/url]';

			// A mention?
			else if (typeof element.attr('data-mention') !== "undefined")
				return '[member='+ element.attr('data-mention') +']'+ content.replace('@','') +'[/member]';

			// Is this an attachment?
			else if (typeof element.attr('data-attachment') !== "undefined")
			{
				var attribs = '';
				if (typeof element.attr('title') !== "undefined")
					attribs += ' name=' + element.attr('title');
				if (typeof element.attr('data-type') !== "undefined")
					attribs += ' type=' + element.attr("data-type");

				return '[attach' + attribs + ']' + element.attr('data-attachment') + '[/attach]';
			}

			else
				return '[iurl=\"' + decodeURI(url) + '\"]' + content + '[/iurl]';
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a target="_blank" rel="noopener" href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
		}
	}
);

sceditor.formats.bbcode.set(
	'iurl', {
		allowsEmpty: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		html: function (token, attrs, content) {

			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
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

			var from = '';
			if ($(element).children("cite:first").length === 1)
			{
				from = $(element).children("cite:first").text();

				$(element).attr({'from': from.php_htmlspecialchars()});

				from = '=' + from;
				content = '';
				$(element).children("cite:first").remove();
				content = this.elementToBbcode($(element));
			}
			else
			{
				if (typeof $(element).attr('from') != 'undefined')
				{
					from = '=' + $(element).attr('from').php_unhtmlspecialchars();
				}
			}

			return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
		},
		html: function (element, attrs, content) {
			var from = '';
			if (typeof attrs.defaultattr !== "undefined")
				from = '<cite>' + attrs.defaultattr + '</cite>';

			return '<code>' + from + content.replace('[', '&#91;') + '</code>'
		}
	}
);

sceditor.formats.bbcode.set(
	'quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		breakBefore: false,
		isInline: false,
		format: function (element, content) {
			var element = $(element);
			var author = '';
			var date = '';
			var link = '';

			// The <cite> contains only the graphic for the quote, so we can skip it
			if (element[0].tagName.toLowerCase() === 'cite')
				return '';

			if (element.attr('author'))
				author = ' author=' + element.attr('author').php_unhtmlspecialchars();
			if (element.attr('link'))
				link = ' link=' + element.attr('link');
			if (element.attr('date'))
				date = ' date=' + element.attr('date');

			return '[quote' + author + link + date + ']' + content + '[/quote]';
		},
		html: function (element, attrs, content) {
			var attr_author = '', author = '';
			var attr_date = '', sDate = '';
			var attr_link = '', link = '';

			if (typeof attrs.author !== "undefined" && attrs.author)
			{
				attr_author = attrs.author;
				author = bbc_quote_from + ': ' + attr_author;
			}

			// Links could be in the form: link=topic=71.msg201#msg201 that would fool javascript, so we need a workaround
			// Probably no more necessary
			for (var key in attrs)
			{
				if (key.substr(0, 4) == 'link' && attrs.hasOwnProperty(key))
				{
					var attr_link = key.length > 4 ? key.substr(5) + '=' + attrs[key] : attrs[key];

					link = attr_link.substr(0, 7) == 'http://' ? attr_link : smf_scripturl + '?' + attr_link;
					author = author == '' ? '<a href="' + link + '">' + bbc_quote_from + ': ' + link + '</a>' : '<a href="' + link + '">' + author + '</a>';
				}
			}

			if (typeof attrs.date !== "undefined" && attrs.date)
			{
				attr_date = attrs.date;
				tDate = new Date(attr_date * 1000);
				sDate_string = tDate.toLocaleString();
				sDate = '<date timestamp="' + attr_date + '">' + sDate_string + '</date>';
			}

			if (author == '' && sDate == '')
				author = bbc_quote;
			else if (author == '' && sDate != '')
				author += ' ' + bbc_search_on;

			/*
			 * This fixes GH Bug #2845
			 * As SMF allows "[quote=text]message[/quote]" it is lost during sceditor when it converts bbc to html and then html back to bbc code.  The simplest method is to tell sceditor that this is a "author", which is how the bbc parser treats it in SMF.  This will cause all bbc to be updated to "[quote author=text]message[/quote]".
			*/
			if (attr_author == '' && attrs.defaultattr != '')
				attr_author = attrs.defaultattr;

			content = '<blockquote author="' + attr_author + '" date="' + attr_date + '" link="' + attr_link + '"><cite>' + author + ' ' + sDate + '</cite>' + content + '</blockquote>';

			return content;
		}
	}
);

sceditor.formats.bbcode.set('font', {
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
});

sceditor.formats.bbcode.set(
	'member', {
		isInline: true,
		format: function (element, content) {
			return '[member='+ $(element).attr('data-mention') +']'+ content.replace('@','') +'[/member]';
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a href="' + smf_scripturl +'?action=profile;u='+ attrs.defaultattr + '" class="mention" data-mention="'+ attrs.defaultattr + '">@'+ content.replace('@','') +'</a>';
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

			side = (element.css('float').indexOf('left') == 0 ? 'left' : 'right');
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
		html: '<div class="videocontainer"><div><iframe frameborder="0" src="https://www.youtube.com/embed/{0}?wmode=opaque" data-youtube-id="{0}" allowfullscreen></iframe></div></div>'
	}
);
