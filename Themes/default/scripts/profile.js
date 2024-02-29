// Determine browser's timezone and DST
function autoDetectTimeOffset()
{
	const getUtcDateFromTz = (date, timeZone) => {
		const dateComponents = date.toLocaleString("sv", { timeZone }).split(/[-\s:]/);

		// Months are zero-based in JavaScript.
		dateComponents[1]--;

		// Discard milliseconds by rounding.
		return Math.round((date - Date.UTC(...dateComponents)) / 6e4);
	};

	const now = new Date;
	const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
	const getRegion = tz => tz.split('/')[0];
	const r = getRegion(tz);
	const diff1 = getUtcDateFromTz(now, tz);
	const offset = diff1;

	const jan = new Date(now.getFullYear(), 0, 1);
	const jun = new Date(now.getFullYear(), 5, 1);
	const dst = getUtcDateFromTz(jun, tz) - getUtcDateFromTz(jan, tz) != 0;

	const hr = Math.floor(Math.abs(diff1) / 60);
	const min = diff1 % 60;
	const pad = n => ('00' + n).slice(-2);

	const span = document.createElement('span');
	span.className = 'padding smalltext';
	span.textContent = tz + ' [UTC ' + (diff1 < 0 ? '+' : '-') + pad(hr) + ':' + pad(min) + ']';
	this.after(span);

	const div = document.createElement('div');
	div.className = 'padding smalltext';

	for (const el of this.form.timezone.options) {
		try {
			const o = getUtcDateFromTz(now, el.value);
			const d = getUtcDateFromTz(jun, el.value) - getUtcDateFromTz(jan, el.value) != 0;

			if (o == offset && d == dst && getRegion(el.value) == r) {
				div.textContent = el.textContent;
				el.selected = true;
			}
		} catch {
			continue;
		}
	}

	span.after(div);
}

// Prevent Chrome from auto completing fields when viewing/editing other members profiles
function disableAutoComplete()
{
	if (is_chrome)
		for (const form of document.forms) {
			for (const el of form.elements) {
				// Only bother with text/password fields,
				if (el.type == "text" || el.type == "password")
					el.setAttribute("autocomplete", "off");
			}
		}
}

