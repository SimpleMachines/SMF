var smf_formSubmitted = false;
var lastKeepAliveCheck = new Date().getTime();
var smf_editorArray = new Array();

// Some very basic browser detection - from Mozilla's sniffer page.
var ua = navigator.userAgent.toLowerCase();

var is_opera = ua.indexOf('opera') != -1;
var is_ff = (ua.indexOf('firefox') != -1 || ua.indexOf('iceweasel') != -1 || ua.indexOf('icecat') != -1 || ua.indexOf('shiretoko') != -1 || ua.indexOf('minefield') != -1) && !is_opera;
var is_gecko = ua.indexOf('gecko') != -1 && !is_opera;

var is_chrome = ua.indexOf('chrome') != -1;
var is_safari = ua.indexOf('applewebkit') != -1 && !is_chrome;
var is_webkit = ua.indexOf('applewebkit') != -1;

var is_ie = ua.indexOf('msie') != -1 && !is_opera;
// Stupid Microsoft...
var is_ie11 = ua.indexOf('trident') != -1 && ua.indexOf('gecko') != -1;
var is_iphone = ua.indexOf('iphone') != -1 || ua.indexOf('ipod') != -1;
var is_android = ua.indexOf('android') != -1;

var ajax_indicator_ele = null;

// Get a response from the server.
function getServerResponse(sUrl, funcCallback, sType = 'GET', sDataType = 'json') {
	var oCaller = this;

	return smc_Request.fetch(sUrl, {
		method: sType,
		cache: 'no-cache'
	})
	.then(response => {
		if (sDataType === 'json') return response.json();

		if (sDataType === 'text') return response.text();

		if (sDataType === 'blob') return response.blob();

		if (sDataType === 'arrayBuffer') return response.arrayBuffer();

		return response;
	})
	.then(data => {
		if (typeof funcCallback !== 'undefined') {
			funcCallback.call(oCaller, data);
		}

		return data;
	})
	.catch(error => {
		if (typeof funcCallback !== 'undefined') {
			funcCallback.call(oCaller, false);
		}

		return Promise.reject(error);
	});
}

class smc_Request {
	static fetch(sUrl, oOptions, iMilliseconds) {
		let timeout;
		let options = oOptions || {};

		if (iMilliseconds) {
			const controller = new AbortController();
			options.signal = controller.signal;
			timeout = setTimeout(() => controller.abort(), iMilliseconds);
		}

		if (typeof allow_xhjr_credentials !== "undefined" && allow_xhjr_credentials) {
			options.credentials = 'include';
		}

		if (options.headers) {
			if (options.headers instanceof Headers) {
				options.headers.set("X-SMF-AJAX", 1);
			} else {
				options.headers["X-SMF-AJAX"] = 1;
			}
		} else {
			options.headers = {
				"X-SMF-AJAX": 1
			};
		}

		const promise = fetch(sUrl, options)
			.then(res => res.ok ? res : Promise.reject(res))
			.catch(err => Promise.reject(new Error(`Network request failed: ${err.message}`)));

		if (iMilliseconds) {
			return promise.finally(() => timeout && clearTimeout(timeout));
		}

		return promise;
	}

	static fetchXML(sUrl, oOptions, iMilliseconds) {
		return this.fetch(sUrl, oOptions, iMilliseconds)
			.then(res => res.text())
			.then(str => new DOMParser().parseFromString(str, "text/xml"));
	}
}

// Load an XML document.
function getXMLDocument(sUrl, funcCallback, iMilliseconds) {
	var oCaller = this;
	const promise = smc_Request.fetchXML(sUrl, null, iMilliseconds);

	if (funcCallback) {
		return promise
			.then(data => {
				funcCallback.call(oCaller, data);
				return data;
			})
			.catch(err => {
				funcCallback.call(oCaller, false);
				return Promise.reject(err);
			});
	}

	return promise;
}

// Send a post form to the server.
function sendXMLDocument(sUrl, sContent, funcCallback) {
	var oCaller = this;

	const headers = {};
	if (typeof sContent === 'string' || sContent instanceof URLSearchParams) {
		headers['Content-Type'] = 'application/x-www-form-urlencoded';
	} else if (sContent instanceof Blob) {
		headers['Content-Type'] = sContent.type || 'application/octet-stream';
	} else if (!(sContent instanceof FormData)) {
		headers['Content-Type'] = 'application/json'; // Default to JSON
		sContent = JSON.stringify(sContent); // Convert object to JSON string
	}

	const promise = smc_Request.fetchXML(sUrl, {
		method: 'POST',
		headers,
		body: sContent
	});

	if (funcCallback) {
		return promise
			.then(data => {
				funcCallback.call(oCaller, data);
				return data;
			})
			.catch(err => {
				funcCallback.call(oCaller, false);
				return Promise.reject(err);
			});
	}

	return promise;
}

// Convert a string to an 8 bit representation (like in PHP).
String.prototype.php_to8bit = function ()
{
	var n, sReturn = '';

	// Recode from UTF16 (native .js) to UTF8
	for (var i = 0, iTextLen = this.length; i < iTextLen; i++)
	{
		// Below xFFFF, UTF16 simply = the code points
		n = this.charCodeAt(i);
		if (n < 128)
			sReturn += String.fromCharCode(n);
		else if (n < 2048)
			sReturn += String.fromCharCode(192 | n >> 6) + String.fromCharCode(128 | n & 63);
		// 0xD800 - 0xDBFF
		else if (n >= 55296 && n <= 56319)
		{
			// In this range, this is the beginning of a surrogate pair, where 4-byte utf8 chars are
			n = 65536 + ((n & 1023) << 10) + (this.charCodeAt(i + 1) & 1023);
			sReturn += String.fromCharCode(240 | n >> 18) + String.fromCharCode(128 | n >> 12 & 63) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
			// Skip next char, already used...
			i++;
		}
		else
			sReturn += String.fromCharCode(224 | n >> 12) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
	}

	return sReturn;
}

String.prototype.php_urlencode = function()
{
	return escape(this).replace(/\+/g, '%2b').replace('*', '%2a').replace('/', '%2f').replace('@', '%40');
}

String.prototype.php_htmlspecialchars = function()
{
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

String.prototype.php_unhtmlspecialchars = function()
{
	return this.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
}

String.prototype._replaceEntities = function(sInput, sDummy, sNum)
{
	return String.fromCharCode(parseInt(sNum));
}

String.prototype.removeEntities = function()
{
	return this.replace(/&(amp;)?#(\d+);/g, this._replaceEntities);
}

String.prototype.easyReplace = function (oReplacements)
{
	var sResult = this;
	for (var sSearch in oReplacements)
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), oReplacements[sSearch]);

	return sResult;
}

/* From: https://stackoverflow.com/questions/1144783/how-to-replace-all-occurrences-of-a-string-in-javascript */
String.prototype.replaceAll = function (find, replace)
{
	var str = this;
	return str.replace(new RegExp(find, 'g'), replace);
}

