function smf_Login(oOptions) {
	this.opt = oOptions;
}

smf_Login.prototype.login = function() {
	const
		opt = this.opt,
		form = opt.oForm,
		isSameCors = opt.sCors == '' || opt.sCors  == 'same',
		url = new URL(form.action);

	url.searchParams.set('ajax', '1');

	form.addEventListener("submit", e => {
		e.preventDefault();

		$.ajax({
			url: url.toString(),
			method: "POST",
			headers: {
				"X-SMF-AJAX": 1
			},
			xhrFields: {
				withCredentials: typeof allow_xhjr_credentials !== "undefined" ? allow_xhjr_credentials : false
			},
			data: new URLSearchParams(new FormData(form)).toString(),
			success: function(data) {
				/*
				 * While a nice action is to replace the document body after a login,
				 * this may fail on CORS requests because the action may not be
				 * redirected back to the page they started the login process from.
				 *
				 * So for these cases, we simply just reload the page.
				 */
				if (isSameCors) {
					if (data.indexOf("<body") > -1) {
						document.open();
						document.write(data);
						document.close();
					} else {
						$(form).parent().html($(data).find(".windowbg form_grid").html());
					}
				} else {
					window.location.reload();
				}
			},
			error: function(xhr) {
				var data = xhr.responseText;
				if (data.indexOf("<body") > -1) {
					document.open();
					document.write(data);
					document.close();
				}
				else
					$(form).parent().html($(data).filter("#fatal_error").html());
			}
		});
	});
}