function calcCharLeft()
{
	var oldSignature = "", currentSignature = this.value;
	var currentChars = 0;

	if (!document.getElementById("signatureLeft"))
		return;

	if (oldSignature != currentSignature)
	{
		oldSignature = currentSignature;

		var currentChars = currentSignature.replace(/\r/, "").length;
		if (is_opera)
			currentChars = currentSignature.replace(/\r/g, "").length;

		if (currentChars > maxLength)
			document.getElementById("signatureLeft").className = "error";
		else
			document.getElementById("signatureLeft").className = "";

		if (currentChars > maxLength)
			ajax_getSignaturePreview(false);
		// Only hide it if the only errors were signature errors...
		else if (currentChars <= maxLength)
		{
			// Are there any errors to begin with?
			if ($(document).has("#list_errors"))
			{
				// Remove any signature errors
				$("#list_errors").remove(".sig_error");

				// Don't hide this if other errors remain
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		}
	}

	setInnerHTML(document.getElementById("signatureLeft"), maxLength - currentChars);
}

function ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;

	// Is the error box already visible?
	var errorbox_visible = $("#profile_error").is(":visible");

	$.ajax({
		type: "POST",
		url: smf_scripturl + "?action=xmlhttp;sa=previews;xml",
		headers: {
			"X-SMF-AJAX": 1
		},
		xhrFields: {
			withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
		},
		data: {item: "sig_preview", signature: $("#signature").val(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				var signatures = new Array("current", "preview");
				for (var i = 0; i < signatures.length; i++)
				{
					$("#" + signatures[i] + "_signature").css({display:""});
					$("#" + signatures[i] + "_signature_display").css({display:""}).html($(request).find('[type="' + signatures[i] + '"]').text() + '<hr>');
				}
			}

			if ($(request).find("error").text() != '')
			{
				// If the box isn't already visible...
				// 1. Add the initial HTML
				// 2. Make it visible
				if (!errorbox_visible)
				{
					// Build our HTML...
					var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul id="list_errors"></ul>';

					// Add it to the box...
					$("#profile_error").html(errors_html);

					// Make it visible
					$("#profile_error").css({display: ""});
				}
				else
				{
					// Remove any existing signature-related errors...
					$("#list_errors").remove(".sig_error");
				}

				var errors = $(request).find('[type="error"]');
				var errors_list = '';

				for (var i = 0; i < errors.length; i++)
					errors_list += '<li class="sig_error">' + $(errors).text() + '</li>';

				$("#list_errors").html(errors_list);
			}
			// If there were more errors besides signature-related ones, don't hide it
			else
			{
				// Remove any signature errors first...
				$("#list_errors").remove(".sig_error");

				// If it still has content, there are other non-signature errors...
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		return false;
		},
	});
	return false;
}

function changeSel(f, opts)
{
	if (f.cat.selectedIndex == -1)
		return;

	if (opts[f.cat.options[f.cat.selectedIndex].value]) {
		f.file.style.display = "inline";
		f.file.disabled = false;

		while (f.file.options.length)
			f.file.remove(0);

		for (el of opts[f.cat.options[f.cat.selectedIndex].value]) {
			el.selected = el.attributes.selected;
			f.file.add(el);
		}

		if (f.file.selectedIndex == -1) {
			f.file.options[0].selected = true;
			f.file.dispatchEvent(new Event('change'));
		}
	}
	else
	{
		f.file.style.display = "none";
		f.file.disabled = true;
		previewExternalAvatar(f.cat.dataset.avatardir + f.cat.options[f.cat.selectedIndex].value);
	}
}

function get_gravatar_image_url(email, size, default_image, allowed_rating, force_default)
{
	email = typeof email !== 'undefined' ? email : 'john.doe@example.com';
	size = (size >= 1 && size <= 2048) ? size : 80;
	default_image = typeof default_image !== 'undefined' ? default_image : 'mm';
	allowed_rating = typeof allowed_rating !== 'undefined' ? allowed_rating : 'g';
	force_default = force_default === true ? 'y' : 'n';

	return "https://secure.gravatar.com/avatar/" + smf_md5(email.toLowerCase().trim()) + "?size=" + size + "&default=" + encodeURIComponent(default_image) + "&rating=" + allowed_rating + (force_default === 'y' ? "&forcedefault=" + force_default : '');
}

function initAvatar(f) {
	const debounce = (callback, wait) => {
		let timeoutId = null;

		return (...args) => {
			window.clearTimeout(timeoutId);
			timeoutId = window.setTimeout(() => {
				callback(...args);
			}, wait);
		};
	};

	const update = e =>
	{
		for (const el of f)
			if (el.name == "avatar_choice") {
				const fieldset = f['avatar_' + el.value];

				if (fieldset)
					fieldset.hidden = !el.checked;

				if (el.checked && e)
					switch (el.value) {
						case 'none':
						case 'upload':
							previewExternalAvatar(f.file.dataset.avatardir + '/default.png');

							break;

						case 'server_stored':
							const sel = f.file || f.cat;
							previewExternalAvatar(f.cat.dataset.avatardir + sel.options[sel.selectedIndex].value);

							break;

						case 'extenal':
							previewExternalAvatar(f.userpicpersonal);

							break;

						case 'gravatar':
							previewExternalAvatar(get_gravatar_image_url(fieldset.dataset.email));

							if (f.gravatarEmail)
								previewExternalAvatar(get_gravatar_image_url(f.gravatarEmail));

							break;
					}
			}
	};

	for (const el of f)
		if (el.name == "avatar_choice")
			el.addEventListener("click", update);

	update();
	let opts = {};

	if (f.cat) {
		let toAdd = [], toRemove = [];

		const sel = document.createElement('select');
		sel.name = 'file';
		sel.size = 10;
		sel.style.marginInline = '1em 0';
		f.cat.after(sel);

		for (let i = 0; i < f.cat.options.length; i++) {
			const el = f.cat.options[i];
			const p = el.parentElement;

			if (p.label && p.dataset.dir) {
				if (!opts[p.label]) {
					toRemove.push(p);
					const opt = new Option(p.label);

					for (const image of document.images) {
						if (image.id == 'current_avatar' && image.src.includes(p.dataset.dir + '/')) {
							opt.selected = true;

							break;
						}
					}
					f.cat.add(opt);
					opts[p.label] = [...p.children];
				}
			}
		}

		for (const el of toRemove)
			el.remove();

		f.cat.addEventListener('change', changeSel.bind(null, f, opts));
		changeSel(f, opts);
	}

	if (f.file)
		f.file.addEventListener('change', function() {
			previewExternalAvatar(f.cat.dataset.avatardir + this.options[this.selectedIndex].value);
		});

	if (f.userpicpersonal)
		f.userpicpersonal.addEventListener('input', function() {
			previewExternalAvatar(smf_images_url + '/loading_sm.gif');
			debounce(() => {
				previewExternalAvatar(this.value);
			}, 500)();
		});

	if (f.attachment)
		f.attachment.addEventListener('change', function() {
			if (this.files && this.files[0]) {
				const reader = new FileReader;

				reader.onload = e => {
					previewExternalAvatar(e.target.result);
				}

				reader.readAsDataURL(this.files[0]);
			}
		});

	if (f.gravatarEmail)
		f.gravatarEmail.addEventListener('input', function() {
			previewExternalAvatar(smf_images_url + '/loading_sm.gif');
			debounce(() => {
				previewExternalAvatar(get_gravatar_image_url(this.value));
			}, 500)();
		});
}

function previewExternalAvatar(src)
{
	for (const image of document.images)
		if (image.id == 'current_avatar') {
			image.src = src;

			break;
		}
}

window.addEventListener("DOMContentLoaded", function() {
	disableAutoComplete();
	const f = document.forms.creator;

	if (!f)
		return;

console.log(f)

	let tmp = f['additional_groups[]'];
	if (tmp)
		makeToggle(tmp[0].parentNode);

	if (f.avatar_choice)
		initAvatar(f);

	if (typeof require_password != 'undefined' && require_password)
		f.addEventListener('submit', e => {
			if (f.oldpasswrd.value == "") {
				e.preventDefault();
				alert(required_security_reasons);
			}
		});

	if (f.smiley_set)
		f.smiley_set.addEventListener('change', function() {
			this.nextElementSibling.src = this.options[this.selectedIndex].dataset.preview;
		});

	if (f.signature) {
		calcCharLeft.call(f.signature);
		f.preview_button.addEventListener('click', ajax_getSignaturePreview.bind(null, true));
		f.signature.addEventListener('input', calcCharLeft);
	}

	if (f.timezone) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'button';
		button.textContent = autodetect;
		f.timezone.after(document.createElement('hr'), button);
		button.addEventListener('click', autoDetectTimeOffset);
	}

	tmp = f.warn_notify;
	if (tmp) {
		f.warning_level.addEventListener('change', updateSlider);
		f.warn_temp.addEventListener('change', populateNotifyTemplate);
		f.preview.addEventListener('click', ajax_getTemplatePreview);
		tmp.addEventListener('click', modifyWarnNotify);
		modifyWarnNotify.call(tmp);
		const a = document.createElement('a');
		a.className = 'button';
		a.textContent = f.warn_temp.dataset.text;
		a.href = smf_scripturl + '?action=moderate;area=warnings;sa=templateedit;tid=0';
		f.warn_temp.after(' ', a, document.createElement('br'));
	}

	if (f.export_format_select) {
		const toggleExport = function() {
			const flag = completed_formats.indexOf(this.value) > -1;
			f.export_begin.hidden = flag;
			f.export_begin.disabled = flag;
			f.export_restart.hidden = !flag;
			f.export_restart.disabled = !flag;
		};
		toggleExport.call(f.export_format_select);
		f.export_format_select.addEventListener('change', toggleExport);
	}

	const els = document.getElementsByClassName('export_download_all');

	if (els)
		for (const el of els)
			el.addEventListener('click', export_download_all);
});