// Open a new window
function reqWin(desktopURL, alternateWidth, alternateHeight, noScrollbars)
{
	if ((alternateWidth && self.screen.availWidth * 0.8 < alternateWidth) || (alternateHeight && self.screen.availHeight * 0.8 < alternateHeight))
	{
		noScrollbars = false;
		alternateWidth = Math.min(alternateWidth, self.screen.availWidth * 0.8);
		alternateHeight = Math.min(alternateHeight, self.screen.availHeight * 0.8);
	}
	else
		noScrollbars = typeof(noScrollbars) == 'boolean' && noScrollbars == true;

	window.open(desktopURL, 'requested_popup', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=' + (noScrollbars ? 'no' : 'yes') + ',width=' + (alternateWidth ? alternateWidth : 480) + ',height=' + (alternateHeight ? alternateHeight : 220) + ',resizable=no');

	// Return false so the click won't follow the link ;).
	return false;
}

// Open a overlay div
function reqOverlayDiv(desktopURL, sHeader, sIcon)
{
	// Set up our div details
	const sAjax_indicator = '<div class="centertext"><img src="' + smf_images_url + '/loading_sm.gif"></div>';
	sHeader = sHeader || help_popup_heading_text;

	let containerOptions;
	if (sIcon && sIcon.match(/\.(gif|png|jpe?g|svg|bmp|tiff)$/) != null) {
		containerOptions = { heading: sHeader, content: sAjax_indicator, icon: smf_images_url + '/' + sIcon };
	} else {
		containerOptions = { heading: sHeader, content: sAjax_indicator, icon_class: 'main_icons ' + (sIcon || 'help') };
	}

	// Create the div that we are going to load
	const oContainer = new smc_Popup(containerOptions);
	const oPopup_body = oContainer.cover.querySelector('.popup_content');

	// Load the help page content (we just want the text to show)
	fetch(desktopURL + (desktopURL.includes('?') ? ';' : '?') + 'ajax', {
		method: 'GET',
		headers: {
			'X-SMF-AJAX': '1',

			// @fixme This is checked for in SMF\Actions\Login2::checkAjax().
			"X-Requested-With": "XMLHttpRequest"
		},
		credentials: typeof allow_xhjr_credentials !== 'undefined' ? 'include' : 'omit'
	})
		.then((res, rej) => res.ok ? res.text() : rej(res))
		.then(data => {
			oPopup_body.innerHTML = data;
		})
		.catch(error => {
			const errorMsg = error.headers.get('x-smf-errormsg');
			oPopup_body.innerHTML = errorMsg || error.message || banned_text;
		});

	return false;
}

// Create the popup menus for the top level/user menu area.
function smc_PopupMenu(oOptions)
{
	this.opt = oOptions || {};
	this.opt.menus = {};
}

smc_PopupMenu.prototype.add = function (sItem, sUrl)
{
	const menu = document.getElementById(sItem + '_menu');
	const item = document.getElementById(sItem + '_menu_top');

	if (!item) {
		return;
	}

	this.opt.menus[sItem] = { open: false, loaded: false, sUrl: sUrl, itemObj: item, menuObj: menu };

	item.addEventListener('click', function(e) {
		e.preventDefault();
		this.toggle(sItem);
	}.bind(this));
}

smc_PopupMenu.prototype.toggle = function (sItem)
{
	if (!!this.opt.menus[sItem].open)
		this.close(sItem);
	else
		this.open(sItem);
}

smc_PopupMenu.prototype.open = function (sItem)
{
	this.closeAll();

	if (!this.opt.menus[sItem].loaded) {
		this.opt.menus[sItem].menuObj.innerHTML = '<div class="loading">' + (ajax_notification_text || '') + '</div>';

		fetch(this.opt.menus[sItem].sUrl + (this.opt.menus[sItem].sUrl.includes('?') ? ';' : '?') + 'ajax', {
			method: "GET",
			headers: {
				'X-SMF-AJAX': 1,
			},
			credentials: typeof allow_xhjr_credentials !== "undefined" ? 'include' : 'same-origin',
		})
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.text();
		})
		.then(data => {
			this.opt.menus[sItem].menuObj.innerHTML = data;
			this.opt.menus[sItem].loaded = true;
		});
	}

	this.opt.menus[sItem].menuObj.classList.add('visible');
	this.opt.menus[sItem].itemObj.classList.add('open');
	this.opt.menus[sItem].open = true;

	// Now set up closing the menu if we click off.
	this.opt.menus[sItem].handleClickOutside = function(e) {
		if (e.target.closest('#' + this.opt.menus[sItem].itemObj.id) || e.target.closest('#' + this.opt.menus[sItem].menuObj.id)) {
			return;
		}

		this.closeAll();
	}.bind(this);

	document.addEventListener('click', this.opt.menus[sItem].handleClickOutside);
}

smc_PopupMenu.prototype.close = function (sItem)
{
	this.opt.menus[sItem].menuObj.classList.remove('visible');
	this.opt.menus[sItem].itemObj.classList.remove('open');
	this.opt.menus[sItem].open = false;
	document.removeEventListener('click', this.opt.menus[sItem].handleClickOutside);
}

smc_PopupMenu.prototype.closeAll = function ()
{
	for (var prop in this.opt.menus)
		if (!!this.opt.menus[prop].open)
			this.close(prop);
}

// *** smc_Popup class.
function smc_Popup(oOptions)
{
	this.opt = oOptions;
	this.popup_id = this.opt.custom_id ? this.opt.custom_id : 'smf_popup';
	this.show();
}

smc_Popup.prototype.show = function ()
{
	popup_class = 'popup_window ' + (this.opt.custom_class ? this.opt.custom_class : 'description');
	if (this.opt.icon_class)
		icon = '<span class="' + this.opt.icon_class + '"></span> ';
	else
		icon = this.opt.icon ? '<img src="' + this.opt.icon + '" class="icon" alt=""> ' : '';

	// Create the div that will be shown
	$('body').append('<div id="' + this.popup_id + '" class="popup_container"><div class="' + popup_class + '"><div class="catbg popup_heading"><a href="javascript:void(0);" class="main_icons hide_popup"></a>' + icon + this.opt.heading + '</div><div class="popup_content">' + this.opt.content + '</div></div></div>');

	// Show it
	this.popup_body = $('#' + this.popup_id).children('.popup_window');
	this.popup_body.parent().fadeIn(300);

	// Trigger hide on escape or mouse click
	var popup_instance = this;
	$(document).mouseup(function (e) {
		if ($('#' + popup_instance.popup_id).has(e.target).length === 0)
			popup_instance.hide();
	}).keyup(function(e){
		if (e.keyCode == 27)
			popup_instance.hide();
	});
	$('#' + this.popup_id).find('.hide_popup').click(function (){ return popup_instance.hide(); });

	return false;
}

smc_Popup.prototype.hide = function ()
{
	$('#' + this.popup_id).fadeOut(300, function(){ $(this).remove(); });

	return false;
}

// Remember the current position.
function storeCaret(oTextHandle)
{
	// Only bother if it will be useful.
	if ('createTextRange' in oTextHandle)
		oTextHandle.caretPos = document.selection.createRange().duplicate();
}

