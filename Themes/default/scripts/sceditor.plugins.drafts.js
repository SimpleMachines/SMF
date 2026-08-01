(function (sceditor) {
	'use strict';

	/**
	 * Autosaves whatever is in the editor, on a timer.
	 *
	 * This half only knows when to save. What to send is supplied by whichever of
	 * the plugins below is loaded alongside it, as editor.getDraftFormData().
	 *
	 * Configured through the editor's draftOptions:
	 *   sQueryParams  query string to post the draft to
	 *   sLastID       id of the hidden input holding the draft id
	 *   sLastNote     id of the element showing when it last saved
	 *   iFreq         how often to save, in milliseconds
	 */
	sceditor.plugins.drafts = function ()
	{
		var editor, opt, interval, lastValue, saving = false;

		var stop = function ()
		{
			saving = false;
			document.getElementById('throbber').style.display = 'none';
		};

		var done = function (XMLDoc)
		{
			if (!XMLDoc || !XMLDoc.getElementsByTagName('draft').length)
				return stop();

			var draft = XMLDoc.getElementsByTagName('draft')[0];

			// If this was a new draft, remember the id the server gave it, so the
			// next save updates it instead of making another one.
			document.getElementById(opt.sLastID).value = draft.getAttribute('id');
			document.getElementById(opt.sLastNote).textContent = draft.childNodes[0].nodeValue;

			// Posting has a "draft saved" box, from the save button. Personal
			// messages do not, hence the check.
			var section = document.getElementById('draft_section');

			if (section)
				section.style.display = 'none';

			stop();
		};

		var trigger = function ()
		{
			var body = editor.val();

			// Nothing to save, still saving the last one, being posted right now,
			// or unchanged since the last save?
			if (isEmptyText(body) || saving || smf_formSubmitted || lastValue === body)
				return;

			saving = true;
			document.getElementById('throbber').style.display = '';

			sendXMLDocument(
				smf_prepareScriptUrl(smf_scripturl) + opt.sQueryParams + ';xml',
				editor.getDraftFormData(),
				done
			);

			lastValue = body;
		};

		this.signalReady = function ()
		{
			editor = this;
			opt = editor.opts.draftOptions;
			interval = setInterval(trigger, opt.iFreq);

			// Stop autosaving once the real post is on its way.
			editor.opts.original.form.addEventListener('submit', this.signalBlurEvent);
		};

		this.signalFocusEvent = function ()
		{
			if (!interval)
				interval = setInterval(trigger, opt.iFreq);
		};

		this.signalBlurEvent = function ()
		{
			clearInterval(interval);
			interval = null;
		};
	};

	/**
	 * Supplies the draft data for a post or a quick reply.
	 */
	sceditor.plugins.messageDrafts = function ()
	{
		this.init = function ()
		{
			var editor = this,
				form = document.forms.postmodify;

			editor.getDraftFormData = function ()
			{
				var data = new FormData();

				data.append('topic', parseInt(form.elements['topic'].value));
				data.append('id_draft', 'id_draft' in form.elements ? parseInt(form.elements['id_draft'].value) : 0);
				data.append('subject', form.subject ? form.subject.value : '');
				data.append('message', editor.val());
				data.append('icon', form.icon ? form.icon.value : 'xx');
				data.append('save_draft', 'true');
				data.append(smf_session_var, smf_session_id);

				if (document.getElementById('check_lock') && document.getElementById('check_lock').checked)
					data.append('lock', '1');

				if (document.getElementById('check_sticky') && document.getElementById('check_sticky').checked)
					data.append('sticky', '1');

				return data;
			};
		};
	};

	/**
	 * Supplies the draft data for a personal message.
	 */
	sceditor.plugins.pmDrafts = function ()
	{
		this.init = function ()
		{
			var editor = this,
				form = document.forms.postmodify;

			// Draft::setProperties() splits these on commas, so they go as one
			// joined value rather than as a repeated field.
			var recipients = function (field)
			{
				var el = form.elements[field];

				if (!el)
					return '';

				var values = 'value' in el ? [el.value] : Array.prototype.map.call(el, function (o) { return o.value; });

				return values.map(function (v) { return parseInt(v); }).join(',');
			};

			editor.getDraftFormData = function ()
			{
				var data = new FormData();

				data.append('replied_to', parseInt(form.elements['replied_to'].value));
				data.append('id_draft', 'id_draft' in form.elements ? parseInt(form.elements['id_draft'].value) : 0);
				data.append('subject', form.subject ? form.subject.value : '');
				data.append('message', editor.val());
				data.append('recipient_to', recipients('recipient_to[]'));
				data.append('recipient_bcc', recipients('recipient_bcc[]'));
				data.append('save_draft', 'true');
				data.append(smf_session_var, smf_session_id);

				return data;
			};
		};
	};
})(sceditor);
