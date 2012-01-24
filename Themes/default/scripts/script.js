var smf_formSubmitted = false;
var lastKeepAliveCheck = new Date().getTime();
var smf_editorArray = new Array();

// Some very basic browser detection - from Mozilla's sniffer page.
var ua = navigator.userAgent.toLowerCase();

var is_opera = ua.indexOf('opera') != -1;
var is_opera5 = ua.indexOf('opera/5') != -1 || ua.indexOf('opera 5') != -1;
var is_opera6 = ua.indexOf('opera/6') != -1 || ua.indexOf('opera 6') != -1;
var is_opera7 = ua.indexOf('opera/7') != -1 || ua.indexOf('opera 7') != -1;
var is_opera8 = ua.indexOf('opera/8') != -1 || ua.indexOf('opera 8') != -1;
var is_opera9 = ua.indexOf('opera/9') != -1 || ua.indexOf('opera 9') != -1;
var is_opera95 = ua.indexOf('opera/9.5') != -1 || ua.indexOf('opera 9.5') != -1;
var is_opera96 = ua.indexOf('opera/9.6') != -1 || ua.indexOf('opera 9.6') != -1;
var is_opera10 = (ua.indexOf('opera/9.8') != -1 || ua.indexOf('opera 9.8') != -1 || ua.indexOf('opera/10.') != -1 || ua.indexOf('opera 10.') != -1) || ua.indexOf('version/10.') != -1;
var is_opera95up = is_opera95 || is_opera96 || is_opera10;

var is_ff = (ua.indexOf('firefox') != -1 || ua.indexOf('iceweasel') != -1 || ua.indexOf('icecat') != -1 || ua.indexOf('shiretoko') != -1 || ua.indexOf('minefield') != -1) && !is_opera;
var is_gecko = ua.indexOf('gecko') != -1 && !is_opera;

var is_chrome = ua.indexOf('chrome') != -1;
var is_safari = ua.indexOf('applewebkit') != -1 && !is_chrome;
var is_webkit = ua.indexOf('applewebkit') != -1;

var is_ie = ua.indexOf('msie') != -1 && !is_opera;
var is_ie4 = is_ie && ua.indexOf('msie 4') != -1;
var is_ie5 = is_ie && ua.indexOf('msie 5') != -1;
var is_ie50 = is_ie && ua.indexOf('msie 5.0') != -1;
var is_ie55 = is_ie && ua.indexOf('msie 5.5') != -1;
var is_ie5up = is_ie && !is_ie4;
var is_ie6 = is_ie && ua.indexOf('msie 6') != -1;
var is_ie6up = is_ie5up && !is_ie55 && !is_ie5;
var is_ie6down = is_ie6 || is_ie5 || is_ie4;
var is_ie7 = is_ie && ua.indexOf('msie 7') != -1;
var is_ie7up = is_ie6up && !is_ie6;
var is_ie7down = is_ie7 || is_ie6 || is_ie5 || is_ie4;

var is_ie8 = is_ie && ua.indexOf('msie 8') != -1;
var is_ie8up = is_ie8 && !is_ie7down;

var is_iphone = ua.indexOf('iphone') != -1 || ua.indexOf('ipod') != -1;
var is_android = ua.indexOf('android') != -1;

var ajax_indicator_ele = null;

// Define document.getElementById for Internet Explorer 4.
if (!('getElementById' in document) && 'all' in document)
	document.getElementById = function (sId) {
		return document.all[sId];
	}

// Define XMLHttpRequest for IE 5 and above. (don't bother for IE 4 :/.... works in Opera 7.6 and Safari 1.2!)
else if (!('XMLHttpRequest' in window) && 'ActiveXObject' in window)
	window.XMLHttpRequest = function () {
		return new ActiveXObject(is_ie5 ? 'Microsoft.XMLHTTP' : 'MSXML2.XMLHTTP');
	};

// Ensure the getElementsByTagName exists.
if (!'getElementsByTagName' in document && 'all' in document)
	document.getElementsByTagName = function (sName) {
		return document.all.tags[sName];
	}

// Some older versions of Mozilla don't have this, for some reason.
if (!('forms' in document))
	document.forms = document.getElementsByTagName('form');