// Replaces the currently selected text with the passed text.
function replaceText(text, oTextHandle)
{
	// Attempt to create a text range (IE).
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
		caretPos.select();
	}
	// Mozilla text range replace.
	else if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart);
		var end = oTextHandle.value.substr(oTextHandle.selectionEnd);
		var scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text + end;

		if (oTextHandle.setSelectionRange)
		{
			oTextHandle.focus();
			var goForward = is_opera ? text.match(/\n/g).length : 0;
			oTextHandle.setSelectionRange(begin.length + text.length + goForward, begin.length + text.length + goForward);
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put it on the end.
	else
	{
		oTextHandle.value += text;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Surrounds the selected text with text1 and text2.
function surroundText(text1, text2, oTextHandle)
{
	// Can a text range be created?
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos, temp_length = caretPos.text.length;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text1 + caretPos.text + text2 + ' ' : text1 + caretPos.text + text2;

		if (temp_length == 0)
		{
			caretPos.moveStart('character', -text2.length);
			caretPos.moveEnd('character', -text2.length);
			caretPos.select();
		}
		else
			oTextHandle.focus(caretPos);
	}
	// Mozilla text range wrap.
	else if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart);
		var selection = oTextHandle.value.substr(oTextHandle.selectionStart, oTextHandle.selectionEnd - oTextHandle.selectionStart);
		var end = oTextHandle.value.substr(oTextHandle.selectionEnd);
		var newCursorPos = oTextHandle.selectionStart;
		var scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text1 + selection + text2 + end;

		if (oTextHandle.setSelectionRange)
		{
			var goForward = is_opera ? text1.match(/\n/g).length : 0, goForwardAll = is_opera ? (text1 + text2).match(/\n/g).length : 0;
			if (selection.length == 0)
				oTextHandle.setSelectionRange(newCursorPos + text1.length + goForward, newCursorPos + text1.length + goForward);
			else
				oTextHandle.setSelectionRange(newCursorPos, newCursorPos + text1.length + selection.length + text2.length + goForwardAll);
			oTextHandle.focus();
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put them on the end, then.
	else
	{
		oTextHandle.value += text1 + text2;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Checks if the passed input's value is nothing.
function isEmptyText(theField)
{
	// Copy the value so changes can be made..
	if (typeof(theField) == 'string')
		var theValue = theField;
	else
		var theValue = theField.value;

	// Strip whitespace off the left side.
	while (theValue.length > 0 && (theValue.charAt(0) == ' ' || theValue.charAt(0) == '\t'))
		theValue = theValue.substring(1, theValue.length);
	// Strip whitespace off the right side.
	while (theValue.length > 0 && (theValue.charAt(theValue.length - 1) == ' ' || theValue.charAt(theValue.length - 1) == '\t'))
		theValue = theValue.substring(0, theValue.length - 1);

	return theValue == '';
}

// Only allow form submission ONCE.
function submitonce(theform)
{
	smf_formSubmitted = true;

	// If there are any editors warn them submit is coming!
	for (var i = 0; i < smf_editorArray.length; i++)
		smf_editorArray[i].doSubmit();
}
function submitThisOnce(oControl)
{
	// oControl might also be a form.
	var oForm = 'form' in oControl ? oControl.form : oControl;

	var aTextareas = oForm.getElementsByTagName('textarea');
	for (var i = 0, n = aTextareas.length; i < n; i++)
		aTextareas[i].readOnly = true;

	return !smf_formSubmitted;
}

// Deprecated, as innerHTML is supported everywhere.
function setInnerHTML(oElement, sToValue)
{
	oElement.innerHTML = sToValue;
}

function getInnerHTML(oElement)
{
	return oElement.innerHTML;
}

// Set the "outer" HTML of an element.
function setOuterHTML(oElement, sToValue)
{
	if ('outerHTML' in oElement)
		oElement.outerHTML = sToValue;
	else
	{
		var range = document.createRange();
		range.setStartBefore(oElement);
		oElement.parentNode.replaceChild(range.createContextualFragment(sToValue), oElement);
	}
}

// Checks for variable in theArray.
function in_array(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return true;

	return false;
}

// Checks for variable in theArray.
function array_search(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return i;

	return null;
}

// Find a specific radio button in its group and select it.
function selectRadioByName(oRadioGroup, sName)
{
	if (!('length' in oRadioGroup))
		return oRadioGroup.checked = true;

	for (var i = 0, n = oRadioGroup.length; i < n; i++)
		if (oRadioGroup[i].value == sName)
			return oRadioGroup[i].checked = true;

	return false;
}

function selectAllRadio(oInvertCheckbox, oForm, sMask, sValue, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
		if (oForm[i].name != undefined && oForm[i].name.substr(0, sMask.length) == sMask && oForm[i].value == sValue && (!oForm[i].disabled || (typeof(bIgnoreDisabled) == 'boolean' && bIgnoreDisabled)))
			oForm[i].checked = true;
}

// Invert all checkboxes at once by clicking a single checkbox.
function invertAll(oInvertCheckbox, oForm, sMask, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
	{
		if (!('name' in oForm[i]) || (typeof(sMask) == 'string' && oForm[i].name.substr(0, sMask.length) != sMask && oForm[i].id.substr(0, sMask.length) != sMask))
			continue;

		if (!oForm[i].disabled || (typeof(bIgnoreDisabled) == 'boolean' && bIgnoreDisabled))
			oForm[i].checked = oInvertCheckbox.checked;
	}
}

// Keep the session alive - always!
var lastKeepAliveCheck = new Date().getTime();
function smf_sessionKeepAlive()
{
	var curTime = new Date().getTime();

	// Prevent a Firefox bug from hammering the server.
	if (smf_scripturl && curTime - lastKeepAliveCheck > 900000)
	{
		var tempImage = new Image();
		tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=keepalive;time=' + curTime;
		lastKeepAliveCheck = curTime;
	}

	window.setTimeout(smf_sessionKeepAlive, 1200000);
}
window.setTimeout(smf_sessionKeepAlive, 1200000);

// Set a theme option through javascript.
function smf_setThemeOption(theme_var, theme_value, theme_id, theme_cur_session_id, theme_cur_session_var, theme_additional_vars)
{
	// Compatibility.
	if (theme_cur_session_id == null)
		theme_cur_session_id = smf_session_id;
	if (typeof(theme_cur_session_var) == 'undefined')
		theme_cur_session_var = 'sesc';

	if (theme_additional_vars == null)
		theme_additional_vars = '';

	var tempImage = new Image();
	tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=jsoption;var=' + theme_var + ';val=' + theme_value + ';' + theme_cur_session_var + '=' + theme_cur_session_id + theme_additional_vars + (theme_id == null ? '' : '&th=' + theme_id) + ';time=' + (new Date().getTime());
}

// Shows the page numbers by clicking the dots (in compact view).
function expandPages(spanNode, baseLink, firstPage, lastPage, perPage)
{
	var replacement = '', i, oldLastPage = 0;
	var perPageLimit = 50;

	// Prevent too many pages to be loaded at once.
	if ((lastPage - firstPage) / perPage > perPageLimit)
	{
		oldLastPage = lastPage;
		lastPage = firstPage + perPageLimit * perPage;
	}

	// Calculate the new pages.
	for (i = firstPage; i < lastPage; i += perPage)
		replacement += baseLink.replace(/%1\$d/, i).replace(/%2\$s/, 1 + i / perPage).replace(/%%/g, '%');

	// Add the new page links.
	spanNode.before(replacement);

	if (oldLastPage)
		// Access the raw DOM element so the native onclick event can be overridden.
		spanNode.onclick = expandPages.bind(null, spanNode, baseLink, lastPage, oldLastPage, perPage);
	else
		spanNode.remove();
}

function smc_preCacheImage(sSrc)
{
	if (!('smc_aCachedImages' in window))
		window.smc_aCachedImages = [];

	if (!in_array(sSrc, window.smc_aCachedImages))
	{
		var oImage = new Image();
		oImage.src = sSrc;
	}
}


// *** smc_Cookie class.
function smc_Cookie(oOptions)
{
	this.opt = oOptions;
	this.oCookies = {};
	this.init();
}

smc_Cookie.prototype.init = function()
{
	if ('cookie' in document && document.cookie != '')
	{
		var aCookieList = document.cookie.split(';');
		for (var i = 0, n = aCookieList.length; i < n; i++)
		{
			var aNameValuePair = aCookieList[i].split('=');
			this.oCookies[aNameValuePair[0].replace(/^\s+|\s+$/g, '')] = decodeURIComponent(aNameValuePair[1]);
		}
	}
}

smc_Cookie.prototype.get = function(sKey)
{
	return sKey in this.oCookies ? this.oCookies[sKey] : null;
}

smc_Cookie.prototype.set = function(sKey, sValue)
{
	document.cookie = sKey + '=' + encodeURIComponent(sValue);
}


// *** smc_Toggle class.
function smc_Toggle(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = false;
	this.oCookie = null;
	this.init();
}

smc_Toggle.prototype.init = function ()
{
	// The master switch can disable this toggle fully.
	if ('bToggleEnabled' in this.opt && !this.opt.bToggleEnabled)
		return;

	// If cookies are enabled and they were set, override the initial state.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
	{
		// Initialize the cookie handler.
		this.oCookie = new smc_Cookie({});

		// Check if the cookie is set.
		var cookieValue = this.oCookie.get(this.opt.oCookieOptions.sCookieName)
		if (cookieValue != null)
			this.opt.bCurrentlyCollapsed = cookieValue == '1';
	}

	// Initialize the images to be clickable.
	if ('aSwapImages' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			this.opt.aSwapImages[i].isCSS = (typeof this.opt.aSwapImages[i].srcCollapsed == 'undefined');
			if (this.opt.aSwapImages[i].isCSS)
			{
				if (!this.opt.aSwapImages[i].cssCollapsed)
					this.opt.aSwapImages[i].cssCollapsed = 'toggle_down';
				if (!this.opt.aSwapImages[i].cssExpanded)
					this.opt.aSwapImages[i].cssExpanded = 'toggle_up';
			}
			else
			{
				// Preload the collapsed image.
				smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
			}

			// Display the image in case it was hidden.
			$('#' + this.opt.aSwapImages[i].sId).show();
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) == 'object' && oImage != null)
			{
				oImage.instanceRef = this;
				oImage.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
				}
				oImage.style.cursor = 'pointer';
			}
		}
	}

	// Initialize links.
	if ('aSwapLinks' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) == 'object' && oLink != null)
			{
				// Display the link in case it was hidden.
				if (oLink.style.display == 'none')
					oLink.style.display = '';

				oLink.instanceRef = this;
				oLink.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
					return false;
				}
			}
		}
	}

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this.changeState(true, true);
}

