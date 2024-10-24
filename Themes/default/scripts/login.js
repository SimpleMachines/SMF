class SMF_Login {
	constructor(oOptions) {
		this.opt = oOptions;
	}

	login() {
		const opt = this.opt;
		const form = opt.oForm;
		const isSameCors = opt.sCors == '' || opt.sCors == 'same';
		const url = new URL(form.action);
		url.searchParams.set('ajax', '1');

		form.addEventListener("submit", function (e) {
			e.preventDefault();

			fetch(url.toString(), {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded",
					"X-SMF-AJAX": 1
				},
				credentials: typeof allow_xhjr_credentials !== "undefined" ? "include" : "same-origin",
				body: new URLSearchParams(new FormData(form)).toString()
			})
			.then(response => {
				if (!response.ok) {
					throw new Error("Network response was not ok.");
				}
				return response.text();
			})
			.then(data => {
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
						form.parentNode.innerHTML = data;
					}
				} else {
					window.location.reload();
				}
			})
			.catch(error => {
				console.error("Error:", error);
			});
		});
	}
}