// Load an XML document using XMLHttpRequest.
function getXMLDocument(sUrl, funcCallback)
{
	if (!window.XMLHttpRequest)
		return null;

	var oMyDoc = new XMLHttpRequest();
	var bAsync = typeof(funcCallback) != 'undefined';
	var oCaller = this;
	if (bAsync)
	{
		oMyDoc.onreadystatechange = function () {
			if (oMyDoc.readyState != 4)
				return;

			if (oMyDoc.responseXML != null && oMyDoc.status == 200)
			{
				if (funcCallback.call)
				{
					funcCallback.call(oCaller, oMyDoc.responseXML);
				}
				// A primitive substitute for the call method to support IE 5.0.
				else
				{
					oCaller.tmpMethod = funcCallback;
					oCaller.tmpMethod(oMyDoc.responseXML);
					delete oCaller.tmpMethod;
				}
			}
		};
	}
	oMyDoc.open('GET', sUrl, bAsync);
	oMyDoc.send(null);

	return oMyDoc;
}

// Send a post form to the server using XMLHttpRequest.
function sendXMLDocument(sUrl, sContent, funcCallback)
{
	if (!window.XMLHttpRequest)
		return false;

	var oSendDoc = new window.XMLHttpRequest();
	var oCaller = this;
	if (typeof(funcCallback) != 'undefined')
	{
		oSendDoc.onreadystatechange = function () {
			if (oSendDoc.readyState != 4)
				return;

			if (oSendDoc.responseXML != null && oSendDoc.status == 200)
				funcCallback.call(oCaller, oSendDoc.responseXML);
			else
				funcCallback.call(oCaller, false);
		};
	}
	oSendDoc.open('POST', sUrl, true);
	if ('setRequestHeader' in oSendDoc)
		oSendDoc.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	oSendDoc.send(sContent);

	return true;
}

// A property we'll be needing for php_to8bit.
String.prototype.oCharsetConversion = {
	from: '',
	to: ''
};

