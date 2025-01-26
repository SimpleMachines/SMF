(function (sceditor) {
	'use strict';

	sceditor.plugins.quoteFast = function ()
	{
		const regex = /\d+/;
		let editor, opts;

		const quote = function(e)
		{
			editor.insertQuoteFast(this);
			location.hash = '#' + opts.sJumpAnchor;
			e.preventDefault();
		};

		this.init = function()
		{
			editor = this;
			opts = editor.opts.quoteFastOptions;

			const posts = document.getElementById(opts.sPostContainerId);

			for (const post of posts.children) {
				if (post.id) {
					const
						el = post.querySelector(opts.sQuickButtonsSelector),
						msgid = post.id.match(regex);

					if (el) {
						const a = el.children[opts.iChildNum].firstElementChild;

						if (a && msgid) {
							a.addEventListener('click', quote.bind(msgid[0]));
						}
					}
				}
			}
		};
	};
})(sceditor);
