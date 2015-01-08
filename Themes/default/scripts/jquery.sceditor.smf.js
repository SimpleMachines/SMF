/**
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2015 Simple Machines and individual contributors
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 2.1 Beta 1
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
				current_value  = this.getWysiwygEditorValue(filter);

			return current_value;
		},
		appendEmoticon: function (code, emoticon) {
			if (emoticon == '')
				line.append($('<br>'));
			else
				line.append($('<img>')
					.attr({
						src: emoticon,
						alt: code,
					})
					.click(function (e) {
						var	start = '', end = '';

						if (base.opts.emoticonsCompat)
						{
							start = '<span> ';
							end   = ' </span>';
						}

						if (base.inSourceMode())
							base.sourceEditorInsertText(' ' + $(this).attr('alt') + ' ');
						else
							base.wysiwygEditorInsertHtml(start + '<img src="' + $(this).attr("src") + '" data-sceditor-emoticon="' + $(this).attr('alt') + '">' + end);

						e.preventDefault();
					})
				);

			if (line.children().length > 0)
				content.append(line);

			$(".sceditor-toolbar").append(content);
		},
		storeLastState: function (){
			this.wasSource = this.inSourceMode();
		},
		setTextMode: function () {
			if (!this.inSourceMode())
				this.toggleSourceMode();
		},
		createPermanentDropDown: function () {
			var	emoticons	= $.extend({}, this.opts.emoticons.dropdown);
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
				this.opts.emoticons.more = this.opts.emoticons.popup;
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

						$.each(emoticons, base.appendEmoticon);

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

						$dropdown = $('<div class="sceditor-dropdown sceditor-smileyPopup">').append(popupContent);

						$dropdown.appendTo($('body'));
						dropdownIgnoreLastClick = true;
						adjheight = closeButton.height() + titlebar.height();
						$dropdown.css({
							position: "fixed",
							top: $(window).height() * 0.2,
							left: $(window).width() * 0.5 - ($dropdown.find('#sceditor-popup-smiley').width() / 2),
							"max-width": "50%",
							"max-height": "50%",
						}).find('#sceditor-popup-smiley').css({
							height: $dropdown.height() - adjheight,
							"overflow": "auto"
						});

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
			$.each(emoticons, base.appendEmoticon);
			if (typeof moreButton !== "undefined")
				content.append(moreButton);
		}
	};

	$.extend(true, $['sceditor'].prototype, extensionMethods);
})(jQuery);

$.sceditor.command.set(
	'ftp', {
		tooltip: 'Insert FTP Link',
		txtExec: ["[ftp]", "[/ftp]"],
		exec: function (caller) {
			var	editor  = this,
				content = $(this._('<form><div><label for="link">{0}</label> <input type="text" id="link" value="ftp://"></div>' +
						'<div><label for="des">{1}</label> <input type="text" id="des" value=""></div></form>',
					this._("URL:"),
					this._("Description (optional):")
				))
				.submit(function () {return false;});

			content.append($(
				this._('<div><input type="button" class="button" value="{0}"></div>',
					this._("Insert")
				)).click(function (e) {
				var val = $(this).parent("form").find("#link").val(),
					description = $(this).parent("form").find("#des").val();

				if (val !== "" && val !== "ftp://") {
					// needed for IE to reset the last range
					editor.focus();

					if (!editor.getRangeHelper().selectedHtml() || description)
					{
						if (!description)
							description = val;

						editor.wysiwygEditorInsertHtml('<a href="' + val + '">' + description + '</a>');
					}
					else
						editor.execCommand("createlink", val);
				}

				editor.closeDropDown(true);
				e.preventDefault();
			}));

			editor.createDropDown(caller, "insertlink", content);
		}
	}
);
$.sceditor.command.set(
	'glow', {
		tooltip: 'Glow',
		txtExec: ["[glow=red,2,300]", "[/glow]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('[glow=red,2,300]', '[/glow]');
		}
	}
);
$.sceditor.command.set(
	'shadow', {
		tooltip: 'Shadow',
		txtExec: ["[shadow=red,right]", "[/shadow]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('[shadow=red,right]', '[/shadow]');
		}
	}
);
$.sceditor.command.set(
	'tt', {
		tooltip: 'Teletype',
		txtExec: ["[tt]", "[/tt]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<tt>', '</tt>');
		}
	}
);
$.sceditor.command.set(
	'pre', {
		tooltip: 'Pre',
		txtExec: ["[pre]", "[/pre]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<pre>', '</pre>');
		}
	}
);
$.sceditor.command.set(
	'move', {
		tooltip: 'Marquee',
		txtExec: ["[move]", "[/move]"],
		exec: function () {
			this.wysiwygEditorInsertHtml('<marquee>', '</marquee>');
		}
	}
);

$.sceditor.command.set(
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
$.sceditor.command.set(
	'link', {
		txtExec: function (caller, selected) {
			var	display = selected && selected.indexOf('http://') > -1 ? null : selected,
				url	= prompt(this._("Enter URL:"), (display ? 'http://' : selected));
			if (url)
			{
				var text	= prompt(this._("Enter the displayed text:"), display || url) || url;
				this.insertText("[url=" + url + "]" + text + "[/url]");
			}
		}
	}
);

$.sceditor.command.set(
	'bulletlist', {
		txtExec: function (caller, selected) {
			if (selected)
			{
				var content = '';

				$.each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				this.insertText('[list]\n' + content + '\n[/list]');
			}
			else
				this.insertText('[list]\n[li]', '[/li]\n[li][/li]\n[/list]');
		}
	}
);

$.sceditor.command.set(
	'orderedlist', {
		txtExec:  function (caller, selected) {
			if (selected)
			{
				var content = '';

				$.each(selected.split(/\r?\n/), function () {
					content += (content ? '\n' : '') + '[li]' + this + '[/li]';
				});

				this.insertText('[list type=decimal]\n' + content + '\n[/list]');
			}
			else
				this.insertText('[list type=decimal]\n[li]', '[/li]\n[li][/li]\n[/list]');
		}
	}
);

$.sceditor.command.set(
	'table', {
		txtExec: ["[table]\n[tr]\n[td]", "[/td]\n[/tr]\n[/table]"]
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'abbr', {
		tags: {
			abbr: {
				title: null
			}
		},
		format: function (element, content) {
			return '[abbr=' + element.attr('title') + ']' + content + '[/abbr]';
		},
		html: function (element, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;

			return '<abbr title="' + attrs.defaultattr + '">' + content + '</abbr>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'acronym', {
		tags: {
			acronym: {
				title: null
			}
		},
		format: function (element, content) {
			return '[abbr=' + element.attr('title') + ']' + content + '[/abbr]';
		},
		html: function (element, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;

			return '<abbr title="' + attrs.defaultattr + '">' + content + '</abbr>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'bdo', {
		tags: {
			bdo: {
				dir: null
			}
		},
		format: function (element, content) {
			return '[bdo=' + element.attr('dir') + ']' + content + '[/bdo]';
		},
		html: function (element, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				return content;
			if (attrs.defaultattr != 'rtl' && attrs.defaultattr != 'ltr')
				return '[bdo=' + attrs.defaultattr + ']' + content + '[/bdo]';

			return '<bdo dir="' + attrs.defaultattr + '">' + content + '</bdo>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'black', {
		html: '<font color="black">{0}</font>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'blue', {
		html: '<font color="blue">{0}</font>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'green', {
		html: '<font color="green">{0}</font>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'red', {
		html: '<font color="red">{0}</font>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'white', {
		html: '<font color="white">{0}</font>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'list', {
		breakStart: true,
		isInline: false,
		allowedChildren: ['*', 'li'],
		html: function (element, attrs, content) {
			var style = '';
			var code = 'ul';

			if (attrs.type)
					style = ' style="list-style-type: ' + attrs.type + '"';

			return '<' + code + style + '>' + content + '</' + code + '>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'ul', {
		tags: {
			ul: null
		},
		breakStart: true,
		isInline: false,
		html: '<ul>{0}</ul>',
		format: function (element, content) {
			if ($(element[0]).css('list-style-type') == 'disc')
				return '[list]' + content + '[/list]';
			else
				return '[list type=' + $(element[0]).css('list-style-type') + ']' + content + '[/list]';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'ol', {
		tags: {
			ol: null
		},
		breakStart: true,
		isInline: false,
		format: "[list type=decimal]{0}[/list]",
		html: '<ol>{0}</ol>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'img', {
		tags: {
			img: {
				src: null
			}
		},
		allowsEmpty: true,
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		format: function (element, content) {
			var	attribs = '',
				style = function (name) {
					return element.style ? element.style[name] : null;
				};

			// check if this is an emoticon image
			if (typeof element.attr('data-sceditor-emoticon') !== "undefined")
				return content;

			// only add width and height if one is specified
			if (element.attr('width') || style('width'))
				attribs += " width=" + $(element).width();
			if (element.attr('height') || style('height'))
				attribs += " height=" + $(element).height();
			if (element.attr('alt'))
				attribs += " alt=" + element.attr('alt');

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

			return '<img' + attribs + ' src="' + content + '">';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'url', {
		allowsEmpty: true,
		tags: {
			a: {
				href: null
			}
		},
		format: function (element, content) {
			var url = element.attr('href');

			// make sure this link is not an e-mail, if it is return e-mail BBCode
			if (url.substr(0, 7) === 'mailto:')
				return '[email=' + url.substr(7) + ']' + content + '[/email]';
			// make sure this link is not an ftp, if it is return ftp BBCode
			else if (url.substr(0, 3) === 'ftp')
				return '[ftp=' +  url + ']' + content + '[/ftp]';

			if (element.attr('target') !== undefined)
				return '[url=' + decodeURI(url) + ']' + content + '[/url]';
			else
				return '[iurl=' + decodeURI(url) + ']' + content + '[/iurl]';
		},
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a target="_blank" href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'iurl', {
		allowsEmpty: true,
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'ftp', {
		allowsEmpty: true,
		html: function (token, attrs, content) {
			if (typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
				attrs.defaultattr = content;

			return '<a target="_blank" href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
		}
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'tt', {
		tags: {
			tt: null
		},
		format: "[tt]{0}[/tt]",
		html: '<tt>{0}</tt>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'pre', {
		tags: {
			pre: null
		},
		isBlock: true,
		format: "[pre]{0}[/pre]",
		html: "<pre>{0}</pre>\n"
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'move', {
		tags: {
			marquee: null
		},
		format: "[move]{0}[/move]",
		html: '<marquee>{0}</marquee>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'php', {
		isInline: false,
		format: "[php]{0}[/php]",
		html: '<code class="php">{0}</code>'
	}
);

$.sceditor.plugins.bbcode.bbcode.set(
	'code', {
		tags: {
			code: null
		},
		isInline: false,
		allowedChildren: ['#', '#newline'],
		format: function (element, content) {
			if ($(element[0]).hasClass('php'))
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

$.sceditor.plugins.bbcode.bbcode.set(
	'quote', {
		tags: {
			blockquote: null,
			cite: null
		},
		quoteType: $.sceditor.BBCodeParser.QuoteType.never,
		breakBefore: false,
		isInline: false,
		format: function (element, content) {
			var author = '';
			var date = '';
			var link = '';

			// The <cite> contains only the graphic for the quote, so we can skip it
			if (element[0].tagName.toLowerCase() === 'cite')
				return '';

			if (element.attr('author'))
				author = ' author=' + element.attr('author').php_unhtmlspecialchars();
			if (element.attr('date'))
				date = ' date=' + element.attr('date');
			if (element.attr('link'))
				link = ' link=' + element.attr('link');

			return '[quote' + author + date + link + ']' + content + '[/quote]';
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
				sDate = '<date timestamp="' + attr_date + '">' + new Date(attrs.date * 1000) + '</date>';
			}

			if (author == '' && sDate == '')
				author = bbc_quote;
			else if (author == '' && sDate != '')
				author += ' ' + bbc_search_on;

			content = '<blockquote author="' + attr_author + '" date="' + attr_date + '" link="' + attr_link + '"><cite>' + author + ' ' + sDate + '</cite>' + content + '</blockquote>';

			return content;
		}
	}
);