// Convert a string to an 8 bit representation (like in PHP).
String.prototype.php_to8bit = function ()
{
	if (smf_charset == 'UTF-8')
	{
		var n, sReturn = '';

		for (var i = 0, iTextLen = this.length; i < iTextLen; i++)
		{
			n = this.charCodeAt(i);
			if (n < 128)
				sReturn += String.fromCharCode(n)
			else if (n < 2048)
				sReturn += String.fromCharCode(192 | n >> 6) + String.fromCharCode(128 | n & 63);
			else if (n < 65536)
				sReturn += String.fromCharCode(224 | n >> 12) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
			else
				sReturn += String.fromCharCode(240 | n >> 18) + String.fromCharCode(128 | n >> 12 & 63) + String.fromCharCode(128 | n >> 6 & 63) + String.fromCharCode(128 | n & 63);
		}

		return sReturn;
	}

	else if (this.oCharsetConversion.from.length == 0)
	{
		switch (smf_charset)
		{
			case 'ISO-8859-1':
				this.oCharsetConversion = {
					from: '\xa0-\xff',
					to: '\xa0-\xff'
				};
			break;

			case 'ISO-8859-2':
				this.oCharsetConversion = {
					from: '\xa0\u0104\u02d8\u0141\xa4\u013d\u015a\xa7\xa8\u0160\u015e\u0164\u0179\xad\u017d\u017b\xb0\u0105\u02db\u0142\xb4\u013e\u015b\u02c7\xb8\u0161\u015f\u0165\u017a\u02dd\u017e\u017c\u0154\xc1\xc2\u0102\xc4\u0139\u0106\xc7\u010c\xc9\u0118\xcb\u011a\xcd\xce\u010e\u0110\u0143\u0147\xd3\xd4\u0150\xd6\xd7\u0158\u016e\xda\u0170\xdc\xdd\u0162\xdf\u0155\xe1\xe2\u0103\xe4\u013a\u0107\xe7\u010d\xe9\u0119\xeb\u011b\xed\xee\u010f\u0111\u0144\u0148\xf3\xf4\u0151\xf6\xf7\u0159\u016f\xfa\u0171\xfc\xfd\u0163\u02d9',
					to: '\xa0-\xff'
				};
			break;

			case 'ISO-8859-5':
				this.oCharsetConversion = {
					from: '\xa0\u0401-\u040c\xad\u040e-\u044f\u2116\u0451-\u045c\xa7\u045e\u045f',
					to: '\xa0-\xff'
				};
			break;

			case 'ISO-8859-9':
				this.oCharsetConversion = {
					from: '\xa0-\xcf\u011e\xd1-\xdc\u0130\u015e\xdf-\xef\u011f\xf1-\xfc\u0131\u015f\xff',
					to: '\xa0-\xff'
				};
			break;

			case 'ISO-8859-15':
				this.oCharsetConversion = {
					from: '\xa0-\xa3\u20ac\xa5\u0160\xa7\u0161\xa9-\xb3\u017d\xb5-\xb7\u017e\xb9-\xbb\u0152\u0153\u0178\xbf-\xff',
					to: '\xa0-\xff'
				};
			break;

			case 'tis-620':
				this.oCharsetConversion = {
					from: '\u20ac\u2026\u2018\u2019\u201c\u201d\u2022\u2013\u2014\xa0\u0e01-\u0e3a\u0e3f-\u0e5b',
					to: '\x80\x85\x91-\x97\xa0-\xda\xdf-\xfb'
				};
			break;

			case 'windows-1251':
				this.oCharsetConversion = {
					from: '\u0402\u0403\u201a\u0453\u201e\u2026\u2020\u2021\u20ac\u2030\u0409\u2039\u040a\u040c\u040b\u040f\u0452\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u2122\u0459\u203a\u045a\u045c\u045b\u045f\xa0\u040e\u045e\u0408\xa4\u0490\xa6\xa7\u0401\xa9\u0404\xab-\xae\u0407\xb0\xb1\u0406\u0456\u0491\xb5-\xb7\u0451\u2116\u0454\xbb\u0458\u0405\u0455\u0457\u0410-\u044f',
					to: '\x80-\x97\x99-\xff'
				};
			break;

			case 'windows-1253':
				this.oCharsetConversion = {
					from: '\u20ac\u201a\u0192\u201e\u2026\u2020\u2021\u2030\u2039\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u2122\u203a\xa0\u0385\u0386\xa3-\xa9\xab-\xae\u2015\xb0-\xb3\u0384\xb5-\xb7\u0388-\u038a\xbb\u038c\xbd\u038e-\u03a1\u03a3-\u03ce',
					to: '\x80\x82-\x87\x89\x8b\x91-\x97\x99\x9b\xa0-\xa9\xab-\xd1\xd3-\xfe'
				};
			break;

			case 'windows-1255':
				this.oCharsetConversion = {
					from: '\u20ac\u201a\u0192\u201e\u2026\u2020\u2021\u02c6\u2030\u2039\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u02dc\u2122\u203a\xa0-\xa3\u20aa\xa5-\xa9\xd7\xab-\xb9\xf7\xbb-\xbf\u05b0-\u05b9\u05bb-\u05c3\u05f0-\u05f4\u05d0-\u05ea\u200e\u200f',
					to: '\x80\x82-\x89\x8b\x91-\x99\x9b\xa0-\xc9\xcb-\xd8\xe0-\xfa\xfd\xfe'
				};
			break;

			case 'windows-1256':
				this.oCharsetConversion = {
					from: '\u20ac\u067e\u201a\u0192\u201e\u2026\u2020\u2021\u02c6\u2030\u0679\u2039\u0152\u0686\u0698\u0688\u06af\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u06a9\u2122\u0691\u203a\u0153\u200c\u200d\u06ba\xa0\u060c\xa2-\xa9\u06be\xab-\xb9\u061b\xbb-\xbe\u061f\u06c1\u0621-\u0636\xd7\u0637-\u063a\u0640-\u0643\xe0\u0644\xe2\u0645-\u0648\xe7-\xeb\u0649\u064a\xee\xef\u064b-\u064e\xf4\u064f\u0650\xf7\u0651\xf9\u0652\xfb\xfc\u200e\u200f\u06d2',
					to: '\x80-\xff'
				};
			break;

			default:
				this.oCharsetConversion = {
					from: '',
					to: ''
				};
			break;
		}
		var funcExpandString = function (sSearch) {
			var sInsert = '';
			for (var i = sSearch.charCodeAt(0), n = sSearch.charCodeAt(2); i <= n; i++)
				sInsert += String.fromCharCode(i);
			return sInsert;
		};
		this.oCharsetConversion.from = this.oCharsetConversion.from.replace(/.\-./g, funcExpandString);
		this.oCharsetConversion.to = this.oCharsetConversion.to.replace(/.\-./g, funcExpandString);
	}

	var sReturn = '', iOffsetFrom = 0;
	for (var i = 0, n = this.length; i < n; i++)
	{
		iOffsetFrom = this.oCharsetConversion.from.indexOf(this.charAt(i));
		sReturn += iOffsetFrom > -1 ? this.oCharsetConversion.to.charAt(iOffsetFrom) : (this.charCodeAt(i) > 127 ? '&#' + this.charCodeAt(i) + ';' : this.charAt(i));
	}

	return sReturn
}

