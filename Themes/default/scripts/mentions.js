let fails = [];

const atwhoConfig = {
	at: '@',
	limit: 10,
	callbacks: {
		remoteFilter: (query, callback) => {
			if (!query || query.length < 2 || query.length > 60) return;

			// Check if query starts with any failed query prefix
			if (fails.some(fail => query.startsWith(fail))) return;

			const params = new URLSearchParams({
				action: 'suggest',
				search: query,
				'suggest_type': 'member',
				[smf_session_var]: smf_session_id
			});
			fetch(smf_scripturl + '?' + params + ';xml', {
				headers: {
					"X-SMF-AJAX": 1,
					'Accept': 'application/xml'
				},
				credentials: typeof allow_xhjr_credentials !== "undefined" ? 'include' : 'same-origin'
			})
			.then(response => {
				if (!response.ok) {
					throw new Error("HTTP error " + response.status);
				}
				return response.text();
			})
			.then(responseText => new window.DOMParser().parseFromString(responseText, "text/xml"))
			.then(responseXml => {
				const members = responseXml.getElementsByTagName('item');

				if (members.length === 0) {
					fails.push(query); // Cache failed queries
				}

				let callbackArray = Array.from(members).map(member => ({ name: member.textContent }));
				callback(callbackArray);
			})
			.catch(error => console.error('Error fetching suggestions:', error));
		}
	}
};

window.addEventListener('load', () => {
	const textArea = document.querySelector('textarea[name=message]');
	if (typeof sceditor === 'undefined') {
		if (textArea) {
			atwho(textArea, atwhoConfig);
		}
	}
});

if (typeof sceditor !== 'undefined') {
		sceditor.plugins.mentions = function() {
		let base = this,
			editor;

		base.init = function () {
			editor = this;
		};

		base.signalReady = function() {
			const sceditor_textarea = editor.getContentAreaContainer().nextSibling;
			atwho(sceditor_textarea, atwhoConfig);

			if (!editor.opts.runWithoutWysiwygSupport) {
				let iframe = editor.getContentAreaContainer(),
					iframeBody = iframe.contentDocument.body;

				atwho(iframeBody, atwhoConfig);
			}
		};
	}
}