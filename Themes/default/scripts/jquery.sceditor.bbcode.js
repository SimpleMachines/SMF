/**
 * SCEditor BBCode Plugin
 * http://www.samclarke.com/2011/07/sceditor/
 *
 * Copyright (C) 2011-2012, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @author Sam Clarke
 * @version 1.3.7
 * @requires jQuery
 */

// ==ClosureCompiler==
// @output_file_name jquery.sceditor.min.js
// @compilation_level SIMPLE_OPTIMIZATIONS
// ==/ClosureCompiler==

/*jshint smarttabs: true, jquery: true, eqnull:true, curly: false */

(function($) {
	'use strict';

	/**
	 * BBCode plugin for SCEditor
	 *
	 * @param {Element} el The textarea to be converted
	 * @return {Object} options
	 * @class sceditorBBCodePlugin
	 * @name jQuery.sceditorBBCodePlugin
	 */
	$.sceditorBBCodePlugin = function(element, options) {
		var base = this;

		/**
		 * Private methods
		 * @private
		 */
		var	init,
			buildBbcodeCache,
			handleStyles,
			handleTags,
			formatString,
			getStyle,
			wrapInDivs,
			isEmpty,
			mergeTextModeCommands;

		base.bbcodes = $.sceditorBBCodePlugin.bbcodes;


		/**
		 * cache of all the tags pointing to their bbcodes to enable
		 * faster lookup of which bbcode a tag should have
		 * @private
		 */
		var tagsToBbcodes = {};

		/**
		 * Same as tagsToBbcodes but instead of HTML tags it's styles
		 * @private
		 */
		var stylesToBbcodes = {};

		/**
		 * Allowed children of specific HTML tags. Empty array if no
		 * children other than text nodes are allowed
		 * @private
		 */
		var validChildren = {
			list: ['li'],
			table: ['tr'],
			tr: ['td', 'th'],
			code: ['br', 'p', 'div'],
			youtube: []
		};


		/**
		 * Initializer
		 * @private
		 * @name sceditorBBCodePlugin.init
		 */
		init = function() {
			$.data(element, "sceditorbbcode", base);

			base.options = $.extend({}, $.sceditor.defaultOptions, options);

			// build the BBCode cache
			buildBbcodeCache();

			(new $.sceditor(element,
				$.extend({}, base.options, {
					getHtmlHandler: base.getHtmlHandler,
					getTextHandler: base.getTextHandler,
					commands: mergeTextModeCommands()
				})
			));
		};

		mergeTextModeCommands = function() {
			var merge = {
				bold: { txtExec: ["[b]", "[/b]"] },
				italic: { txtExec: ["[i]", "[/i]"] },
				underline: { txtExec: ["[u]", "[/u]"] },
				strike: { txtExec: ["[s]", "[/s]"] },
				subscript: { txtExec: ["[sub]", "[/sub]"] },
				superscript: { txtExec: ["[sup]", "[/sup]"] },
				left: { txtExec: ["[left]", "[/left]"] },
				center: { txtExec: ["[center]", "[/center]"] },
				right: { txtExec: ["[right]", "[/right]"] },
				justify: { txtExec: ["[justify]", "[/justify]"] },
				ftp: { txtExec: ["[ftp]", "[/ftp]"] },
				tt: { txtExec: ["[tt]", "[/tt]"] },
				glow: { txtExec: ["[glow=red,2,300]", "[/glow]"] },
				move: { txtExec: ["[[move]", "[/move]"] },
				shadow: { txtExec: ["[shadow=red,left]", "[/shadow]"] },
				pre: { txtExec: ["[pre]", "[/pre]"] },
				// @todo: check tooltip
				font: { txtExec: function(caller) {
					var editor = this;

					$.sceditor.command.get('font')._dropDown(
						editor,
						caller,
						function(fontName) {
							editor.insertText("[font="+fontName+"]", "[/font]");
						}
					);
				} },
				// @todo: check overlapping
				size: { txtExec: function(caller) {
					var editor = this;

					$.sceditor.command.get('size')._dropDown(
						editor,
						caller,
						function(fontSize) {
							editor.insertText("[size="+fontSize+"]", "[/size]");
						}
					);
				} },
				color: { txtExec: function(caller) {
					var editor = this;

					$.sceditor.command.get('color')._dropDown(
						editor,
						caller,
						function(color) {
							editor.insertText("[color="+color+"]", "[/color]");
						}
					);
				} },
				bulletlist: { txtExec: ["[list]\n[li]", "[/li]\n[li][/li]\n[/list]"] },
				orderedlist: { txtExec: ["[list type=decimal]\n[li]", "[/li]\n[li][/li]\n[/list]"] },
				table: { txtExec: ["[table]\n[tr]\n[td]", "[/td]\n[/tr]\n[/table]"] },
				horizontalrule: { txtExec: ["[hr]"] },
				code: { txtExec: ["[code]", "[/code]"] },
				image: { txtExec: function(caller, selected) {
					var url = prompt(this._("Enter the image URL:"), selected);

					if(url)
						this.insertText("[img]" + url + "[/img]");
				} },
				email: { txtExec: function(caller, selected) {
					var	display = selected && selected.indexOf('@') > -1 ? null : selected,
						email	= prompt(this._("Enter the e-mail address:"), (display ? '' : selected));
					if (email)
					{
						var text	= prompt(this._("Enter the displayed text:"), display || email) || email;
						this.insertText("[email=" + email + "]" + text + "[/email]");
					}
				} },
				link: { txtExec: function(caller, selected) {
					var	display = selected && selected.indexOf('http://') > -1 ? null : selected,
						url	= prompt(this._("Enter URL:"), (display ? 'http://' : selected));
					if (url)
					{
						var text	= prompt(this._("Enter the displayed text:"), display || url) || url;
						this.insertText("[url=" + url + "]" + text + "[/url]");
					}
				} },
				quote: { txtExec: ["[quote]", "[/quote]"] },
				youtube: { txtExec: function(caller) {
					var editor = this;

					$.sceditor.command.get('youtube')._dropDown(
						editor,
						caller,
						function(id) {
							editor.insertText("[youtube]" + id + "[/youtube]");
						}
					);
				} },
				rtl: { txtExec: ["[rtl]", "[/rtl]"] },
				ltr: { txtExec: ["[ltr]", "[/ltr]"] }
			};

			return $.extend(true, {}, merge, $.sceditor.commands);
		};

		/**
		 * Populates tagsToBbcodes and stylesToBbcodes to enable faster lookups
		 *
		 * @private
		 */
		buildBbcodeCache = function() {
			$.each(base.bbcodes, function(bbcode, info) {
				if(typeof base.bbcodes[bbcode].tags !== "undefined")
					$.each(base.bbcodes[bbcode].tags, function(tag, values) {
						var isBlock = !!base.bbcodes[bbcode].isBlock;
						tagsToBbcodes[tag] = (tagsToBbcodes[tag] || {});
						tagsToBbcodes[tag][isBlock] = (tagsToBbcodes[tag][isBlock] || {});
						tagsToBbcodes[tag][isBlock][bbcode] = values;
					});

				if(typeof base.bbcodes[bbcode].styles !== "undefined")
					$.each(base.bbcodes[bbcode].styles, function(style, values) {
						var isBlock = !!base.bbcodes[bbcode].isBlock;
						stylesToBbcodes[isBlock] = (stylesToBbcodes[isBlock] || {});
						stylesToBbcodes[isBlock][style] = (stylesToBbcodes[isBlock][style] || {});
						stylesToBbcodes[isBlock][style][bbcode] = values;
					});
			});
		};

		getStyle = function(element, property) {
			var	name = $.camelCase(property),
				$elm, ret, dir;

			// add exception for align
			if("text-align" === property)
			{
				$elm = $(element);
			
				if(!element.style)
					return null;

				if($elm.parent().css(property) !== $elm.css(property) &&
					$elm.css('display') === "block" && !$elm.is('hr') && !$elm.is('th'))
					ret = $elm.css(property);

				// IE changes text-align to the same as direction so skip unless overried by user
				dir = element.style.direction;
				if(dir && ((/right/i.test(ret) && dir === 'rtl') || (/left/i.test(ret) && dir === 'ltr')))
					return null;

				return ret;
			}

			if(element.style)
				return element.style[name];

			return null;
		};

		isEmpty = function(element) {
			var	childNodes = element.childNodes,
				i = childNodes.length;

			if(element.nodeValue && /\S|\u00A0/.test(element.nodeValue))
				return false;

			if(childNodes.length === 0 || (childNodes.length === 1 && (/br/i.test(childNodes[0].nodeName) || isEmpty(childNodes[0]))))
				return true;

			while(i--)
				if(!isEmpty(childNodes[i]))
					return false;

			return true;
		};

		/**
		 * Checks if any bbcode styles match the elements styles
		 *
		 * @private
		 * @return string Content with any matching bbcode tags wrapped around it.
		 * @Private
		 */
		handleStyles = function(element, content, blockLevel) {
			var	elementPropVal;

			// convert blockLevel to boolean
			blockLevel = !!blockLevel;

			if(!stylesToBbcodes[blockLevel])
				return content;

			$.each(stylesToBbcodes[blockLevel], function(property, bbcodes) {
				elementPropVal = getStyle(element[0], property);

				// if the parent has the same style use that instead of this one
				// so you dont end up with [i]parent[i]child[/i][/i]
				if(!elementPropVal || getStyle(element.parent()[0], property) === elementPropVal)
					return;

				$.each(bbcodes, function(bbcode, values) {
					if(!/\S|\u00A0/.test(content) && !base.bbcodes[bbcode].allowsEmpty && isEmpty(element[0]))
						return;

					if(!values || $.inArray(elementPropVal.toString(), values) > -1) {
						if($.isFunction(base.bbcodes[bbcode].format))
							content = base.bbcodes[bbcode].format.call(base, element, content);
						else
							content = formatString(base.bbcodes[bbcode].format, content);
					}
				});
			});

			return content;
		};

		/**
		 * Handles a HTML tag and finds any matching bbcodes
		 *
		 * @private
		 * @param	jQuery element	element		The element to convert
		 * @param	string			content		The Tags text content
		 * @param	bool			blockLevel	If to convert block level tags
		 * @return	string	Content with any matching bbcode tags wrapped around it.
		 * @Private
		 */
		handleTags = function(element, content, blockLevel) {
			var tag = element[0].nodeName.toLowerCase();

			// convert blockLevel to boolean
			blockLevel = !!blockLevel;

			if(tagsToBbcodes[tag] && tagsToBbcodes[tag][blockLevel]) {
				// loop all bbcodes for this tag
				$.each(tagsToBbcodes[tag][blockLevel], function(bbcode, bbcodeAttribs) {
					if(!/\S|\u00A0/.test(content) && !base.bbcodes[bbcode].allowsEmpty && isEmpty(element[0]))
						return;

					// if the bbcode requires any attributes then check this has
					// all needed
					if(bbcodeAttribs) {
						var runBbcode = false;

						// loop all the bbcode attribs
						$.each(bbcodeAttribs, function(attrib, values)
						{
							// if the element has the bbcodes attribute and the bbcode attribute
							// has values check one of the values matches
							if(!element.attr(attrib) || (values && $.inArray(element.attr(attrib), values) < 0))
								return;

							// break this loop as we have matched this bbcode
							runBbcode = true;
							return false;
						});

						if(!runBbcode)
							return;
					}

					if($.isFunction(base.bbcodes[bbcode].format))
						content = base.bbcodes[bbcode].format.call(base, element, content);
					else
						content = formatString(base.bbcodes[bbcode].format, content);
				});
			}

			// add newline after paragraph elements p and div (WebKit uses divs) and br tags
			if(blockLevel && /^(br|div|p)$/.test(tag))
			{
				// Only treat divs/p as a newline if their last child was not a new line.
				if(!(/^(div|p)$/i.test(tag) && element[0].lastChild && element[0].lastChild.nodeName.toLowerCase() === "br"))
					content += "\n";

				// needed for browsers that enter textnode then when return is pressed put the rest in a div, i.e.:
				// text<div>line 2</div>
				if("br" !== tag && !$.sceditor.dom.isInline(element[0].parentNode) && element[0].previousSibling &&
					element[0].previousSibling.nodeType === 3) {
					content = "\n" + content;
				}
			}

			return content;
		};

		/**
		 * Formats a string in the format
		 * {0}, {1}, {2}, ect. with the params provided
		 * @private
		 * @return string
		 * @Private
		 */
		formatString = function() {
			var args = arguments;
			return args[0].replace(/\{(\d+)\}/g, function(str, p1) {
				return typeof args[p1-0+1] !== "undefined" ?
					args[p1-0+1] :
					'{' + p1 + '}';
			});
		};

		/**
		 * Removes any leading or trailing quotes ('")
		 *
		 * @return string
		 * @memberOf jQuery.sceditorBBCodePlugin.prototype
		 */
		base.stripQuotes = function(str) {
			return str.replace(/^(["'])(.*?)\1$/, "$2");
		};

		/**
		 * Converts HTML to BBCode
		 * @param string	html	Html string, this function ignores this, it works off domBody
		 * @param HtmlElement	domBody	Editors dom body object to convert
		 * @return string BBCode which has been converted from HTML
		 * @memberOf jQuery.sceditorBBCodePlugin.prototype
		 */
		base.getHtmlHandler = function(html, domBody) {
			$.sceditor.dom.removeWhiteSpace(domBody[0]);

			return $.trim(base.elementToBbcode(domBody));
		};

		/**
		 * Converts a HTML dom element to BBCode starting from
		 * the innermost element and working backwards
		 *
		 * @private
		 * @param HtmlElement	element		The element to convert to BBCode
		 * @param array			vChildren	Valid child tags allowed
		 * @return string BBCode
		 * @memberOf jQuery.sceditorBBCodePlugin.prototype
		 */
		base.elementToBbcode = function($element) {
			return (function toBBCode(node, vChildren) {
				var ret = '';

				$.sceditor.dom.traverse(node, function(node) {
					var	$node		= $(node),
						curTag		= '',
						tag		= node.nodeName.toLowerCase(),
						vChild		= validChildren[tag],
						isValidChild	= true;

					if(typeof vChildren === 'object')
					{
						isValidChild = $.inArray(tag, vChildren) > -1;

						// if this tag is one of the parents allowed children
						// then set this tags allowed children to whatever it allows,
						// otherwise set to what the parent allows
						if(!isValidChild)
							vChild = vChildren;
					}

					// 3 is text element
					if(node.nodeType !== 3)
					{
						// skip ignored elments
						if($node.hasClass("sceditor-ignore"))
							return;

						// don't loop inside iframes
						if(tag !== 'iframe')
							curTag = toBBCode(node, vChild);

						if(isValidChild)
						{
							// code tags should skip most styles
							if(!$node.is('code'))
							{
								// handle inline bbcodes
								curTag = handleStyles($node, curTag);
								curTag = handleTags($node, curTag);

								// handle blocklevel bbcodes
								curTag = handleStyles($node, curTag, true);
							}

							ret += handleTags($node, curTag, true);
						}
						else
							ret += curTag;
					}
					else if(node.wholeText && (!node.previousSibling || node.previousSibling.nodeType !== 3))
					{
						if($(node).parents('code').length === 0)
							ret += node.wholeText.replace(/ +/g, " ");
						else
							ret += node.wholeText;
					}
					else if(!node.wholeText)
						ret += node.nodeValue;
				}, false, true);

				return ret;
			}($element.get(0)));
		};

		/**
		 * Converts BBCode to HTML
		 *
		 * @param {String} text
		 * @param {Bool} isFragment
		 * @return {String} HTML
		 * @memberOf jQuery.sceditorBBCodePlugin.prototype
		 */
		base.getTextHandler = function(text, isFragment) {

			var	oldText, replaceBBCodeFunc,
// Previous				bbcodeRegex = /\[([^\[\s=]*?)(?:([\s=][^\[]*?))?\]((?:[\s\S(?!=\[\\\1)](?!\[\1))*?)\[\/(\1)\]/g,
				bbcodeRegex = /\[([^\[\s=]+)(?:([\s=][^\[\]]+))?\]((?:[\s\S](?!\[\1))*?)\[\/(\1)\]/gi,
				atribsRegex = /(\S+)=((?:(?:(["'])(?:\\\3|[^\3])*?\3))|(?:[^'"\s]+))/gi;

			replaceBBCodeFunc = function(str, bbcode, attrs, content)
			{
				var	attrsMap = {},
					matches;

				bbcode = bbcode.toLowerCase();

				if(attrs)
				{
					attrs = $.trim(attrs);

					// if only one attribute then remove the = from the start and strip any quotes
					if((attrs.charAt(0) === "=" && (attrs.split("=").length - 1) <= 1) || bbcode === 'url')
						attrsMap.defaultattr = base.stripQuotes(attrs.substr(1));
					else
					{
						if(attrs.charAt(0) === "=")
							attrs = "defaultattr" + attrs;

						if (typeof base.bbcodes[bbcode] != 'undefined' && typeof base.bbcodes[bbcode].attrs == 'function')
						{
							var declaredAttrs = base.bbcodes[bbcode].attrs();
							var attrArray = new Array;
							var compatArray = new Array;
							for (var i = 0; i < declaredAttrs.length; i++)
							{
								var attrPos = attrs.indexOf(declaredAttrs[i]);
								if (attrPos != -1)
								{
									attrArray[attrPos] = [declaredAttrs[i], attrPos + declaredAttrs[i].length + 1];
								}
							}

							for (var attrElem in attrArray)
								compatArray.push(attrArray[attrElem]);
							for (var i = 0; i < compatArray.length; i++)
							{
								if (typeof compatArray[i+1] != 'undefined')
									attrsMap[compatArray[i][0].toLowerCase()] = attrs.substr(compatArray[i][1], attrs.indexOf(compatArray[i+1][0]) - compatArray[i][1]).trim();
								else
									attrsMap[compatArray[i][0].toLowerCase()] = attrs.substr(compatArray[i][1], attrs.length);
							}
						}
						else
							while((matches = atribsRegex.exec(attrs)))
								attrsMap[matches[1].toLowerCase()] = base.stripQuotes(matches[2]);
					}
				}

				if(!base.bbcodes[bbcode])
					return str;

				if($.isFunction(base.bbcodes[bbcode].html))
					return base.bbcodes[bbcode].html.call(base, bbcode, attrsMap, content);
				else
					return formatString(base.bbcodes[bbcode].html, content);
			};

			text = text.replace(/&/g, "&amp;")
					.replace(/</g, "&lt;")
					.replace(/>/g, "&gt;")
					.replace(/\r/g, "")
					.replace(/(\[\/?(?:left|center|right|justify|align|rtl|ltr)\])\n/g, "$1")
					.replace(/\n/g, "<br />");

			while(text !== oldText)
			{
				oldText = text;
				text    = text.replace(bbcodeRegex, replaceBBCodeFunc);
			}

			// As hr is the only bbcode not to have a start and end tag it's
			// just being replace here instead of adding support for it above.
			text = text.replace(/\[hr\]/gi, "<hr>")
					.replace(/\[\*\]/gi, "<li>");

			// replace multi-spaces which are not inside tags with a non-breaking space
			// to preserve them. Otherwise they will just be converted to 1!
			text = text.replace(/ {2}(?=([^<\>]*?<|[^<\>]*?$))/g, " &nbsp;");

			return wrapInDivs(text, isFragment);
		};

		/**
		 * Wraps divs around inline HTML. Needed for IE
		 *
		 * @param string html
		 * @return string HTML
		 * @private
		 */
		wrapInDivs = function(html, excludeFirstLast)
		{
			var	d		= document,
				inlineFrag	= d.createDocumentFragment(),
				outputDiv	= d.createElement('div'),
				tmpDiv		= d.createElement('div'),
				div, node, next, nodeName;

			$(tmpDiv).hide().appendTo(d.body);
			tmpDiv.innerHTML = html;

			node = tmpDiv.firstChild;
			while(node)
			{
				next = node.nextSibling;
				nodeName = node.nodeName.toLowerCase();

				if((node.nodeType === 1 && !$.sceditor.dom.isInline(node)) || nodeName === "br")
				{
					if(inlineFrag.childNodes.length > 0 || nodeName === "br")
					{
						div = d.createElement('div');
						div.appendChild(inlineFrag);

						// Putting BR in a div in IE9 causes it to do a double line break,
						// as much as I hate browser UA sniffing, to do feature detection would
						// be more code than it's worth for this specific bug.
						if(nodeName === "br" && !$.sceditor.ie)
							div.appendChild(d.createElement('br'));

						// If it's an empty DIV and in compatibility mode is below IE8 then
						// we must add a non-breaking space to the div otherwise the div
						// will be collapsed. Adding a BR works but when you press enter
						// to make a newline it suddenly goes back to the normal IE div
						// behaviour and creates two lines, one for the newline and one
						// for the BR. I'm sure there must be a better fix but I've yet to
						// find one.
						// Cannot do zoom: 1; or set a height on the div to fix it as that
						// causes resize handles to be added to the div when it's clicked on/
						if(!div.childNodes.length && (d.documentMode && d.documentMode < 8 || $.sceditor.ie < 8))
							div.appendChild(d.createTextNode('\u00a0'));

						outputDiv.appendChild(div);
						inlineFrag = d.createDocumentFragment();
					}

					if(nodeName !== "br")
						outputDiv.appendChild(node);
				}
				else
					inlineFrag.appendChild(node);

				node = next;
			}

			if(inlineFrag.childNodes.length > 0)
			{
				div = d.createElement('div');
				div.appendChild(inlineFrag);
				outputDiv.appendChild(div);
			}

			// needed for paste, the first shouldn't be wrapped in a div
			if(excludeFirstLast)
			{
				node = outputDiv.firstChild;
				if(node && node.nodeName.toLowerCase() === "div")
				{
					while((next = node.firstChild))
						outputDiv.insertBefore(next, node);

					if($.sceditor.ie >= 9)
						outputDiv.insertBefore(d.createElement('br'), node);

					outputDiv.removeChild(node);
				}

				node = outputDiv.lastChild;
				if(node && node.nodeName.toLowerCase() === "div")
				{
					while((next = node.firstChild))
						outputDiv.insertBefore(next, node);

					if($.sceditor.ie >= 9)
						outputDiv.insertBefore(d.createElement('br'), node);

					outputDiv.removeChild(node);
				}
			}

			$(tmpDiv).remove();
			return outputDiv.innerHTML;
		};

		init();
	};

	$.sceditorBBCodePlugin.bbcodes = {
		// START_COMMAND: Bold
		b: {
			tags: {
				b: null,
				strong: null
			},
			styles: {
				// 401 is for FF 3.5
				"font-weight": ["bold", "bolder", "401", "700", "800", "900"]
			},
			format: "[b]{0}[/b]",
			html: '<strong>{0}</strong>'
		},
		// END_COMMAND

		// START_COMMAND: Italic
		i: {
			tags: {
				i: null,
				em: null
			},
			styles: {
				"font-style": ["italic", "oblique"]
			},
			format: "[i]{0}[/i]",
			html: '<em>{0}</em>'
		},
		// END_COMMAND

		// START_COMMAND: Underline
		u: {
			tags: {
				u: null
			},
			styles: {
				"text-decoration": ["underline"]
			},
			format: "[u]{0}[/u]",
			html: '<u>{0}</u>'
		},
		// END_COMMAND

		// START_COMMAND: Strikethrough
		s: {
			tags: {
				s: null,
				strike: null
			},
			styles: {
				"text-decoration": ["line-through"]
			},
			format: "[s]{0}[/s]",
			html: '<s>{0}</s>'
		},
		// END_COMMAND

		// START_COMMAND: Subscript
		sub: {
			tags: {
				sub: null
			},
			format: "[sub]{0}[/sub]",
			html: '<sub>{0}</sub>'
		},
		// END_COMMAND

		// START_COMMAND: Superscript
		sup: {
			tags: {
				sup: null
			},
			format: "[sup]{0}[/sup]",
			html: '<sup>{0}</sup>'
		},
		// END_COMMAND

		// START_COMMAND: Font
		font: {
			tags: {
				font: {
					face: null
				}
			},
			styles: {
				"font-family": null
			},
			format: function(element, content) {
				if(element[0].nodeName.toLowerCase() === "font" && element.attr('face'))
					return '[font=' + this.stripQuotes(element.attr('face')) + ']' + content + '[/font]';

				return '[font=' + this.stripQuotes(element.css('font-family')) + ']' + content + '[/font]';
			},
			html: function(element, attrs, content) {
				return '<font face="' + attrs.defaultattr + '">' + content + '</font>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Size
		size: {
			tags: {
				font: {
					size: null
				}
			},
			styles: {
				"font-size": null
			},
			format: function(element, content) {
				var	fontSize = element.css('fontSize'),
					size     = 1;

				if(element.attr('size'))
					size = element.attr('size');
				// Most browsers return px value but IE returns 1-7
				else if(fontSize.indexOf("px") > -1) {
					// convert size to an int
					fontSize = fontSize.replace("px", "") - 0;

					if(fontSize > 12)
						size = 2;
					if(fontSize > 15)
						size = 3;
					if(fontSize > 17)
						size = 4;
					if(fontSize > 23)
						size = 5;
					if(fontSize > 31)
						size = 6;
					if(fontSize > 47)
						size = 7;
				}
				else
					size = fontSize;

				return '[size=' + size + ']' + content + '[/size]';
			},
			html: function(element, attrs, content) {
				return '<font size="' + attrs.defaultattr + '">' + content + '</font>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Color
		color: {
			tags: {
				font: {
					color: null
				}
			},
			styles: {
				color: null
			},
			format: function(element, content) {
				/**
				 * Converts CSS rgb value into hex
				 * @private
				 * @return string Hex color
				 */
				var rgbToHex = function(rgbStr) {
					var m;

					function toHex(n) {
						n = parseInt(n,10);
						if(isNaN(n))
							return "00";
						n = Math.max(0,Math.min(n,255)).toString(16);

						return n.length<2 ? '0'+n : n;
					}

					// rgb(n,n,n);
					if((m = rgbStr.match(/rgb\((\d+),\s*?(\d+),\s*?(\d+)\)/i)))
						return '#' + toHex(m[1]) + toHex(m[2]-0) + toHex(m[3]-0);

					// expand shorthand
					if((m = rgbStr.match(/#([0-f])([0-f])([0-f])\s*?$/i)))
						return '#' + m[1] + m[1] + m[2] + m[2] + m[3] + m[3];

					return rgbStr;
				};

				var color = element.css('color');

				if(element[0].nodeName.toLowerCase() === "font" && element.attr('color'))
					color = element.attr('color');

				color = rgbToHex(color);

				return '[color=' + color + ']' + content + '[/color]';
			},
			html: function(element, attrs, content) {
				return '<font color="' + attrs.defaultattr + '">' + content + '</font>';
			}
		},
		black: {
			html: '<font color="black">{0}</font>'
		},
		blue: {
			html: '<font color="blue">{0}</font>'
		},
		green: {
			html: '<font color="green">{0}</font>'
		},
		red: {
			html: '<font color="red">{0}</font>'
		},
		white: {
			html: '<font color="white">{0}</font>'
		},
		// END_COMMAND

		// START_COMMAND: Lists
		list: {
			isBlock: true,
			html: function(element, attrs, content) {
				var style = '';
				var code = 'ul';
				if (attrs.type)
						style = ' style="list-style-type: ' + attrs.type + '"';

				return '<' + code + style + '>' + content + '</' + code + '>';
			}
		},
		ul: {
			tags: {
				ul: null
			},
			isBlock: true,
			format: function(element, content) {
				if ($(element[0]).css('list-style-type') == 'disc')
					return '[list]' + content + '[/list]';
				else
					return '[list type=' + $(element[0]).css('list-style-type') + ']' + content + '[/list]';
			},
			html: '<ul>{0}</ul>'
		},
		ol: {
			tags: {
				ol: null
			},
			isBlock: true,
			format: '[list type=decimal]{0}[/list]',
			html: '<ol>{0}</ol>'
		},
		li: {
			tags: {
				li: null
			},
			format: "[li]{0}[/li]",
			html: '<li>{0}</li>'
		},
		"*": {
			html: '<li>{0}</li>'
		},
		// END_COMMAND

		// START_COMMAND: Table
		table: {
			tags: {
				table: null
			},
			format: "[table]{0}[/table]",
			html: '<table>{0}</table>'
		},
		tr: {
			tags: {
				tr: null
			},
			format: "[tr]{0}[/tr]",
			html: '<tr>{0}</tr>'
		},
		th: {
			tags: {
				th: null
			},
			isBlock: true,
			format: "[th]{0}[/th]",
			html: '<th>{0}</th>'
		},
		td: {
			tags: {
				td: null
			},
			isBlock: true,
			format: "[td]{0}[/td]",
			html: '<td>{0}<br class="sceditor-ignore" /></td>'
		},
		// END_COMMAND

		// START_COMMAND: Emoticons
		emoticon: {
			allowsEmpty: true,
			tags: {
				img: {
					src: null,
					"data-sceditor-emoticon": null
				}
			},
			format: function(element, content) {
				if (element.attr('data-sceditor-emoticon') == '')
					return content;
				return element.attr('data-sceditor-emoticon') + content;
			},
			html: '{0}'
		},
		// END_COMMAND

		// START_COMMAND: Horizontal Rule
		horizontalrule: {
			allowsEmpty: true,
			tags: {
				hr: null
			},
			format: "[hr]{0}",
			html: "<hr />"
		},
		// END_COMMAND

		// START_COMMAND: Image
		img: {
			allowsEmpty: true,
			tags: {
				img: {
					src: null
				}
			},
			format: function(element, content) {
				var	attribs = '',
					style = function(name) {
						return element.style ? element.style[name] : null;
					};

				// check if this is an emoticon image
				if(typeof element.attr('data-sceditor-emoticon') !== "undefined")
					return content;

				var width = ' width=' + $(element).width();
				var height = ' height=' + $(element).height();
				var alt = $(element).attr('alt') != undefined ? ' alt=' + $(element).attr('alt').php_unhtmlspecialchars() : '';

				return '[img' + width + height + alt + ']' + element.attr('src') + '[/img]';
			},
			attrs: function () {
				return ['alt', 'width', 'height'];
			},
			html: function(element, attrs, content) {
				var attribs = "", parts;

				// handle [img width=340 height=240]url[/img]
				if(typeof attrs.width !== "undefined")
					attribs += ' width="' + attrs.width + '"';
				if(typeof attrs.height !== "undefined")
					attribs += ' height="' + attrs.height + '"';
				if(typeof attrs.alt !== "undefined")
					attribs += ' alt="' + attrs.alt + '"';

				return '<img ' + attribs + ' src="' + content + '" />';
			}
		},
		// END_COMMAND

		// START_COMMAND: URL
		url: {
			allowsEmpty: true,
			tags: {
				a: {
					href: null
				}
			},
			format: function(element, content) {
				// make sure this link is not an e-mail, if it is return e-mail BBCode
				if(element.attr('href').substr(0, 7) === 'mailto:')
					return '[email=' + element.attr('href').substr(7) + ']' + content + '[/email]';
				// make sure this link is not an ftp, if it is return ftp BBCode
				else if(element.attr('href').substr(0, 3) === 'ftp')
					return '[ftp=' + element.attr('href') + ']' + content + '[/ftp]';

				if(element.attr('target') !== undefined)
					return '[url=' + decodeURI(element.attr('href')) + ']' + content + '[/url]';
				else
					return '[iurl=' + decodeURI(element.attr('href')) + ']' + content + '[/iurl]';
			},
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					attrs.defaultattr = content;

				return '<a target="_blank" href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
			}
		},
		iurl: {
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					attrs.defaultattr = content;

				return '<a href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
			}
		},
		ftp: {
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					attrs.defaultattr = content;

				return '<a target="_blank" href="' + encodeURI(attrs.defaultattr) + '">' + content + '</a>';
			}
		},
		// END_COMMAND

		// START_COMMAND: E-mail
		email: {
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined")
					attrs.defaultattr = content;

				return '<a href="mailto:' + attrs.defaultattr + '">' + content + '</a>';
			}
		},
		// END_COMMAND

		// START_COMMAND: Quote
		quote: {
			tags: {
				blockquote: null,
				cite: null
			},
			isBlock: true,
			format: function(element, content) {
				var author = '';
				var date = '';
				var link = '';

				if (element[0].tagName.toLowerCase() == 'cite')
					return '';

				if ($(element).attr('author'))
					author = ' author=' + $(element).attr('author').php_unhtmlspecialchars();
				if ($(element).attr('date'))
					date = ' date=' + $(element).attr('date');
				if ($(element).attr('link'))
					link = ' link=' + $(element).attr('link');

				return '[quote' + author + date + link + ']' + content + '[/quote]';
			},
			attrs: function () {
				return ['author', 'date', 'link'];
			},
			html: function(element, attrs, content) {
				var attr_author = '', author = '';
				var attr_date = '', sDate = '';
				var attr_link = '', link = '';
				if(typeof attrs.author !== "undefined" && attrs.author)
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

				if(typeof attrs.date !== "undefined" && attrs.date)
				{
					attr_date = attrs.date;
					sDate = '<date timestamp="' + attr_date + '">' + new Date(attrs.date * 1000) + '</date>';
				}

				if (author == '' && sDate == '')
					author = bbc_quote;
				else
					author += ' ' + bbc_search_on;

				content = '<blockquote author="' + attr_author + '" date="' + attr_date + '" link="' + attr_link + '"><cite>' + author + ' ' + sDate + '</cite>' + content + '</blockquote>';

				return content;
			}
		},
		// END_COMMAND

		// START_COMMAND: Code
		code: {
			tags: {
				code: null
			},
			isBlock: true,
			format: function(element, content) {
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
					if ($(element).attr('from') != undefined)
					{
						from = '=' + $(element).attr('from').php_unhtmlspecialchars();
					}
				}

				return '[code' + from + ']' + content.replace('&#91;', '[') + '[/code]';
			},
			html:  function(element, attrs, content) {
				var from = '';
				if(typeof attrs.defaultattr !== "undefined")
					from = '<cite>' + attrs.defaultattr + '</cite>';

				return '<code>' + from + content.replace('[', '&#91;') + '</code>'
			}
		},
		php: {
			isBlock: true,
			format: "[php]{0}[/php]",
			html: '<code class="php">{0}</code>'
		},
		// END_COMMAND


		// START_COMMAND: Left
		left: {
			styles: {
				"text-align": ["left", "-webkit-left", "-moz-left", "-khtml-left"]
			},
			isBlock: true,
			format: "[left]{0}[/left]",
			html: '<div align="left">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Centre
		center: {
			styles: {
				"text-align": ["center", "-webkit-center", "-moz-center", "-khtml-center"]
			},
			isBlock: true,
			format: "[center]{0}[/center]",
			html: '<div align="center">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Right
		right: {
			styles: {
				"text-align": ["right", "-webkit-right", "-moz-right", "-khtml-right"]
			},
			isBlock: true,
			format: "[right]{0}[/right]",
			html: '<div align="right">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Justify
		justify: {
			styles: {
				"text-align": ["justify", "-webkit-justify", "-moz-justify", "-khtml-justify"]
			},
			isBlock: true,
			format: "[justify]{0}[/justify]",
			html: '<div align="justify">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: YouTube
		youtube: {
			allowsEmpty: true,
			tags: {
				iframe: {
					'data-youtube-id': null
				}
			},
			format: function(element, content) {
				if(!element.attr('data-youtube-id'))
					return content;

				return '[youtube]' + element.attr('data-youtube-id') + '[/youtube]';
			},
			html: '<iframe width="560" height="315" src="http://www.youtube.com/embed/{0}?wmode=opaque' +
				'" data-youtube-id="{0}" frameborder="0" allowfullscreen></iframe>'
		},
		// END_COMMAND


		// START_COMMAND: Rtl
		rtl: {
			styles: {
				"direction": ["rtl"]
			},
			format: "[rtl]{0}[/rtl]",
			html: '<div style="direction: rtl">{0}</div>'
		},
		// END_COMMAND

		// START_COMMAND: Ltr
		ltr: {
			styles: {
				"direction": ["ltr"]
			},
			format: "[ltr]{0}[/ltr]",
			html: '<div style="direction: ltr">{0}</div>'
		},
		// END_COMMAND

		abbr: {
			tags: {
				abbr: {
					title: null
				}
			},
			format: function(element, content) {
				return '[abbr=' + element.attr('title') + ']' + content + '[/abbr]';
			},
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					return content;

				return '<abbr title="' + attrs.defaultattr + '">' + content + '</abbr>';
			}
		},
		acronym: {
			tags: {
				acronym: {
					title: null
				}
			},
			format: function(element, content) {
				return '[acronym=' + element.attr('title') + ']' + content + '[/acronym]';
			},
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					return content;

				return '<acronym title="' + attrs.defaultattr + '">' + content + '</acronym>';
			}
		},
		bdo: {
			tags: {
				bdo: {
					dir: null
				}
			},
			format: function(element, content) {
				return '[bdo=' + element.attr('dir') + ']' + content + '[/bdo]';
			},
			html: function(element, attrs, content) {
				if(typeof attrs.defaultattr === "undefined" || attrs.defaultattr.length === 0)
					return content;
				if (attrs.defaultattr != 'rtl' && attrs.defaultattr != 'ltr')
					return '[bdo=' + attrs.defaultattr + ']' + content + '[/bdo]';

				return '<bdo dir="' + attrs.defaultattr + '">' + content + '</bdo>';
			}
		},
		tt: {
			tags: {
				tt: null
			},
			format: "[tt]{0}[/tt]",
			html: '<tt>{0}</tt>'
		},
		pre: {
			tags: {
				pre: null
			},
			isBlock: true,
			format: "[pre]{0}[/pre]",
			html: "<pre>{0}</pre>\n"
		},
		move: {
			tags: {
				marquee: null
			},
			format: "[move]{0}[/move]",
			html: '<marquee>{0}</marquee>'
		},

		// this is here so that commands above can be removed
		// without having to remove the , after the last one.
		// Needed for IE.
		ignore: {}
	};

	/**
	 * Static BBCode helper class
	 * @class command
	 * @name jQuery.sceditorBBCodePlugin.bbcode
	 */
	$.sceditorBBCodePlugin.bbcode =
	/** @lends jQuery.sceditorBBCodePlugin.bbcode */
	{
		/**
		 * Gets a BBCode
		 *
		 * @param {String} name
		 * @return {Object|null}
		 * @since v1.3.5
		 */
		get: function(name) {
			return $.sceditorBBCodePlugin.bbcodes[name] || null;
		},

		/**
		 * <p>Adds a BBCode to the parser or updates an exisiting
		 * BBCode if a BBCode with the specified name already exists.</p>
		 *
		 * @param {String} name
		 * @param {Object} bbcode
		 * @return {this|false} Returns false if name or bbcode is false
		 * @since v1.3.5
		 */
		set: function(name, bbcode) {
			if(!name || !bbcode)
				return false;

			// merge any existing command properties
			bbcode = $.extend($.sceditorBBCodePlugin.bbcodes[name] || {}, bbcode);

			bbcode.remove = function() { $.sceditorBBCodePlugin.bbcode.remove(name); };

			$.sceditorBBCodePlugin.bbcodes[name] = bbcode;
			return this;
		},

		/**
		 * Removes a BBCode
		 *
		 * @param {String} name
		 * @return {this}
		 * @since v1.3.5
		 */
		remove: function(name) {
			if($.sceditorBBCodePlugin.bbcodes[name])
				delete $.sceditorBBCodePlugin.bbcodes[name];

			return this;
		}
	};

	/**
	 * Checks if a command with the specified name exists
	 *
	 * @param string name
	 * @return bool
	 * @deprecated Since v1.3.5
	 * @memberOf jQuery.sceditorBBCodePlugin
	 */
	$.sceditorBBCodePlugin.commandExists = function(name) {
		return !!$.sceditorBBCodePlugin.bbcode.get(name);
	};

	/**
	 * Adds/updates a BBCode.
	 *
	 * @param String		name		The BBCode name
	 * @param Object		tags		Any html tags this bbcode applies to, i.e. strong for [b]
	 * @param Object		styles		Any style properties this applies to, i.e. font-weight for [b]
	 * @param String|Function	format		Function or string to convert the element into BBCode
	 * @param String|Function	html		String or function to format the BBCode back into HTML.
	 * @param bool			allowsEmpty	If this BBCodes is allowed to be empty, e.g. [b][/b]
	 * @return Bool
	 * @deprecated Since v1.3.5
	 * @memberOf jQuery.sceditorBBCodePlugin
	 */
	$.sceditorBBCodePlugin.setCommand = function(name, tags, styles, format, html, allowsEmpty, isBlock) {
		return $.sceditorBBCodePlugin.bbcode.set(name,
		{
			tags: tags || {},
			styles: styles || {},
			allowsEmpty: allowsEmpty,
			isBlock: isBlock,
			format: format,
			html: html
		});
	};

	$.fn.sceditorBBCodePlugin = function(options) {
		if((!options || !options.runWithoutWysiwygSupport) && !$.sceditor.isWysiwygSupported)
			return;

		return this.each(function() {
			(new $.sceditorBBCodePlugin(this, options));
		});
	};
})(jQuery);
