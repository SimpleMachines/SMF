function addPollOption(container, e) {
	var pollOptionNum = 0;
	var pollOptionId = 0;

	for (const el of e.target.form.elements) {
		if (el.id.substr(0, 8) == 'options-') {
			pollOptionNum++;
			pollOptionId = el.id.match(/\d+/)[0];
		}
	}

	pollOptionNum++
	pollOptionId++

	container.insertAdjacentHTML('beforeend', '<dt><label for="options-' + pollOptionId + '">' + container.dataset.optionTxt + ' ' + pollOptionNum + '</label>:</dt><dd><input type="text" name="options[' + pollOptionId + ']" id="options-' + pollOptionId + '" value="" size="80" maxlength="255"></dd>');
}

function pollOptions()
{
	var expire_time = this.form.poll_expire;

	if (isEmptyText(expire_time) || expire_time.value == 0)
	{
		document.forms.postmodify.poll_hide[2].disabled = true;
		if (document.forms.postmodify.poll_hide[2].checked)
			document.forms.postmodify.poll_hide[1].checked = true;
	}
	else
		document.forms.postmodify.poll_hide[2].disabled = false;
}

window.addEventListener('load', function () {
	const form = document.forms.postmodify;
	const el = form.poll_main.children[1];
	const addMoreButton = document.createElement('button');
	addMoreButton.textContent = el.dataset.moreTxt;
	addMoreButton.className = 'button';
	addMoreButton.addEventListener('click', addPollOption.bind(null, el));
	el.after(addMoreButton);

	form.poll_expire.addEventListener('change', pollOptions);
});