// Character-level replacement function.
String.prototype.php_strtr = function (sFrom, sTo)
{
	return this.replace(new RegExp('[' + sFrom + ']', 'g'), function (sMatch) {
		return sTo.charAt(sFrom.indexOf(sMatch));
	});
}

// Simulate PHP's strtolower (in SOME cases PHP uses ISO-8859-1 case folding).
String.prototype.php_strtolower = function ()
{
	return typeof(smf_iso_case_folding) == 'boolean' && smf_iso_case_folding == true ? this.php_strtr(
		'ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde',
		'abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9e\xff\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe'
	) : this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
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

String.prototype.php_addslashes = function()
{
	return this.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
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


// Open a new window.
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
	var theValue = theField.value;

	// Strip whitespace off the left side.
	while (theValue.length > 0 && (theValue.charAt(0) == ' ' || theValue.charAt(0) == '\t'))
		theValue = theValue.substring(1, theValue.length);
	// Strip whitespace off the right side.
	while (theValue.length > 0 && (theValue.charAt(theValue.length - 1) == ' ' || theValue.charAt(theValue.length - 1) == '\t'))
		theValue = theValue.substring(0, theValue.length - 1);

	if (theValue == '')
		return true;
	else
		return false;
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
	// Hateful, hateful fix for Safari 1.3 beta.
	if (is_safari)
		return !smf_formSubmitted;

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

	window.setTimeout('smf_sessionKeepAlive();', 1200000);
}
window.setTimeout('smf_sessionKeepAlive();', 1200000);

// Set a theme option through javascript.
function smf_setThemeOption(option, value, theme, cur_session_id, cur_session_var, additional_vars)
{
	// Compatibility.
	if (cur_session_id == null)
		cur_session_id = smf_session_id;
	if (typeof(cur_session_var) == 'undefined')
		cur_session_var = 'sesc';

	if (additional_vars == null)
		additional_vars = '';

	var tempImage = new Image();
	tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=jsoption;var=' + option + ';val=' + value + ';' + cur_session_var + '=' + cur_session_id + additional_vars + (theme == null ? '' : '&th=' + theme) + ';time=' + (new Date().getTime());
}

function smf_avatarResize()
{
	var possibleAvatars = document.getElementsByTagName('img');

	for (var i = 0; i < possibleAvatars.length; i++)
	{
		var tempAvatars = []; j = 0;
		if (possibleAvatars[i].className != 'avatar')
			continue;

		// Image.prototype.avatar = possibleAvatars[i];
		tempAvatars[j] = new Image();
		tempAvatars[j].avatar = possibleAvatars[i];
		
		tempAvatars[j].onload = function()
		{
			this.avatar.width = this.width;
			this.avatar.height = this.height;
			if (smf_avatarMaxWidth != 0 && this.width > smf_avatarMaxWidth)
			{
				this.avatar.height = (smf_avatarMaxWidth * this.height) / this.width;
				this.avatar.width = smf_avatarMaxWidth;
			}
			if (smf_avatarMaxHeight != 0 && this.avatar.height > smf_avatarMaxHeight)
			{
				this.avatar.width = (smf_avatarMaxHeight * this.avatar.width) / this.avatar.height;
				this.avatar.height = smf_avatarMaxHeight;
			}
		}
		tempAvatars[j].src = possibleAvatars[i].src;
		j++;
	}

	if (typeof(window_oldAvatarOnload) != 'undefined' && window_oldAvatarOnload)
	{
		window_oldAvatarOnload();
		window_oldAvatarOnload = null;
	}
}


function hashLoginPassword(doForm, cur_session_id)
{
	// Compatibility.
	if (cur_session_id == null)
		cur_session_id = smf_session_id;

	if (typeof(hex_sha1) == 'undefined')
		return;
	// Are they using an email address?
	if (doForm.user.value.indexOf('@') != -1)
		return;

	// Unless the browser is Opera, the password will not save properly.
	if (!('opera' in window))
		doForm.passwrd.autocomplete = 'off';

	doForm.hash_passwrd.value = hex_sha1(hex_sha1(doForm.user.value.php_to8bit().php_strtolower() + doForm.passwrd.value.php_to8bit()) + cur_session_id);

	// It looks nicer to fill it with asterisks, but Firefox will try to save that.
	if (is_ff != -1)
		doForm.passwrd.value = '';
	else
		doForm.passwrd.value = doForm.passwrd.value.replace(/./g, '*');
}

function hashAdminPassword(doForm, username, cur_session_id)
{
	// Compatibility.
	if (cur_session_id == null)
		cur_session_id = smf_session_id;

	if (typeof(hex_sha1) == 'undefined')
		return;

	doForm.admin_hash_pass.value = hex_sha1(hex_sha1(username.php_to8bit().php_strtolower() + doForm.admin_pass.value.php_to8bit()) + cur_session_id);
	doForm.admin_pass.value = doForm.admin_pass.value.replace(/./g, '*');
}

// Shows the page numbers by clicking the dots (in compact view).
function expandPages(spanNode, baseURL, firstPage, lastPage, perPage)
{
	var replacement = '', i, oldLastPage = 0;
	var perPageLimit = 50;

	// The dots were bold, the page numbers are not (in most cases).
	spanNode.style.fontWeight = 'normal';
	spanNode.onclick = '';

	// Prevent too many pages to be loaded at once.
	if ((lastPage - firstPage) / perPage > perPageLimit)
	{
		oldLastPage = lastPage;
		lastPage = firstPage + perPageLimit * perPage;
	}

	// Calculate the new pages.
	for (i = firstPage; i < lastPage; i += perPage)
		replacement += '<a class="navPages" href="' + baseURL.replace(/%1\$d/, i).replace(/%%/g, '%') + '">' + (1 + i / perPage) + '</a> ';

	if (oldLastPage > 0)
		replacement += '<span style="font-weight: bold; cursor: ' + (is_ie && !is_ie6up ? 'hand' : 'pointer') + ';" onclick="expandPages(this, \'' + baseURL + '\', ' + lastPage + ', ' + oldLastPage + ', ' + perPage + ');"> ... </span> ';

	// Replace the dots by the new page links.
	setInnerHTML(spanNode, replacement);
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

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this.changeState(true, true);

	// Initialize the images to be clickable.
	if ('aSwapImages' in this.opt)
	{
		for (var i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) == 'object' && oImage != null)
			{
				// Display the image in case it was hidden.
				if (oImage.style.display == 'none')
					oImage.style.display = '';

				oImage.instanceRef = this;
				oImage.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
				}
				oImage.style.cursor = 'pointer';

				// Preload the collapsed image.
				smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
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
}

// Collapse or expand the section.
smc_Toggle.prototype.changeState = function(bCollapse, bInit)
{
	// Default bInit to false.
	bInit = typeof(bInit) == 'undefined' ? false : true;

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
			oContainer.style.display = bCollapse ? 'none' : '';
	}

	// Update the new state.
	this.bCollapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed ? '1' : '0');

	if ('oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		smf_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed ? '1' : '0', 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, this.opt.oThemeOptions.sSessionId, this.opt.oThemeOptions.sSessionVar, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
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
		if (navigator.appName == 'Microsoft Internet Explorer' && !is_ie7up)
		{
			ajax_indicator_ele.style.position = 'absolute';
			ajax_indicator_ele.style.top = document.documentElement.scrollTop;
		}

		ajax_indicator_ele.style.display = turn_on ? 'block' : 'none';
	}
}

