/*!
 * jQuery Cookie Plugin v1.4.1
 * https://github.com/carhartl/jquery-cookie
 *
 * Copyright 2006, 2014 Klaus Hartl
 * Released under the MIT license
 */
(function (factory) {
	if (typeof define === 'function' && define.amd) {
		// AMD (Register as an anonymous module)
		define(['jquery'], factory);
	} else if (typeof exports === 'object') {
		// Node/CommonJS
		module.exports = factory(require('jquery'));
	} else {
		// Browser globals
		factory(jQuery);
	}
}(function ($) {

	var pluses = /\+/g;

	function encode(s) {
		return config.raw ? s : encodeURIComponent(s);
	}

	function decode(s) {
		return config.raw ? s : decodeURIComponent(s);
	}

	function stringifyCookieValue(value) {
		return encode(config.json ? JSON.stringify(value) : String(value));
	}

	function parseCookieValue(s) {
		if (s.indexOf('"') === 0) {
			// This is a quoted cookie as according to RFC2068, unescape...
			s = s.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
		}

		try {
			// Replace server-side written pluses with spaces.
			// If we can't decode the cookie, ignore it, it's unusable.
			// If we can't parse the cookie, ignore it, it's unusable.
			s = decodeURIComponent(s.replace(pluses, ' '));
			return config.json ? JSON.parse(s) : s;
		} catch(e) {}
	}

	function read(s, converter) {
		var value = config.raw ? s : parseCookieValue(s);
		return $.isFunction(converter) ? converter(value) : value;
	}

	var config = $.cookie = function (key, value, options) {

		// Write

		if (arguments.length > 1 && !$.isFunction(value)) {
			options = $.extend({}, config.defaults, options);

			if (typeof options.expires === 'number') {
				var days = options.expires, t = options.expires = new Date();
				t.setMilliseconds(t.getMilliseconds() + days * 864e+5);
			}

			return (document.cookie = [
				encode(key), '=', stringifyCookieValue(value),
				options.expires ? '; expires=' + options.expires.toUTCString() : '', // use expires attribute, max-age is not supported by IE
				options.path    ? '; path=' + options.path : '',
				options.domain  ? '; domain=' + options.domain : '',
				options.secure  ? '; secure' : ''
			].join(''));
		}

		// Read

		var result = key ? undefined : {},
			// To prevent the for loop in the first place assign an empty array
			// in case there are no cookies at all. Also prevents odd result when
			// calling $.cookie().
			cookies = document.cookie ? document.cookie.split('; ') : [],
			i = 0,
			l = cookies.length;

		for (; i < l; i++) {
			var parts = cookies[i].split('='),
				name = decode(parts.shift()),
				cookie = parts.join('=');

			if (key === name) {
				// If second argument (value) is a function it's a converter...
				result = read(cookie, value);
				break;
			}

			// Prevent storing a cookie that we couldn't decode.
			if (!key && (cookie = read(cookie)) !== undefined) {
				result[name] = cookie;
			}
		}

		return result;
	};

	config.defaults = {};

	$.removeCookie = function (key, options) {
		// Must not alter options, thus extending a fresh object...
		$.cookie(key, '', $.extend({}, options, { expires: -1 }));
		return !$.cookie(key);
	};

}));

var itemsOnPage = 8;
var statusButton = 1;
var my_timer_id = 0;
var renew_cookies = $.cookie('my_recent_renew');
var my_timer_period = 10; //секунды

$(function() {
	
	sizeRecentTopics();

	if(renew_cookies == 'true'){
		startCountdown();
		buildRecentTopics();
		$("#renew").attr("checked","checked");
		setMyTimer('on');
	} else {
		buildRecentTopics();
	}
	
	
	$('#renew_button').on('click', function(){
		buildRecentTopics();
	});

	
	$('#renew').on('click', function(){
		var val = $('#renew').prop("checked");
		clearInterval('id_nums_timer');
		$.cookie('my_recent_renew', val);
		if(val === true){
			setMyTimer('on');
			startCountdown();
			
		} else {
			setMyTimer('off');
		}
	});
	
	$('body').on('click', '.recent_button', function(){
		statusButton = $(this).val();
		buildRecentTopics();
	});
	
	$(window).on('resize', function(){
		sizeRecentTopics();
	});
		
});


function sizeRecentTopics(){
    return;
	var width = (($('#my_recent').innerWidth()) / 2);
	width = Math.floor(width);

	$('#left_recent_col').css({"width": width + "px"});
	$('#right_recent_col').css({"width": width + "px"});
	
}


function buildRecentTopics(){
	$.getJSON( "/my_recent_topics2/ajax.php", function( data ) {
		
		if(data.length == 0) return;
		
		var numsButton = data.length / itemsOnPage;
		numsButton = Math.ceil(numsButton);
		
		
		if(numsButton > 1){
			buildButton(numsButton);
		}
		
		buildListTopics(data);
		
	});
 
}

function buildButton(num){
	var btn = '';

	for (i = 1; i <= num; i++){
		btn += '<button id="btn_'+i+'" type="button" class="recent_button" value="' + i + '">' + ( ( i * itemsOnPage ) - itemsOnPage + 1 ) + '-' + ( i * itemsOnPage ) + '</button>';
	}

	
	$('#my_btn_recent').html(btn);
	$('#btn_' + statusButton).css({"background": "#f8f8f8"});
	
}

function buildListTopics(data){
	
		$('#left_recent_col').html('');
		$('#right_recent_col').html('');
		
		var itemStart = (statusButton * itemsOnPage) - itemsOnPage; //0
		var itemHalf = itemStart + (itemsOnPage / 2) - 1; //5
		var itemEnd = (statusButton * itemsOnPage) - 1; //11
		var leftCol = '';
		var rightCol = '';
		
		$.each( data, function( key, val ) {
			if( key >= itemStart && key <= itemHalf ){
				leftCol += val;				
			}
			
			if( key > itemHalf && key <= itemEnd ){
				rightCol += val;
			}
			
		});
		
		$('#left_recent_col').html(leftCol);
		$('#right_recent_col').html(rightCol);
	
}

function setMyTimer(stat){
	if(stat == 'on'){
	
		my_timer_id = setInterval(function(){
			buildRecentTopics();
		} , 1000 * my_timer_period);
	} else {
		startFrom = my_timer_period;
		clearInterval(id_nums_timer);
		$('#renew_button').val('Обновить');
		
		clearInterval(my_timer_id);
		$('#renew_button').val('Обновить');
	}
}


startFrom = 0;
id_nums_timer = 0;
function startCountdown(){	
	startFrom = my_timer_period;
	id_nums_timer = setInterval(function(){
		$('#renew_button').val('Обновить (' + startFrom + ')');
		startFrom--;
		if(startFrom == -1){
			startFrom = my_timer_period;
		}
		
	}, 1000);	
}