// Collapse or expand the section.
smc_Toggle.prototype.changeState = function(bCollapse, bInit)
{
	// Default bInit to false.
	bInit = typeof(bInit) !== 'undefined';

	// Handle custom function hook before collapse.
	if (!bInit && bCollapse && 'funcOnBeforeCollapse' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeCollapse;
		this.tmpMethod();
		delete this.tmpMethod;
	}

	// Handle custom function hook before expand.
	else if (!bInit && !bCollapse && 'funcOnBeforeExpand' in this.opt)
	{
		this.tmpMethod = this.opt.funcOnBeforeExpand;
		this.tmpMethod();
		delete this.tmpMethod;
	}

	// Loop through all the images that need to be toggled.
	if ('aSwapImages' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			this.opt.aSwapImages[i].altExpanded = this.opt.aSwapImages[i].altExpanded ? this.opt.aSwapImages[i].altExpanded : smf_collapseAlt;
			this.opt.aSwapImages[i].altCollapsed = this.opt.aSwapImages[i].altCollapsed ? this.opt.aSwapImages[i].altCollapsed : smf_expandAlt;
			if (this.opt.aSwapImages[i].isCSS)
			{
				$('#' + this.opt.aSwapImages[i].sId).toggleClass(this.opt.aSwapImages[i].cssCollapsed, bCollapse).toggleClass(this.opt.aSwapImages[i].cssExpanded, !bCollapse).attr('title', bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded);
			}
			else
			{
				var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
				if (typeof(oImage) == 'object' && oImage != null)
				{
					// Only (re)load the image if it's changed.
					var sTargetSource = bCollapse ? this.opt.aSwapImages[i].srcCollapsed : this.opt.aSwapImages[i].srcExpanded;
					if (oImage.src != sTargetSource)
						oImage.src = sTargetSource;

					oImage.alt = oImage.title = bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded;
				}
			}
		}
	}

	// Loop through all the links that need to be toggled.
	if ('aSwapLinks' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) == 'object' && oLink != null)
				setInnerHTML(oLink, bCollapse ? this.opt.aSwapLinks[i].msgCollapsed : this.opt.aSwapLinks[i].msgExpanded);
		}
	}

	// Now go through all the sections to be collapsed.
	for (var i = 0, n = this.opt.aSwappableContainers.length; i < n; i++)
	{
		if (this.opt.aSwappableContainers[i] == null)
			continue;

		var oContainer = document.getElementById(this.opt.aSwappableContainers[i]);
		if (typeof(oContainer) == 'object' && oContainer != null)
		{
			if (!!this.opt.bNoAnimate || bInit)
			{
				$(oContainer).toggle(!bCollapse);
			}
			else
			{
				if (bCollapse)
				{
					if (this.opt.aHeader != null && this.opt.aHeader.hasClass('cat_bar'))
						$(this.opt.aHeader).addClass('collapsed');
					$(oContainer).slideUp();
				}
				else
				{
					if (this.opt.aHeader != null && this.opt.aHeader.hasClass('cat_bar'))
						$(this.opt.aHeader).removeClass('collapsed');
					$(oContainer).slideDown();
				}
			}
		}
	}

	// Update the new state.
	this.bCollapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed | 0);

	if (!bInit && 'oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		smf_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed | 0, 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, smf_session_id, smf_session_var, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
}

smc_Toggle.prototype.toggle = function()
{
	// Change the state by reversing the current state.
	this.changeState(!this.bCollapsed);
}


function ajax_indicator(turn_on)
{
	if (ajax_indicator_ele == null)
	{
		ajax_indicator_ele = document.getElementById('ajax_in_progress');

		if (ajax_indicator_ele == null && typeof(ajax_notification_text) != null)
		{
			create_ajax_indicator_ele();
		}
	}

	if (ajax_indicator_ele != null)
	{
		ajax_indicator_ele.style.display = turn_on ? 'block' : 'none';
	}
}

function create_ajax_indicator_ele()
{
	// Create the div for the indicator.
	ajax_indicator_ele = document.createElement('div');

	// Set the id so it'll load the style properly.
	ajax_indicator_ele.id = 'ajax_in_progress';

	// Set the text.  (Note:  You MUST append here and not overwrite.)
	ajax_indicator_ele.innerHTML += ajax_notification_text;

	// Finally attach the element to the body.
	document.body.appendChild(ajax_indicator_ele);
}

/**
 * Parse an HTML template string into a DocumentFragment.
 *
 * @param {string} template - The HTML string to parse.
 * @returns {DocumentFragment} - A fragment containing parsed nodes.
 */
function parseTemplateToFragment(template) {
	const parser = new DOMParser();
	const doc = parser.parseFromString(template, 'text/html');
	const frag = document.createDocumentFragment();

	while (doc.body.firstChild) {
		frag.appendChild(doc.body.firstChild);
	}

	return frag;
}

/**
 * Replace a single placeholder in all text nodes and attributes within a fragment or element.
 *
 * This function traverses all text nodes under the specified root node using a TreeWalker.
 * When it finds a text node containing the placeholder, it checks the type of the replacement value.
 * If the value is a string, it simply replaces all occurrences of the placeholder in the text node.
 * If the value is a Node, the function splits the text node into "before" and "after" segments,
 * inserts the replacement node between them, and removes the original text node.
 *
 * After processing all text nodes, the function iterates over all elements within the root node
 * and examines their attributes. Only string replacements are supported in attributes, so if
 * an attribute value contains the placeholder, it is replaced using standard string substitution.
 *
 * @param {Node} root The root node (fragment, element, etc.)
 * @param {string} placeholder The placeholder to replace (e.g. "%select_id%").
 * @param {string|Node} value Replacement string or DOM node.
 */
function replacePlaceholder(root, placeholder, value) {
	const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);

	let node;
	while ((node = walker.nextNode())) {
		const idx = node.nodeValue.indexOf(placeholder);

		if (idx !== -1) {
			if (value instanceof Node) {
				// Split text into before/after parts
				const before = node.nodeValue.slice(0, idx);
				const after = node.nodeValue.slice(idx + placeholder.length);

				const parent = node.parentNode;
				if (before) {
					parent.insertBefore(document.createTextNode(before), node);
				}
				parent.insertBefore(value, node);
				if (after) {
					parent.insertBefore(document.createTextNode(after), node);
				}

				parent.removeChild(node);
			} else {
				node.nodeValue = node.nodeValue.replace(placeholder, value);
			}
		}
	}

	if (typeof value === 'string') {
		const elements = root.querySelectorAll('*');
		for (let i = 0; i < elements.length; i++) {
			const el = elements[i];
			for (let j = 0; j < el.attributes.length; j++) {
				const attr = el.attributes[j];
				if (attr.value.includes(placeholder)) {
					attr.value = attr.value.replace(placeholder, value);
				}
			}
		}
	}
}

class JumpTo {
	static instances = [];

	constructor(opt) {
		this.opt = opt;
		this.dropdownList = null;
		this.oContainer = document.getElementById(opt.sContainerId);
		this.sTemplate = opt.sJumpToTemplate || '%dropdown_list%';
		this.showSelect();

		let timeout = null;

		// Register instance
		JumpTo.instances.push(this);

		// Detect if a "coarse pointer" (usually a touch screen) is the primary input device.
		if (window.matchMedia("(pointer: coarse)").matches)
		{
			const focusHandler = () =>
			{
				this.grabJumpToContent();
				this.oContainer.removeEventListener('focus', focusHandler);
			};
			this.oContainer.addEventListener('focus', focusHandler);
		}
		else
		{
			const mouseOverHandler = () =>
			{
				timeout = setTimeout(() =>
				{
					this.grabJumpToContent();
					this.oContainer.removeEventListener('mouseover', mouseOverHandler);
					this.oContainer.removeEventListener('mouseout', mouseOutHandler);
				}, 200);
			};

			const mouseOutHandler = () =>
			{
				clearTimeout(timeout);
			};

			this.oContainer.addEventListener('mouseover', mouseOverHandler);
			this.oContainer.addEventListener('mouseout', mouseOutHandler);
		}
	}