function create_ajax_indicator_ele()
{
	// Create the div for the indicator.
	ajax_indicator_ele = document.createElement('div');

	// Set the id so it'll load the style properly.
	ajax_indicator_ele.id = 'ajax_in_progress';

	// Add the image in and link to turn it off.
	var cancel_link = document.createElement('a');
	cancel_link.href = 'javascript:ajax_indicator(false)';
	var cancel_img = document.createElement('img');
	cancel_img.src = smf_images_url + '/icons/quick_remove.gif';

	if (typeof(ajax_notification_cancel_text) != 'undefined')
	{
		cancel_img.alt = ajax_notification_cancel_text;
		cancel_img.title = ajax_notification_cancel_text;
	}

	// Add the cancel link and image to the indicator.
	cancel_link.appendChild(cancel_img);
	ajax_indicator_ele.appendChild(cancel_link);

	// Set the text.  (Note:  You MUST append here and not overwrite.)
	ajax_indicator_ele.innerHTML += ajax_notification_text;

	// Finally attach the element to the body.
	document.body.appendChild(ajax_indicator_ele);
}

function createEventListener(oTarget)
{
	if (!('addEventListener' in oTarget))
	{
		if (oTarget.attachEvent)
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.attachEvent('on' + sEvent, funcHandler);
			}
			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget.detachEvent('on' + sEvent, funcHandler);
			}
		}
		else
		{
			oTarget.addEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = funcHandler;
			}
			oTarget.removeEventListener = function (sEvent, funcHandler, bCapture) {
				oTarget['on' + sEvent] = null;
			}
		}
	}
}

