/**
 * SCEditor Auto Youtube Plugin
 * http://www.sceditor.com/
 *
 * Copyright (C) 2016, Sam Clarke (samclarke.com)
 *
 * SCEditor is licensed under the MIT license:
 *	http://www.opensource.org/licenses/mit-license.php
 *
 * @author Sam Clarke
 */
(function (document, sceditor) {
	'use strict';

	var dom = sceditor.dom;

	/*
		(^|\s)					Start of line or space
		(?:https?:\/\/)?  		Optional scheme like http://
		(?:www\.)?      		Optional www. prefix
		(?:
			youtu\.be\/     	Ends with .be/ so whatever comes next is the ID
		|
			youtube\.com\/watch\?v=		Matches the .com version
		)
		([^"&?\/ ]{11}) 				The actual YT ID
		(?:\&[\&_\?0-9a-z\#]+)?			Any extra URL params
		(\s|$)							End of line or space
	*/
	var ytUrlRegex = /(^|\s)(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/watch\?v=)([^"&?\/ ]{11})(?:\&[\&_\?0-9a-z\#]+)?(\s|$)/i;

	function youtubeEmbedCode(id) {
		return '<div class="videocontainer"><div><iframe frameborder="0" ' +
			'src="https://www.youtube-nocookie.com/embed/' + id + '" ' +
			'data-youtube-id="' + id + '" allowfullscreen></iframe></div></div>';
	}

	function convertYoutubeLinks(root) {
		var node = root.firstChild;

		while (node) {
			// 3 is TextNodes
			if (node.nodeType === 3) {
				var text   = node.nodeValue;
				var parent = node.parentNode;
				var match  = text.match(ytUrlRegex);

				if (match) {
					parent.insertBefore(document.createTextNode(
						text.substr(0, match.index) + match[1]
					), node);

					parent.insertBefore(
						dom.parseHTML(youtubeEmbedCode(match[2])), node
					);

					node.nodeValue = match[3] +
						text.substr(match.index + match[0].length);
				}
			} else {
				// TODO: Make this tag configurable.
				if (!dom.is(node, 'code')) {
					convertYoutubeLinks(node);
				}
			}

			node = node.nextSibling;
		}
	};

	sceditor.plugins.autoyoutube = function () {
		this.signalPasteRaw = function (data) {
			// TODO: Make this tag configurable.
			// Skip code tags
			if (dom.closest(this.currentNode(), 'code')) {
				return;
			}

			if (data.text) {
				var html = document.createElement('div');

				html.textContent = data.text;

				convertYoutubeLinks(html);

				data.html = html.innerHTML;
			}
		};
	};
})(document, sceditor);