// Disable notification boxes as required.
function modifyWarnNotify()
{
	const disable = !this.form.warn_notify.checked;
	this.form.warn_sub.disabled = disable;
	this.form.warn_body.disabled = disable;
	this.form.warn_temp.disabled = disable;
	this.form.preview.disabled = disable;
}

// Warn template.
function populateNotifyTemplate() {
	const opt = this.options[this.selectedIndex];

	if (opt.value == -1)
		return;

	const el = this.nextElementSibling;
	el.href = el.href.replacce('0', opt.value);

	for (const tpl of notification_templates)
		if (opt.text == tpl.title) {
			this.form.warn_body.value = tpl.body;
			this.form.warn_body.focus;
			break;
		}
}

function updateSlider() {
	for (const l in level_effects)
		if (this.value >= l)
			this.form.cur_level.value = this.value + '% (' + level_effects[l] + ')';
}

function ajax_getTemplatePreview() {
	$.ajax({
		type: "POST",
		headers: {
			"X-SMF-AJAX": 1
		},
		xhrFields: {
			withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
		},
		url: smf_scripturl + '?action=xmlhttp;sa=previews;xml',
		data: {item: "warning_preview", title: $("#warn_sub").val(), body: $("#warn_body").val(), issuing: true},
		context: document.body,
		success: function(request) {
			$("#box_preview").css({display:""});
			$("#body_preview").html($(request).find('body').text());
			if ($(request).find("error").text() != '')
			{
				$("#profile_error").css({display:""});
				var errors_html = '<ul class="list_errors">';
				var errors = $(request).find('error').each(function() {
					errors_html += '<li>' + $(this).text() + '</li>';
				});
				errors_html += '</ul>';

				$("#profile_error").html(errors_html);
			}
			else
			{
				$("#profile_error").css({display:"none"});
				$("#error_list").html('');
			}
		return false;
		},
	});
	return false;
}