	// This function will retrieve the contents needed for the jump to boxes.
	grabJumpToContent()
	{
		ajax_indicator(true);

		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=jumpto;xml', (xml) =>
		{
			const items = xml.getElementsByTagName('smf')[0].getElementsByTagName('item');
			const boards = [];

			for (let i = 0; i < items.length; i++)
			{
				const item = items[i];
				boards.push({
					id: parseInt(item.getAttribute('id')),
					isCategory: item.getAttribute('type') === 'category',
					name: item.firstChild.nodeValue.removeEntities(),
					is_current: false,
					isRedirect: parseInt(item.getAttribute('is_redirect')),
					childLevel: parseInt(item.getAttribute('childlevel'))
				});
			}

			ajax_indicator(false);

			for (let i = 0; i < JumpTo.instances.length; i++)
			{
				JumpTo.instances[i].fillSelect(boards);
			}
		});
	}

	// Show select using template
	showSelect()
	{
		const el = this.oContainer;
		const frag = parseTemplateToFragment(this.sTemplate);

		// Create select element
		const select = document.createElement('select');
		select.id = this.opt.sContainerId + '_select';
		select.name = this.opt.sCustomName || select.id;
		if (this.opt.sClassName)
		{
			select.className = this.opt.sClassName;
		}
		if (this.opt.bDisabled)
		{
			select.disabled = true;
		}

		// Default option
		const defaultOption = document.createElement('option');
		defaultOption.value = this.opt.bNoRedirect ? this.opt.iCurBoardId : '?board=' + this.opt.iCurBoardId + '.0';
		defaultOption.textContent = this.opt.sBoardChildLevelIndicator.repeat(this.opt.iCurBoardChildLevel) + this.opt.sBoardPrefix + this.opt.sCurBoardName.removeEntities();
		select.appendChild(defaultOption);

		// Replace placeholders using node walker
		replacePlaceholder(frag, '%select_id%', this.opt.sContainerId + '_select');
		replacePlaceholder(frag, '%dropdown_list%', select);

		if (this.opt.sGoButtonLabel)
		{
			const btn = document.createElement('button');
			btn.className = 'button';
			btn.textContent = this.opt.sGoButtonLabel;
			btn.addEventListener('click', () =>
			{
				window.location.href = smf_prepareScriptUrl(smf_scripturl) + 'board=' + this.opt.iCurBoardId + '.0';
			});

			frag.append(' ', btn);
		}

		// Append processed template to container
		el.innerHTML = ''; // clear existing
		el.appendChild(frag);

		this.dropdownList = select;

		if (!this.opt.bNoRedirect)
		{
			select.addEventListener('change', function()
			{
				const val = this.options[this.selectedIndex].value;
				if (this.selectedIndex > 0 && val)
				{
					window.location.href = smf_scripturl + (val.startsWith('?') ? val.substring(1) : val);
				}
			});
		}
	}

	// Fill select with boards/categories
	fillSelect(boards)
	{
		if (!this.dropdownList)
		{
			return;
		}

		const fragment = document.createDocumentFragment();
		const dashOptionTemplate = document.createElement('option');
		dashOptionTemplate.textContent = this.opt.sCatSeparator;
		dashOptionTemplate.disabled = true;

		if (this.opt.bNoRedirect)
		{
			if (this.dropdownList.options[0])
			{
				this.dropdownList.options[0].disabled = true;
			}
		}

		let lastWasCategory = false;

		for (let i = 0; i < boards.length; i++)
		{
			const item = boards[i];

			// If we've reached the currently selected board add all items so far.
			if (!item.isCategory && item.id === this.opt.iCurBoardId)
			{
				this.dropdownList.insertBefore(fragment, this.dropdownList.options[0]);
				continue;
			}

			if (item.isCategory)
			{
				if (!lastWasCategory)
				{
					fragment.appendChild(dashOptionTemplate.cloneNode(true));
					lastWasCategory = true;
				}
			}
			else
			{
				lastWasCategory = false;
			}

			const option = document.createElement('option');
			option.textContent = (item.isCategory ? this.opt.sCatPrefix : this.opt.sBoardChildLevelIndicator.repeat(item.childLevel) + this.opt.sBoardPrefix) + item.name;
			option.value = item.isCategory ? '#c' + item.id : '?board=' + item.id + '.0';

			if (this.opt.bNoRedirect && (item.isCategory || item.isRedirect))
			{
				option.disabled = true;
			}

			fragment.appendChild(option);

			if (item.isCategory)
			{
				fragment.appendChild(dashOptionTemplate.cloneNode(true));
			}
		}

		// Add the remaining items after the currently selected item.
		this.dropdownList.appendChild(fragment);
	}
}

// IconList class
function IconList(options) {
	this.opt = options || {};

	// Default CSS classes
	this.opt.sBoxClass = this.opt.sBoxClass || 'icon_list_box';
	this.opt.sContainerClass = this.opt.sContainerClass || 'icon_list_container';
	this.opt.sItemClass = this.opt.sItemClass || 'icon_list_item';

	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.iCurMessageId = 0;
	this.oClickedIcon = null;

	if (!IconList.instances) IconList.instances = [];
	IconList.instances.push(this);

	this.initIcons();
}

// Replace all message icons by icons with hoverable and clickable div's.
IconList.prototype.initIcons = function () {
	const prefixLength = this.opt.sIconIdPrefix.length;
	const imgs = document.images;

	for (let i = 0; i < imgs.length; i++) {
		const img = imgs[i];
		if (img.id.substr(0, prefixLength) === this.opt.sIconIdPrefix) {
			const div = document.createElement('div');
			div.className = this.opt.sBoxClass;
			div.appendChild(img.cloneNode(true));

			div.addEventListener('click', this.openPopup.bind(this, div, parseInt(img.id.substr(prefixLength), 10)));

			img.parentNode.replaceChild(div, img);
		}
	}
};

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (div, messageId) {
	this.iCurMessageId = messageId;
	this.oClickedIcon = div;

	if (!this.bListLoaded && this.oContainerDiv == null) {
		this.oContainerDiv = document.createElement('div');
		this.oContainerDiv.className = this.opt.sContainerClass;
		document.body.appendChild(this.oContainerDiv);

		ajax_indicator(true);
		getXMLDocument(
			smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=messageicons;board=' + this.opt.iBoardId + ';xml',
			this.onIconsReceived.bind(this)
		);
	}

	const pos = smf_itemPos(div);
	this.oContainerDiv.style.top = (pos[1] + div.offsetHeight) + 'px';
	this.oContainerDiv.style.left = (pos[0] - 1) + 'px';

	if (this.bListLoaded) this.oContainerDiv.style.display = 'block';

	document.body.addEventListener('mousedown', IconList.onWindowMouseDown);
};

// Setup the list of icons once it is received through xmlHTTP.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	if (!oXMLDoc) return;

	ajax_indicator(false);
	const icons = oXMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('icon');
	const frag = document.createDocumentFragment();

	for (let i = 0; i < icons.length; i++) {
		const icon = icons[i];
		const span = document.createElement('span');
		span.className = this.opt.sItemClass;

		const img = document.createElement('img');
		img.src = icon.getAttribute('url');
		img.alt = icon.getAttribute('name');
		img.title = icon.firstChild ? icon.firstChild.nodeValue : '';
		img.style.verticalAlign = 'middle';

		span.appendChild(img);

		span.addEventListener('pointerdown', this.onItemMouseDown.bind(this, span, icon.getAttribute('value')));

		frag.appendChild(span);
	}

	this.oContainerDiv.appendChild(frag);
	this.oContainerDiv.style.display = 'block';
	this.bListLoaded = true;
};

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function(span, newIcon) {
	if (this.iCurMessageId === 0) return;

	ajax_indicator(true);
	getXMLDocument(
		smf_prepareScriptUrl(smf_scripturl) +
		'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' +
		smf_session_var + '=' + smf_session_id + ';icon=' + newIcon + ';xml',
		oXMLDoc => {
			ajax_indicator(false);
			if (!oXMLDoc) return;

			const messageEl = oXMLDoc.getElementsByTagName('message')[0];
			if (!messageEl) return;

			const curMessageId = (messageEl.getAttribute('id') || '').replace(/^\D+/g, '');
			if (!messageEl.getElementsByTagName('error')[0]) {
				const modifiedEl = messageEl.getElementsByTagName('modified')[0];
				if (this.opt.bShowModify && modifiedEl) {
					const modContainer = document.getElementById('modified_' + curMessageId);
					if (modContainer) modContainer.innerHTML = modifiedEl.textContent;
				}
				const img = this.oClickedIcon.getElementsByTagName('img')[0];
				if (img) img.src = span.getElementsByTagName('img')[0].src;
			}
		}
	);
};