// This function will retrieve the contents needed for the jump to boxes.
function grabJumpToContent()
{
	var oXMLDoc = getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=jumpto;xml');
	var aBoardsAndCategories = new Array();

	ajax_indicator(true);

	if (oXMLDoc.responseXML)
	{
		var items = oXMLDoc.responseXML.getElementsByTagName('smf')[0].getElementsByTagName('item');
		for (var i = 0, n = items.length; i < n; i++)
		{
			aBoardsAndCategories[aBoardsAndCategories.length] = {
				id: parseInt(items[i].getAttribute('id')),
				isCategory: items[i].getAttribute('type') == 'category',
				name: items[i].firstChild.nodeValue.removeEntities(),
				is_current: false,
				childLevel: parseInt(items[i].getAttribute('childlevel'))
			}
		}
	}

	ajax_indicator(false);

	for (var i = 0, n = aJumpTo.length; i < n; i++)
		aJumpTo[i].fillSelect(aBoardsAndCategories);
}

// This'll contain all JumpTo objects on the page.
var aJumpTo = new Array();

// *** JumpTo class.
function JumpTo(oJumpToOptions)
{
	this.opt = oJumpToOptions;
	this.dropdownList = null;
	this.showSelect();
}

// Show the initial select box (onload). Method of the JumpTo class.
JumpTo.prototype.showSelect = function ()
{
	var sChildLevelPrefix = '';
	for (var i = this.opt.iCurBoardChildLevel; i > 0; i--)
		sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;
	setInnerHTML(document.getElementById(this.opt.sContainerId), this.opt.sJumpToTemplate.replace(/%select_id%/, this.opt.sContainerId + '_select').replace(/%dropdown_list%/, '<select name="' + this.opt.sContainerId + '_select" id="' + this.opt.sContainerId + '_select" ' + ('implementation' in document ? '' : 'onmouseover="grabJumpToContent();" ') + ('onbeforeactivate' in document ? 'onbeforeactivate' : 'onfocus') + '="grabJumpToContent();"><option value="?board=' + this.opt.iCurBoardId + '.0">' + sChildLevelPrefix + this.opt.sBoardPrefix + this.opt.sCurBoardName.removeEntities() + '</option></select>&nbsp;<input type="button" value="' + this.opt.sGoButtonLabel + '" onclick="window.location.href = \'' + smf_prepareScriptUrl(smf_scripturl) + 'board=' + this.opt.iCurBoardId + '.0\';" />'));
	this.dropdownList = document.getElementById(this.opt.sContainerId + '_select');
}

// Fill the jump to box with entries. Method of the JumpTo class.
JumpTo.prototype.fillSelect = function (aBoardsAndCategories)
{
	var bIE5x = !('implementation' in document);
	var iIndexPointer = 0;

	// Create an option that'll be above and below the category.
	var oDashOption = document.createElement('option');
	oDashOption.appendChild(document.createTextNode(this.opt.sCatSeparator));
	oDashOption.disabled = 'disabled';
	oDashOption.value = '';

	// Reset the events and clear the list (IE5.x only).
	if (bIE5x)
	{
		this.dropdownList.onmouseover = null;
		this.dropdownList.remove(0);
	}
	if ('onbeforeactivate' in document)
		this.dropdownList.onbeforeactivate = null;
	else
		this.dropdownList.onfocus = null;

	// Create a document fragment that'll allowing inserting big parts at once.
	var oListFragment = bIE5x ? this.dropdownList : document.createDocumentFragment();

	// Loop through all items to be added.
	for (var i = 0, n = aBoardsAndCategories.length; i < n; i++)
	{
		var j, sChildLevelPrefix, oOption;

		// If we've reached the currently selected board add all items so far.
		if (!aBoardsAndCategories[i].isCategory && aBoardsAndCategories[i].id == this.opt.iCurBoardId)
		{
			if (bIE5x)
				iIndexPointer = this.dropdownList.options.length;
			else
			{
				this.dropdownList.insertBefore(oListFragment, this.dropdownList.options[0]);
				oListFragment = document.createDocumentFragment();
				continue;
			}
		}

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
		else
			for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = ''; j > 0; j--)
				sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

		oOption = document.createElement('option');
		oOption.appendChild(document.createTextNode((aBoardsAndCategories[i].isCategory ? this.opt.sCatPrefix : sChildLevelPrefix + this.opt.sBoardPrefix) + aBoardsAndCategories[i].name));
		oOption.value = aBoardsAndCategories[i].isCategory ? '#c' + aBoardsAndCategories[i].id : '?board=' + aBoardsAndCategories[i].id + '.0';
		oListFragment.appendChild(oOption);

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
	}

	// Add the remaining items after the currently selected item.
	this.dropdownList.appendChild(oListFragment);

	if (bIE5x)
		this.dropdownList.options[iIndexPointer].selected = true;

	// Internet Explorer needs this to keep the box dropped down.
	this.dropdownList.style.width = 'auto';
	this.dropdownList.focus();

	// Add an onchange action
	this.dropdownList.onchange = function() {
		if (this.selectedIndex > 0 && this.options[this.selectedIndex].value)
			window.location.href = smf_scripturl + this.options[this.selectedIndex].value.substr(smf_scripturl.indexOf('?') == -1 || this.options[this.selectedIndex].value.substr(0, 1) != '?' ? 0 : 1);
	}
}