function changeVariant(iThemeId, el)
{
	document.getElementById('theme_thumb_' + iThemeId).src = oThemeVariants[iThemeId][el.value]['thumbnail'];
	const href = el.form.action + ';theme=' + iThemeId + ';variant=' + el.value;
	document.getElementById('theme_thumb_preview_' + iThemeId).href = href;
	document.getElementById('theme_preview_' + iThemeId).href = href;
}

function export_download_all()
{
	for (el of document.links) {
		if (el.id == this.dataset.format + '_export_files') {
			// Add an invisible iframe pointing to the file to automatically download it.
			const iframe = document.createElement('iframe');
			iframe.style.display = 'collapse';
			iframe.src = el.href;
			document.body.append(iframe);

			// Give plenty of time for the download to complete, then clean up.
			setTimeout(() => { iframe.remove(); }, 30000);
		}
	}
}

function smf_md5(inputString) {
	const hc = '0123456789abcdef';
	const rh = n => {
		let j, s = '';
		for (j = 0; j <= 3; j++) s += hc.charAt((n >> (j * 8 + 4)) & 0x0F) + hc.charAt((n >> (j * 8)) & 0x0F);
		return s;
	}
	const ad = (x, y) => {
		let l = (x & 0xFFFF) + (y & 0xFFFF);
		let m = (x >> 16) + (y >> 16) + (l >> 16);
		return (m << 16) | (l & 0xFFFF);
	}
	const rl = (n, c) => (n << c) | (n >>> (32 - c));
	const cm = (q, a, b, x, s, t) => ad(rl(ad(ad(a, q), ad(x, t)), s), b);
	const ff = (a, b, c, d, x, s, t) => cm((b & c) | ((~b) & d), a, b, x, s, t);
	const gg = (a, b, c, d, x, s, t) => cm((b & d) | (c & (~d)), a, b, x, s, t);
	const hh = (a, b, c, d, x, s, t) => cm(b ^ c ^ d, a, b, x, s, t);
	const ii = (a, b, c, d, x, s, t) => cm(c ^ (b | (~d)), a, b, x, s, t);
	const sb = x => {
		let i;
		const nblk = ((x.length + 8) >> 6) + 1;
		const blks = [];
		for (i = 0; i < nblk * 16; i++) {
			blks[i] = 0
		};
		for (i = 0; i < x.length; i++) {
			blks[i >> 2] |= x.charCodeAt(i) << ((i % 4) * 8);
		}
		blks[i >> 2] |= 0x80 << ((i % 4) * 8);
		blks[nblk * 16 - 2] = x.length * 8;
		return blks;
	}
	let i, x = sb(inputString),
		a = 1732584193,
		b = -271733879,
		c = -1732584194,
		d = 271733878,
		olda, oldb, oldc, oldd;
	for (i = 0; i < x.length; i += 16) {
		olda = a;
		oldb = b;
		oldc = c;
		oldd = d;
		a = ff(a, b, c, d, x[i + 0], 7, -680876936);
		d = ff(d, a, b, c, x[i + 1], 12, -389564586);
		c = ff(c, d, a, b, x[i + 2], 17, 606105819);
		b = ff(b, c, d, a, x[i + 3], 22, -1044525330);
		a = ff(a, b, c, d, x[i + 4], 7, -176418897);
		d = ff(d, a, b, c, x[i + 5], 12, 1200080426);
		c = ff(c, d, a, b, x[i + 6], 17, -1473231341);
		b = ff(b, c, d, a, x[i + 7], 22, -45705983);
		a = ff(a, b, c, d, x[i + 8], 7, 1770035416);
		d = ff(d, a, b, c, x[i + 9], 12, -1958414417);
		c = ff(c, d, a, b, x[i + 10], 17, -42063);
		b = ff(b, c, d, a, x[i + 11], 22, -1990404162);
		a = ff(a, b, c, d, x[i + 12], 7, 1804603682);
		d = ff(d, a, b, c, x[i + 13], 12, -40341101);
		c = ff(c, d, a, b, x[i + 14], 17, -1502002290);
		b = ff(b, c, d, a, x[i + 15], 22, 1236535329);
		a = gg(a, b, c, d, x[i + 1], 5, -165796510);
		d = gg(d, a, b, c, x[i + 6], 9, -1069501632);
		c = gg(c, d, a, b, x[i + 11], 14, 643717713);
		b = gg(b, c, d, a, x[i + 0], 20, -373897302);
		a = gg(a, b, c, d, x[i + 5], 5, -701558691);
		d = gg(d, a, b, c, x[i + 10], 9, 38016083);
		c = gg(c, d, a, b, x[i + 15], 14, -660478335);
		b = gg(b, c, d, a, x[i + 4], 20, -405537848);
		a = gg(a, b, c, d, x[i + 9], 5, 568446438);
		d = gg(d, a, b, c, x[i + 14], 9, -1019803690);
		c = gg(c, d, a, b, x[i + 3], 14, -187363961);
		b = gg(b, c, d, a, x[i + 8], 20, 1163531501);
		a = gg(a, b, c, d, x[i + 13], 5, -1444681467);
		d = gg(d, a, b, c, x[i + 2], 9, -51403784);
		c = gg(c, d, a, b, x[i + 7], 14, 1735328473);
		b = gg(b, c, d, a, x[i + 12], 20, -1926607734);
		a = hh(a, b, c, d, x[i + 5], 4, -378558);
		d = hh(d, a, b, c, x[i + 8], 11, -2022574463);
		c = hh(c, d, a, b, x[i + 11], 16, 1839030562);
		b = hh(b, c, d, a, x[i + 14], 23, -35309556);
		a = hh(a, b, c, d, x[i + 1], 4, -1530992060);
		d = hh(d, a, b, c, x[i + 4], 11, 1272893353);
		c = hh(c, d, a, b, x[i + 7], 16, -155497632);
		b = hh(b, c, d, a, x[i + 10], 23, -1094730640);
		a = hh(a, b, c, d, x[i + 13], 4, 681279174);
		d = hh(d, a, b, c, x[i + 0], 11, -358537222);
		c = hh(c, d, a, b, x[i + 3], 16, -722521979);
		b = hh(b, c, d, a, x[i + 6], 23, 76029189);
		a = hh(a, b, c, d, x[i + 9], 4, -640364487);
		d = hh(d, a, b, c, x[i + 12], 11, -421815835);
		c = hh(c, d, a, b, x[i + 15], 16, 530742520);
		b = hh(b, c, d, a, x[i + 2], 23, -995338651);
		a = ii(a, b, c, d, x[i + 0], 6, -198630844);
		d = ii(d, a, b, c, x[i + 7], 10, 1126891415);
		c = ii(c, d, a, b, x[i + 14], 15, -1416354905);
		b = ii(b, c, d, a, x[i + 5], 21, -57434055);
		a = ii(a, b, c, d, x[i + 12], 6, 1700485571);
		d = ii(d, a, b, c, x[i + 3], 10, -1894986606);
		c = ii(c, d, a, b, x[i + 10], 15, -1051523);
		b = ii(b, c, d, a, x[i + 1], 21, -2054922799);
		a = ii(a, b, c, d, x[i + 8], 6, 1873313359);
		d = ii(d, a, b, c, x[i + 15], 10, -30611744);
		c = ii(c, d, a, b, x[i + 6], 15, -1560198380);
		b = ii(b, c, d, a, x[i + 13], 21, 1309151649);
		a = ii(a, b, c, d, x[i + 4], 6, -145523070);
		d = ii(d, a, b, c, x[i + 11], 10, -1120210379);
		c = ii(c, d, a, b, x[i + 2], 15, 718787259);
		b = ii(b, c, d, a, x[i + 9], 21, -343485551);
		a = ad(a, olda);
		b = ad(b, oldb);
		c = ad(c, oldc);
		d = ad(d, oldd);
	}
	return rh(a) + rh(b) + rh(c) + rh(d);
}