// Event handler for clicking outside the list (will make the list disappear).
IconList.onWindowMouseDown = function() {
	if (!IconList.instances) return;
	for (const inst of IconList.instances) {
		inst.collapseList();
	}
};

// Collapse the list of icons.
IconList.prototype.collapseList = function() {
	if (!this.oClickedIcon || !this.oContainerDiv) return;
	this.oContainerDiv.style.display = 'none';
	this.iCurMessageId = 0;
};

// Handy shortcuts for getting the mouse position on the screen - only used for IE at the moment.
function smf_mousePose(oEvent)
{
	var x = 0;
	var y = 0;

	if (oEvent.pageX)
	{
		y = oEvent.pageY;
		x = oEvent.pageX;
	}
	else if (oEvent.clientX)
	{
		x = oEvent.clientX + (document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft);
		y = oEvent.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop);
	}

	return [x, y];
}

// Short function for finding the actual position of an item.
function smf_itemPos(itemHandle)
{
	var itemX = 0;
	var itemY = 0;

	if ('offsetParent' in itemHandle)
	{
		itemX = itemHandle.offsetLeft;
		itemY = itemHandle.offsetTop;
		while (itemHandle.offsetParent && typeof(itemHandle.offsetParent) == 'object')
		{
			itemHandle = itemHandle.offsetParent;
			itemX += itemHandle.offsetLeft;
			itemY += itemHandle.offsetTop;
		}
	}
	else if ('x' in itemHandle)
	{
		itemX = itemHandle.x;
		itemY = itemHandle.y;
	}

	return [itemX, itemY];
}

// This function takes the script URL and prepares it to allow the query string to be appended to it.
function smf_prepareScriptUrl(sUrl)
{
	// Ensure index.php is in the URL even when the option to hide it is enabled.
	if (sUrl.indexOf('/index.php') == -1) {
		sUrl = sUrl + '/index.php';
	}

	return sUrl.indexOf('?') == -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) == '?' || sUrl.charAt(sUrl.length - 1) == '&' || sUrl.charAt(sUrl.length - 1) == ';' ? '' : ';');
}

var aOnloadEvents = new Array();
function addLoadEvent(fNewOnload)
{
	// If there's no event set, just set this one
	if (typeof(fNewOnload) == 'function' && (!('onload' in window) || typeof(window.onload) != 'function'))
		window.onload = fNewOnload;

	// If there's just one event, setup the array.
	else if (aOnloadEvents.length == 0)
	{
		aOnloadEvents[0] = window.onload;
		aOnloadEvents[1] = fNewOnload;
		window.onload = function() {
			for (var i = 0, n = aOnloadEvents.length; i < n; i++)
			{
				if (typeof(aOnloadEvents[i]) == 'function')
					aOnloadEvents[i]();
				else if (typeof(aOnloadEvents[i]) == 'string')
					eval(aOnloadEvents[i]);
			}
		}
	}

	// This isn't the first event function, add it to the list.
	else
		aOnloadEvents[aOnloadEvents.length] = fNewOnload;
}

// Get the text in a code tag.
function smfSelectText(oCurElement, bActOnElement)
{
	// The place we're looking for is one div up, and next door - if it's auto detect.
	if (typeof(bActOnElement) == 'boolean' && bActOnElement)
		var oCodeArea = document.getElementById(oCurElement);
	else
		var oCodeArea = oCurElement.parentNode.nextSibling;

	if (typeof(oCodeArea) != 'object' || oCodeArea == null)
		return false;

	// Start off with my favourite, internet explorer.
	if ('createTextRange' in document.body)
	{
		var oCurRange = document.body.createTextRange();
		oCurRange.moveToElementText(oCodeArea);
		oCurRange.select();
	}
	// Firefox at el.
	else if (window.getSelection)
	{
		var oCurSelection = window.getSelection();
		// Safari is special!
		if (oCurSelection.setBaseAndExtent)
		{
			oCurSelection.setBaseAndExtent(oCodeArea, 0, oCodeArea, oCodeArea.childNodes.length);
		}
		else
		{
			var curRange = document.createRange();
			curRange.selectNodeContents(oCodeArea);

			oCurSelection.removeAllRanges();
			oCurSelection.addRange(curRange);
		}
	}

	return false;
}

// A function used to clean the attachments on post page
function cleanFileInput(idElement)
{
	// Simpler solutions work in Opera, IE, Safari and Chrome.
	if (is_opera || is_ie || is_safari || is_chrome)
	{
		document.getElementById(idElement).outerHTML = document.getElementById(idElement).outerHTML;
	}
	// What else can we do? By the way, this doesn't work in Chrome and Mac's Safari.
	else
	{
		document.getElementById(idElement).type = 'input';
		document.getElementById(idElement).type = 'file';
	}
}

function reActivate()
{
	document.forms.postmodify.message.readOnly = false;
}

// The actual message icon selector.
function showimage()
{
	document.images.icons.src = icon_urls[document.forms.postmodify.icon.options[document.forms.postmodify.icon.selectedIndex].value];
}

function expandThumb(thumbID)
{
	var img = document.getElementById('thumb_' + thumbID);
	var link = document.getElementById('link_' + thumbID);

	// save the currently displayed image attributes
	var tmp_src = img.src;
	var tmp_height = img.style.height;
	var tmp_width = img.style.width;

	// set the displayed image attributes to the link attributes, this will expand in place
	img.src = link.href;
	img.style.width = link.style.width;
	img.style.height = link.style.height;
	img.classList.toggle('original_size');

	// place the image attributes back
	link.href = tmp_src;
	link.style.width = tmp_width;
	link.style.height = tmp_height;

	return false;
}

function pollOptions()
{
	var expire_time = document.getElementById('poll_expire');

	if (isEmptyText(expire_time) || expire_time.value == 0)
	{
		document.forms.postmodify.poll_hide[2].disabled = true;
		if (document.forms.postmodify.poll_hide[2].checked)
			document.forms.postmodify.poll_hide[1].checked = true;
	}
	else
		document.forms.postmodify.poll_hide[2].disabled = false;
}

function generateDays(offset)
{
	// Work around JavaScript's lack of support for default values...
	offset = typeof(offset) != 'undefined' ? offset : '';

	var days = 0, selected = 0;
	var dayElement = document.getElementById("day" + offset), yearElement = document.getElementById("year" + offset), monthElement = document.getElementById("month" + offset);

	var monthLength = [
		31, 28, 31, 30,
		31, 30, 31, 31,
		30, 31, 30, 31
	];
	if (yearElement.options[yearElement.selectedIndex].value % 4 == 0)
		monthLength[1] = 29;

	selected = dayElement.selectedIndex;
	while (dayElement.options.length)
		dayElement.options[0] = null;

	days = monthLength[monthElement.value - 1];

	for (i = 1; i <= days; i++)
		dayElement.options[dayElement.length] = new Option(i, i);

	if (selected < days)
		dayElement.selectedIndex = selected;
}

function initSearch()
{
	if (document.forms.searchform.search.value.indexOf("%u") != -1)
		document.forms.searchform.search.value = unescape(document.forms.searchform.search.value);
}

function selectBoards(ids, aFormID)
{
	var toggle = true;
	var aForm = document.getElementById(aFormID);

	for (i = 0; i < ids.length; i++)
		toggle = toggle & aForm["brd" + ids[i]].checked;

	for (i = 0; i < ids.length; i++)
		aForm["brd" + ids[i]].checked = !toggle;
}

function updateRuleDef(optNum)
{
	if (document.getElementById("ruletype" + optNum).value == "gid")
	{
		document.getElementById("defdiv" + optNum).style.display = "none";
		document.getElementById("defseldiv" + optNum).style.display = "";
	}
	else if (document.getElementById("ruletype" + optNum).value == "bud" || document.getElementById("ruletype" + optNum).value == "")
	{
		document.getElementById("defdiv" + optNum).style.display = "none";
		document.getElementById("defseldiv" + optNum).style.display = "none";
	}
	else
	{
		document.getElementById("defdiv" + optNum).style.display = "";
		document.getElementById("defseldiv" + optNum).style.display = "none";
	}
}

