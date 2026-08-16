/**
 * Submits the login form without leaving the page.
 *
 * Used where a plain submit would not do: the login form can be opened in an
 * overlay from the top menu, and it can be served to another origin that CORS
 * allows, where the response does not necessarily come back to the page the
 * member started on.
 *
 * The listener is delegated from the document rather than bound to the form,
 * because the overlay arrives through innerHTML and nothing that comes in that
 * way gets a chance to bind anything to itself.
 *
 * A form opts in by carrying data-ajax-login, whose value is what
 * Security::corsPolicyHeader() decided about the origin: empty for a plain
 * request, otherwise one of 'same', 'subdomain', 'alias', 'additional' or
 * 'wildcard'.
 */
document.addEventListener('submit', function (e)
{
	const form = e.target;

	if (!form.matches('form[data-ajax-login]'))
		return;

	e.preventDefault();
	e.stopPropagation();

	const action = form.getAttribute('action');

	/*
	 * Replacing the page with the response is the nicer outcome, but it is only
	 * right when the response is going to come back to the page the member is
	 * looking at. Anywhere else, all that can be said is that something changed,
	 * so reload and let the server decide what to show.
	 */
	const cors = form.dataset.ajaxLogin;
	const reloadOnly = cors !== '' && cors !== 'same';

	fetch(action + (action.indexOf('?') !== -1 ? ';' : '?') + 'ajax', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-SMF-AJAX': 1,

			// @fixme This is checked for in SMF\Actions\Login2::checkAjax().
			'X-Requested-With': 'XMLHttpRequest'
		},
		credentials: typeof allow_xhjr_credentials !== 'undefined' && allow_xhjr_credentials ? 'include' : 'same-origin',
		body: new URLSearchParams(new FormData(form)).toString()
	})
	.then((response) => response.text().then((data) => {
		// A whole page came back, so the login went somewhere. Show it.
		if (data.indexOf('<bo' + 'dy') > -1)
		{
			if (reloadOnly && response.ok)
				window.location.reload();
			else
				smf_replaceDocument(data);

			return;
		}

		// Otherwise it is the form again, with whatever went wrong in it.
		smf_replaceLoginForm(form, data, response.ok ? '.roundframe' : '#fatal_error');
	}))
	.catch(() => window.location.reload());
});

/**
 * Throws the current page away and puts the given one in its place.
 *
 * @param {string} data The page to show.
 */
function smf_replaceDocument(data)
{
	document.open();
	document.write(data);
	document.close();
}

/**
 * Swaps the login form for whatever came back in its place.
 *
 * @param {HTMLFormElement} form The form that was submitted.
 * @param {string} data The response.
 * @param {string} selector What to pull out of the response.
 */
function smf_replaceLoginForm(form, data, selector)
{
	const parsed = new DOMParser().parseFromString(data, 'text/html');
	const replacement = parsed.querySelector(selector);

	// Nothing recognisable came back, so there is nothing better to do.
	if (!replacement)
	{
		window.location.reload();

		return;
	}

	form.parentNode.innerHTML = replacement.innerHTML;
}
