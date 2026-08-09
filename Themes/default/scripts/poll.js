/**
 * Editing a poll: adding options to it, and the settings that depend on each
 * other.
 *
 * Loaded from Post.php when the posting form carries a poll, and from
 * PollEdit.php for the add/edit poll form, so everything here can assume the
 * poll fieldsets are on the page.
 */

/**
 * Adds one more empty option to the poll.
 *
 * The id and name of the new field are the last one's with its trailing number
 * bumped, rather than anything built from scratch. The two templates disagree
 * about what those look like, and following whatever the server just rendered
 * keeps the new field in the same shape as its neighbours either way.
 *
 * The label comes from a data attribute holding the language string already
 * rendered with 999 in it, because languages do not agree on which side of the
 * word the number belongs.
 *
 * @param {HTMLElement} container The dl the options live in.
 */
function addPollOption(container)
{
	var fields = container.querySelectorAll('input[id^="options-"]');

	if (!fields.length)
		return;

	var last = fields[fields.length - 1];
	var bump = function (str) {
		return str.replace(/\d+(?!.*\d)/, function (n) {
			return parseInt(n, 10) + 1;
		});
	};

	var id = bump(last.id), name = bump(last.name);
	var label = container.dataset.optionTxt.replace('999', fields.length + 1);

	container.insertAdjacentHTML('beforeend',
		'<dt><label for="' + id + '">' + label + '</label></dt>'
		+ '<dd><input type="text" name="' + name + '" id="' + id + '" value="" size="80" maxlength="255"></dd>'
	);
}

/**
 * "Results are only shown once the poll has expired" cannot mean anything while
 * the poll is set to never expire, so it follows the expiry field.
 */
function pollOptions()
{
	var expire_time = document.getElementById('poll_expire');
	var hide = document.forms.postmodify.poll_hide;

	if (isEmptyText(expire_time) || expire_time.value == 0)
	{
		hide[2].disabled = true;

		if (hide[2].checked)
			hide[1].checked = true;
	}
	else
		hide[2].disabled = false;
}

document.addEventListener('DOMContentLoaded', function ()
{
	var container = document.getElementById('poll_choices');

	if (container)
	{
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'button';
		button.textContent = container.dataset.moreTxt;
		button.addEventListener('click', function () {
			addPollOption(container);
		});
		container.after(button);
	}

	var expire = document.getElementById('poll_expire');

	if (expire)
		expire.addEventListener('change', pollOptions);
});
