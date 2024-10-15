function getClosest(el, divID) {
	if (typeof divID == 'undefined' || divID == false)
		return null;

	do {
		if (el.nodeName === 'TEXTAREA' || el.nodeName === 'INPUT' || el.id === 'error_box')
			break;

		if (el.id === divID) {
			return el;
		}
	} while (el = el.parentNode);

	return null;
}

function getSelectedText(node) {
	const selection = window.getSelection();
	for (let i = 0; i < selection.rangeCount; i++) {
		const range = selection.getRangeAt(i);

		if (range.intersectsNode(node)) {
			const frag = range.cloneContents();
			const s = getClosest(range.startContainer, node.id);
			const e = getClosest(range.endContainer, node.id);

			if (s && e) {
				const container = document.createElement("div");
				container.appendChild(range.cloneContents());
				return container.innerHTML;
			} else {
				const el = frag.getElementById(node.id);
				return el?.innerHTML;
			}
		}
	}
}

let selectedText;

function quotedTextClick(msgID, e) {
	e.preventDefault();

	ajax_indicator(true);
	selectedText = selectedText.replaceAll(/<img src=".*?" alt="(.*?)" title=".*?" class="smiley">/, '$1');

	fetch(smf_prepareScriptUrl(smf_scripturl) + 'action=quotefast;quote=' + msgID + ';xml', {
		method: 'GET',
		headers: {
			"X-SMF-AJAX": 1
		},
		credentials: typeof allow_xhjr_credentials !== "undefined" ? 'include' : 'omit'
	}).finally (() => {
		ajax_indicator(false);
	}).then(response => {
		if (!response.ok) {
			throw new Error('Network response was not ok');
		}
		return response.text();
	}).then(data => (new DOMParser).parseFromString(data, 'text/xml').getElementsByTagName('quote')[0].textContent)
	.then(data => {
		const text = data.match(/^\[quote(.*)]/ig) + selectedText + '[/quote]' + '\n\n';
		const oEditor = sceditor.instance(document.getElementById(oEditorID));
		oEditor.insert(oEditor.toBBCode(text));

		// Manually move cursor to after the quote.
		var
			rangeHelper = oEditor.getRangeHelper(),
			parent = rangeHelper.parentNode();
		if (parent && parent.nodeName === 'BLOCKQUOTE') {
			var range = rangeHelper.selectedRange();
			range.setStartAfter(parent);
			rangeHelper.selectRange(range);
		}
		window.location.hash = '#' + oJumpAnchor;
		oEditor.focus();
	}).catch(error => {
		console.error('There has been a problem with your fetch operation:', error);
	});
}

addEventListener('load', () => {
	const els = document.querySelectorAll('.inner, .list_posts');

	for (const el of els) {
		const btn = document.getElementById('quoteSelected_' + el.dataset.msgid);
		btn.querySelector('a').addEventListener('click', quotedTextClick.bind(btn, el.dataset.msgid));
	}

	document.addEventListener('selectionchange', () => {
		for (const el of els) {
			selectedText = getSelectedText(el);
			const btn = document.getElementById('quoteSelected_' + el.dataset.msgid);
			btn.style.display = !selectedText ? 'none' : '';
		}
	});
});