// A global array containing all IconList objects.
var aIconLists = new Array();

// *** IconList object.
function IconList(oOptions)
{
	if (!window.XMLHttpRequest)
		return;

	this.opt = oOptions;
	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.funcMousedownHandler = null;
	this.funcParent = this;
	this.iCurMessageId = 0;
	this.iCurTimeout = 0;

	// Add backwards compatibility with old themes.
	if (!('sSessionVar' in this.opt))
		this.opt.sSessionVar = 'sesc';

	this.initIcons();
}

// Replace all message icons by icons with hoverable and clickable div's.
IconList.prototype.initIcons = function ()
{
	for (var i = document.images.length - 1, iPrefixLength = this.opt.sIconIdPrefix.length; i >= 0; i--)
		if (document.images[i].id.substr(0, iPrefixLength) == this.opt.sIconIdPrefix)
			setOuterHTML(document.images[i], '<div title="' + this.opt.sLabelIconList + '" onclick="' + this.opt.sBackReference + '.openPopup(this, ' + document.images[i].id.substr(iPrefixLength) + ')" onmouseover="' + this.opt.sBackReference + '.onBoxHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onBoxHover(this, false)" style="background: ' + this.opt.sBoxBackground + '; cursor: ' + (is_ie && !is_ie6up ? 'hand' : 'pointer') + '; padding: 3px; text-align: center;"><img src="' + document.images[i].src + '" alt="' + document.images[i].alt + '" id="' + document.images[i].id + '" style="margin: 0px; padding: ' + (is_ie ? '3px' : '3px 0px 3px 0px') + ';" /></div>');
}

// Event for the mouse hovering over the original icon.
IconList.prototype.onBoxHover = function (oDiv, bMouseOver)
{
	oDiv.style.border = bMouseOver ? this.opt.iBoxBorderWidthHover + 'px solid ' + this.opt.sBoxBorderColorHover : '';
	oDiv.style.background = bMouseOver ? this.opt.sBoxBackgroundHover : this.opt.sBoxBackground;
	oDiv.style.padding = bMouseOver ? (3 - this.opt.iBoxBorderWidthHover) + 'px' : '3px'
}

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (oDiv, iMessageId)
{
	this.iCurMessageId = iMessageId;

	if (!this.bListLoaded && this.oContainerDiv == null)
	{
		// Create a container div.
		this.oContainerDiv = document.createElement('div');
		this.oContainerDiv.id = 'iconList';
		this.oContainerDiv.style.display = 'none';
		this.oContainerDiv.style.cursor = is_ie && !is_ie6up ? 'hand' : 'pointer';
		this.oContainerDiv.style.position = 'absolute';
		this.oContainerDiv.style.width = oDiv.offsetWidth + 'px';
		this.oContainerDiv.style.background = this.opt.sContainerBackground;
		this.oContainerDiv.style.border = this.opt.sContainerBorder;
		this.oContainerDiv.style.padding = '1px';
		this.oContainerDiv.style.textAlign = 'center';
		document.body.appendChild(this.oContainerDiv);

		// Start to fetch its contents.
		ajax_indicator(true);
		this.tmpMethod = getXMLDocument;
		this.tmpMethod(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=xmlhttp;sa=messageicons;board=' + this.opt.iBoardId + ';xml', this.onIconsReceived);
		delete this.tmpMethod;

		createEventListener(document.body);
	}

	// Set the position of the container.
	var aPos = smf_itemPos(oDiv);
	if (is_ie50)
		aPos[1] += 4;

	this.oContainerDiv.style.top = (aPos[1] + oDiv.offsetHeight) + 'px';
	this.oContainerDiv.style.left = (aPos[0] - 1) + 'px';
	this.oClickedIcon = oDiv;

	if (this.bListLoaded)
		this.oContainerDiv.style.display = 'block';

	document.body.addEventListener('mousedown', this.onWindowMouseDown, false);
}

// Setup the list of icons once it is received through xmlHTTP.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	var icons = oXMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('icon');
	var sItems = '';

	for (var i = 0, n = icons.length; i < n; i++)
		sItems += '<div onmouseover="' + this.opt.sBackReference + '.onItemHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onItemHover(this, false);" onmousedown="' + this.opt.sBackReference + '.onItemMouseDown(this, \'' + icons[i].getAttribute('value') + '\');" style="padding: 3px 0px 3px 0px; margin-left: auto; margin-right: auto; border: ' + this.opt.sItemBorder + '; background: ' + this.opt.sItemBackground + '"><img src="' + icons[i].getAttribute('url') + '" alt="' + icons[i].getAttribute('name') + '" title="' + icons[i].firstChild.nodeValue + '" /></div>';

	setInnerHTML(this.oContainerDiv, sItems);
	this.oContainerDiv.style.display = 'block';
	this.bListLoaded = true;

	if (is_ie)
		this.oContainerDiv.style.width = this.oContainerDiv.clientWidth + 'px';

	ajax_indicator(false);
}