function updateActionDef(optNum)
{
	if (document.getElementById("acttype" + optNum).value == "lab")
	{
		document.getElementById("labdiv" + optNum).style.display = "";
	}
	else
	{
		document.getElementById("labdiv" + optNum).style.display = "none";
	}
}

function makeToggle(el, text)
{
	var t = document.createElement("a");
	t.href = 'javascript:void(0);';
	t.textContent = text;
	t.className = 'toggle_down';
	t.addEventListener('click', function()
	{
		var d = this.nextSibling;
		d.classList.toggle('hidden');
		this.className = this.className == 'toggle_down' ? 'toggle_up' : 'toggle_down';
	}, false);
	el.classList.add('hidden');
	el.parentNode.insertBefore(t, el);
}

function smc_resize(selector)
{
	var allElements = [];

	$(selector).each(function(){
		$thisElement = $(this);

		// Get rid of the width and height attributes.
		$thisElement.removeAttr('width').removeAttr('height');

		// Get the default vars.
		$thisElement.basedElement = $thisElement.parent();
		$thisElement.defaultWidth = $thisElement.width();
		$thisElement.defaultHeight = $thisElement.height();
		$thisElement.aspectRatio = $thisElement.defaultHeight / $thisElement.defaultWidth;

		allElements.push($thisElement);
	});

	$(window).resize(function(){
		$(allElements).each(function(){
			_innerElement = this;

			// Get the new width and height.
			var newWidth = _innerElement.basedElement.width();
			var newHeight = (newWidth * _innerElement.aspectRatio) <= _innerElement.defaultHeight ? (newWidth * _innerElement.aspectRatio) : _innerElement.defaultHeight;

			// If the new width is lower than the "default width" then apply some resizing. No? then go back to our default sizes
			var applyResize = (newWidth <= _innerElement.defaultWidth),
				applyWidth = !applyResize ? _innerElement.defaultWidth : newWidth,
				applyHeight = !applyResize ? _innerElement.defaultHeight : newHeight;

			// Gotta check the applied width and height is actually something!
			if (applyWidth <= 0 && applyHeight <= 0) {
				applyWidth = _innerElement.defaultWidth;
				applyHeight = _innerElement.defaultHeight;
			}

			// Finally resize the element!
			_innerElement.width(applyWidth).height(applyHeight);
		});

	// Kick off one resize to fix all elements on page load.
	}).resize();
}

$(function() {
	$('.buttonlist > .dropmenu').each(function(index, item) {
		$(item).prev().click(function(e) {
			e.stopPropagation();
			e.preventDefault();

			if ($(item).is(':visible')) {
				$(item).css('display', 'none');

				return true;
			}

			$(item).css('display', 'block');
			$(item).css('top', $(this).offset().top + $(this).height());
			$(item).css('left', Math.max($(this).offset().left - $(item).width() + $(this).outerWidth(), 0));
			$(item).height($(item).find('div:first').height());
		});
		$(document).click(function() {
			$(item).css('display', 'none');
		});
	});

	$('.edit_history_list').each(function(index, item) {
		$(item).prev().click(function(e) {
			e.stopPropagation();
			e.preventDefault();

			if ($(item).is(':visible')) {
				$(item).css('display', 'none');

				return true;
			}

			$(item).css('display', 'block');
		});
		$(document).click(function() {
			$(item).css('display', 'none');
		});
	});

	// Generic confirmation message.
	$(document).on('click', '.you_sure', function() {
		if (this.getAttribute('type') === 'checkbox' && !this.checked) {
			return true;
		}

		var custom_message = $(this).attr('data-confirm');
		var timeBefore = new Date();
		var result = confirm(custom_message ? custom_message.replace(/-n-/g, "\n") : smf_you_sure);
		var timeAfter = new Date();

		// Check if the browser disabled the alert
		if (!result && (timeAfter - timeBefore) < 10)
			return true;

		return result;
	});

	// Generic event for smfSelectText()
	$('.smf_select_text').on('click', function(e) {
		e.preventDefault();

		// Do you want to target yourself?
		var actOnElement = $(this).attr('data-actonelement');

		return typeof actOnElement !== "undefined" ? smfSelectText(actOnElement, true) : smfSelectText(this);
	});

	// Show the Expand bbc button if needed
	$('.bbc_code').each(function(index, item) {
		if($(item).css('max-height') == 'none')
			return;

		if($(item).prop('scrollHeight') > parseInt($(item).css('max-height'), 10))
			$(item.previousSibling).find('.smf_expand_code').removeClass('hidden');
	});
	// Expand or Shrink the code bbc area
	$('.smf_expand_code').on('click', function(e) {
		e.preventDefault();

		var oCodeArea = this.parentNode.nextSibling;

		if(oCodeArea.classList.contains('expand_code')) {
			$(oCodeArea).removeClass('expand_code');
			$(this).html($(this).attr('data-expand-txt'));
		}
		else {
			$(oCodeArea).addClass('expand_code');
			$(this).html($(this).attr('data-shrink-txt'));
		}
	});

	// Expand quotes
	if ((typeof(smf_quote_expand) != 'undefined') && (smf_quote_expand > 0))
	{
		$('blockquote').each(function(index, item) {

			let cite = $(item).find('cite').first();
			let quote_height = parseInt($(item).height());

			if(quote_height < smf_quote_expand)
				return;

			$(item).css({
				'overflow-y': 'hidden',
				'max-height': smf_quote_expand +'px'
			});

			let anchor = $('<a/>', {
				text: ' [' + smf_txt_expand + ']',
				class: 'expand'
			});

			if (cite.length)
				cite.append(anchor);

			$(item).on('click', 'a.expand', function(event) {
				event.preventDefault();

				if (smf_quote_expand < parseInt($(item).height()))
				{
					cite.find('a.expand').text(' ['+ smf_txt_expand +']');
					$(item).css({
						'overflow-y': 'hidden',
						'max-height': smf_quote_expand +'px'
					});
				}

				else
				{
					cite.find('a.expand').text(' ['+ smf_txt_shrink +']');
					$(item).css({
						'overflow-y': 'visible',
						'max-height': (quote_height + 10) +'px'
					});

					expand_quote_parent($(item));
				}

				return false;
			});
		});
	}
});

function expand_quote_parent(oElement)
{
	$.each(oElement.parentsUntil('div.inner'), function( index, value ) {
		$(value).css({
			'overflow-y': 'visible',
			'max-height': '',
		}).find('a.expand').first().text(' ['+ smf_txt_shrink +']');
	});
}

function avatar_fallback(e) {
	var e = window.e || e;
	var default_url = smf_avatars_url + '/default.png';

	if (e.target.tagName !== 'IMG' || !e.target.classList.contains('avatar') || e.target.src === default_url )
		return;

	e.target.src = default_url;
	return true;
}

if (document.addEventListener)
	document.addEventListener("error", avatar_fallback, true);
else
	document.attachEvent("error", avatar_fallback);

// SMF Preview handler.
function smc_preview_post(oOptions)
{
	this.opts = oOptions;
	this.previewXMLSupported = true;
	this.init();
}

smc_preview_post.prototype.init = function ()
{
	if (this.opts.sPreviewLinkContainerID)
		$('#' + this.opts.sPreviewLinkContainerID).on('click', this.doPreviewPost.bind(this));
	else
		$(document.forms).find("input[name='preview']").on('click', this.doPreviewPost.bind(this));
}