// Event handler for hovering over the icons.
IconList.prototype.onItemHover = function (oDiv, bMouseOver)
{
	oDiv.style.background = bMouseOver ? this.opt.sItemBackgroundHover : this.opt.sItemBackground;
	oDiv.style.border = bMouseOver ? this.opt.sItemBorderHover : this.opt.sItemBorder;
	if (this.iCurTimeout != 0)
		window.clearTimeout(this.iCurTimeout);
	if (bMouseOver)
		this.onBoxHover(this.oClickedIcon, true);
	else
		this.iCurTimeout = window.setTimeout(this.opt.sBackReference + '.collapseList();', 500);
}

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function (oDiv, sNewIcon)
{
	if (this.iCurMessageId != 0)
	{
		ajax_indicator(true);
		this.tmpMethod = getXMLDocument;
		var oXMLDoc = this.tmpMethod(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';icon=' + sNewIcon + ';xml');
		delete this.tmpMethod;
		ajax_indicator(false);

		var oMessage = oXMLDoc.responseXML.getElementsByTagName('smf')[0].getElementsByTagName('message')[0];
		if (oMessage.getElementsByTagName('error').length == 0)
		{
			if (this.opt.bShowModify && oMessage.getElementsByTagName('modified').length != 0)
				setInnerHTML(document.getElementById('modified_' + this.iCurMessageId), oMessage.getElementsByTagName('modified')[0].childNodes[0].nodeValue);
			this.oClickedIcon.getElementsByTagName('img')[0].src = oDiv.getElementsByTagName('img')[0].src;
		}
	}
}

// Event handler for clicking outside the list (will make the list disappear).
IconList.prototype.onWindowMouseDown = function ()
{
	for (var i = aIconLists.length - 1; i >= 0; i--)
	{
		aIconLists[i].funcParent.tmpMethod = aIconLists[i].collapseList;
		aIconLists[i].funcParent.tmpMethod();
		delete aIconLists[i].funcParent.tmpMethod;
	}
}

// Collapse the list of icons.
IconList.prototype.collapseList = function()
{
	this.onBoxHover(this.oClickedIcon, false);
	this.oContainerDiv.style.display = 'none';
	this.iCurMessageId = 0;
	document.body.removeEventListener('mousedown', this.onWindowMouseDown, false);
}

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

function smfFooterHighlight(element, value)
{
	element.src = smf_images_url + '/' + (value ? 'h_' : '') + element.id + '.gif';
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
			var oLastChild = oCodeArea.lastChild;
			oCurSelection.setBaseAndExtent(oCodeArea, 0, oLastChild, 'innerText' in oLastChild ? oLastChild.innerText.length : oLastChild.textContent.length);
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

// A function needed to discern HTML entities from non-western characters.
function smc_saveEntities(sFormName, aElementNames, sMask)
{
	if (typeof(sMask) == 'string')
	{
		for (var i = 0, n = document.forms[sFormName].elements.length; i < n; i++)
			if (document.forms[sFormName].elements[i].id.substr(0, sMask.length) == sMask)
				aElementNames[aElementNames.length] = document.forms[sFormName].elements[i].name;
	}

	for (var i = 0, n = aElementNames.length; i < n; i++)
	{
		if (aElementNames[i] in document.forms[sFormName])
			document.forms[sFormName][aElementNames[i]].value = document.forms[sFormName][aElementNames[i]].value.replace(/&#/g, '&#38;#');
	}
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