smc_preview_post.prototype.doPreviewPost = function (event)
{
	event.preventDefault();

	if (!this.previewXMLSupported)
		return submitThisOnce(document.forms.postmodify);

	var new_replies = new Array();
	if (window.XMLHttpRequest)
	{
		// @todo Currently not sending option checkboxes.
		var x = new Array();
		var textFields = ['subject', this.opts.sPostBoxContainerID, this.opts.sSessionVar, 'icon', 'guestname', 'email', 'evtitle', 'question', 'topic'];
		var numericFields = [
			'board', 'topic', 'last_msg',
			'eventid', 'calendar', 'year', 'month', 'day',
			'poll_max_votes', 'poll_expire', 'poll_change_vote', 'poll_hide'
		];
		var checkboxFields = [
			'ns'
		];

		// Text Fields.
		for (var i = 0, n = textFields.length; i < n; i++)
			if (textFields[i] in document.forms.postmodify)
			{
				// Handle the WYSIWYG editor.
				var e = $('#' + this.opts.sPostBoxContainerID).get(0);

				// After moving this from Post template, html() stopped working in all cases.
				if (textFields[i] == this.opts.sPostBoxContainerID && sceditor.instance(e) != undefined && typeof sceditor.instance(e).getText().html !== 'undefined')
					x[x.length] = textFields[i] + '=' + sceditor.instance(e).getText().html().php_to8bit().php_urlencode();
				else if (textFields[i] == this.opts.sPostBoxContainerID && sceditor.instance(e) != undefined)
					x[x.length] = textFields[i] + '=' + sceditor.instance(e).getText().php_to8bit().php_urlencode();
				else if (typeof document.forms.postmodify[textFields[i]].value.html !== 'undefined')
					x[x.length] = textFields[i] + '=' + document.forms.postmodify[textFields[i]].value.html().php_to8bit().php_urlencode();
				else
					x[x.length] = textFields[i] + '=' + document.forms.postmodify[textFields[i]].value.php_to8bit().php_urlencode();
			}

		// Numbers.
		for (var i = 0, n = numericFields.length; i < n; i++)
			if (numericFields[i] in document.forms.postmodify && 'value' in document.forms.postmodify[numericFields[i]])
				x[x.length] = numericFields[i] + '=' + parseInt(document.forms.postmodify.elements[numericFields[i]].value);

		// Checkboxes.
		for (var i = 0, n = checkboxFields.length; i < n; i++)
			if (checkboxFields[i] in document.forms.postmodify && document.forms.postmodify.elements[checkboxFields[i]].checked)
				x[x.length] = checkboxFields[i] + '=' + document.forms.postmodify.elements[checkboxFields[i]].value;

		// Poll options.
		var i = 0;
		while ('options[' + i + ']' in document.forms.postmodify)
		{
			x[x.length] = 'options[' + i + ']=' +
				document.forms.postmodify.elements['options[' + i + ']'].value.php_to8bit().php_urlencode();
			i++;
		}

		sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=post2' + (this.opts.iCurrentBoard ? ';board=' + this.opts.iCurrentBoard : '') + (this.opts.bMakePoll ? ';poll' : '') + ';preview;xml', x.join('&'), this.onDocSent.bind(this));

		document.getElementById(this.opts.sPreviewSectionContainerID).style.display = '';
		setInnerHTML(document.getElementById(this.opts.sPreviewSubjectContainerID), this.opts.sTxtPreviewTitle);
		setInnerHTML(document.getElementById(this.opts.sPreviewBodyContainerID), this.opts.sTxtPreviewFetch);

		return false;
	}
	else
		return submitThisOnce(document.forms.postmodify);
}

smc_preview_post.prototype.onDocSent = function (XMLDoc)
{
	if (!XMLDoc)
	{
		document.forms.postmodify.preview.onclick = new function ()
		{
			return true;
		}
		document.forms.postmodify.preview.click();
	}

	// Show the preview section.
	var preview = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('preview')[0];
	setInnerHTML(document.getElementById(this.opts.sPreviewSubjectContainerID), preview.getElementsByTagName('subject')[0].firstChild.nodeValue);

	var bodyText = '';
	for (var i = 0, n = preview.getElementsByTagName('body')[0].childNodes.length; i < n; i++)
		if (preview.getElementsByTagName('body')[0].childNodes[i].nodeValue != null)
			bodyText += preview.getElementsByTagName('body')[0].childNodes[i].nodeValue;

	setInnerHTML(document.getElementById(this.opts.sPreviewBodyContainerID), bodyText);
	$('#' + this.opts.sPreviewBodyContainerID + ' .smf_select_text').on('click', function(e) {
		e.preventDefault();

		// Do you want to target yourself?
		var actOnElement = $(this).attr('data-actonelement');

		return typeof actOnElement !== "undefined" ? smfSelectText(actOnElement, true) : smfSelectText(this);
	});
	document.getElementById(this.opts.sPreviewBodyContainerID).className = 'windowbg';

	// Show a list of errors (if any).
	var errors = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('errors')[0];
	var errorList = new Array();
	for (var i = 0, numErrors = errors.getElementsByTagName('error').length; i < numErrors; i++)
		errorList[errorList.length] = errors.getElementsByTagName('error')[i].firstChild.nodeValue;
	document.getElementById(this.opts.sErrorsContainerID).style.display = numErrors == 0 ? 'none' : '';
	document.getElementById(this.opts.sErrorsContainerID).className = errors.getAttribute('serious') == 1 ? 'errorbox' : 'noticebox';
	document.getElementById(this.opts.sErrorsSeriousContainerID).style.display = numErrors == 0 ? 'none' : '';
	setInnerHTML(document.getElementById(this.opts.sErrorsListContainerID), numErrors == 0 ? '' : errorList.join('<br>'));

	// Adjust the color of captions if the given data is erroneous.
	var captions = errors.getElementsByTagName('caption');
	for (var i = 0, numCaptions = errors.getElementsByTagName('caption').length; i < numCaptions; i++)
	{
		if (document.getElementById(this.opts.sCaptionContainerID.replace('%ID%', captions[i].getAttribute('name'))))
			document.getElementById(this.opts.sCaptionContainerID.replace('%ID%', captions[i].getAttribute('name'))).className = captions[i].getAttribute('class');
	}

	if (errors.getElementsByTagName('post_error').length == 1)
		document.forms.postmodify[this.opts.sPostBoxContainerID].style.border = '1px solid red';
	else if (document.forms.postmodify[this.opts.sPostBoxContainerID].style.borderColor == 'red' || document.forms.postmodify[this.opts.sPostBoxContainerID].style.borderColor == 'red red red red')
	{
		if ('runtimeStyle' in document.forms.postmodify[this.opts.sPostBoxContainerID])
			document.forms.postmodify[this.opts.sPostBoxContainerID].style.borderColor = '';
		else
			document.forms.postmodify[this.opts.sPostBoxContainerID].style.border = null;
	}

	// Set the new last message id.
	if ('last_msg' in document.forms.postmodify)
		document.forms.postmodify.last_msg.value = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('last_msg')[0].firstChild.nodeValue;

	var ignored_replies = new Array(), ignoring;
	var newPosts = XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('new_posts')[0] ? XMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('new_posts')[0].getElementsByTagName('post') : {length: 0};
	var numNewPosts = newPosts.length;
	if (numNewPosts != 0)
	{
		var newPostsHTML = '<span id="new_replies"><' + '/span>';
		var tempHTML;
		var new_replies = new Array();
		for (var i = 0; i < numNewPosts; i++)
		{
			new_replies[i] = newPosts[i].getAttribute("id");

			ignoring = false;
			if (newPosts[i].getElementsByTagName("is_ignored")[0].firstChild.nodeValue != 0)
				ignored_replies[ignored_replies.length] = ignoring = newPosts[i].getAttribute("id");

			tempHTML = this.opts.newPostsTemplate.replaceAll('%PostID%', newPosts[i].getAttribute("id")).replaceAll('%PosterName%', newPosts[i].getElementsByTagName("poster")[0].firstChild.nodeValue).replaceAll('%PostTime%', newPosts[i].getElementsByTagName("time")[0].firstChild.nodeValue).replaceAll('%PostBody%', newPosts[i].getElementsByTagName("message")[0].firstChild.nodeValue).replaceAll('%IgnoredStyle%', ignoring ?  'display: none' : '');

			newPostsHTML += tempHTML;
		}

		// Remove the new image from old-new replies!
		for (i = 0; i < new_replies.length; i++)
			document.getElementById(this.opts.sNewImageContainerID.replace('%ID%', new_replies[i])).style.display = 'none';

		setOuterHTML(document.getElementById('new_replies'), newPostsHTML);
	}

	var numIgnoredReplies = ignored_replies.length;
	if (numIgnoredReplies != 0)
	{
		for (var i = 0; i < numIgnoredReplies; i++)
		{
			aIgnoreToggles[ignored_replies[i]] = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: true,
				aSwappableContainers: [
					'msg_' + ignored_replies[i] + '_body',
					'msg_' + ignored_replies[i] + '_quote',
				],
				aSwapLinks: [
					{
						sId: 'msg_' + ignored_replies[i] + '_ignored_link',
						msgExpanded: '',
						msgCollapsed: this.opts.sTxtIgnoreUserPost
					}
				]
			});
		}
	}

	location.hash = '#' + this.opts.sPreviewSectionContainerID;
}
