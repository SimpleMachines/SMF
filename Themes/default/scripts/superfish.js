
/*
 * Superfish v1.4.8 - jQuery menu widget
 * Copyright (c) 2008 Joel Birch
 *
 * Dual licensed under the MIT and GPL licenses:
 * 	http://www.opensource.org/licenses/mit-license.php
 * 	http://www.gnu.org/licenses/gpl.html
 *
 * CHANGELOG: http://users.tpg.com.au/j_birch/plugins/superfish/changelog.txt
 */
 
 /*
 * PLEASE READ THE FOLLOWING BEFORE PLAYING AROUND WITH ANYTHING. KTHNX.
 * Dev copy. Antechinus - 20th October 2011.
 * Code has been tweaked to remove stuff we do not need (IE7 fix, etc).
 * Remaining code appears to be essential for full functionality.
 * If contemplating changes, testing for full functionality is essential or a11y will be degraded.
 * Since a11y is the whole point of this system, degradation is not at all desirable regardless of personal preferences.
 * If you do not understand the a11y advantages of this system, please ask before making changes.
 *
 * Full functionality means:
 * 1/ hoverIntent plugin functions so that drop menus do NOT open or close instantly when cursor touches first level anchor.
 * 2/ The drop menus should only open when the cursor actually stops on the first level anchor, or is moving very slowly.
 * 3/ There should be a delay before the drop menus close on mouseout, for people with less than perfect tracking ability.
 * 4/ The drop menus must remain fully accessible via keyboard navigation (eg: the Tab key).
 */

;(function($){
	$.fn.superfish = function(op){

		var sf = $.fn.superfish,
			c = sf.c,
			over = function(){
				var $$ = $(this), menu = getMenu($$);
				clearTimeout(menu.sfTimer);
				$$.showSuperfishUl().siblings().hideSuperfishUl();
			},
			out = function(){
				var $$ = $(this), menu = getMenu($$), o = sf.op;
				clearTimeout(menu.sfTimer);
				menu.sfTimer=setTimeout(function(){
					o.retainPath=($.inArray($$[0],o.$path)>-1);
					$$.hideSuperfishUl();
					if (o.$path.length && $$.parents(['li.',o.hoverClass].join('')).length<1){over.call(o.$path);}
				},o.delay);	
			},
			getMenu = function($menu){
				var menu = $menu.parents(['ul.',c.menuClass,':first'].join(''))[0];
				sf.op = sf.o[menu.serial];
				return menu;
			},
			// This next line is essential, despite the other code for arrows being removed.
			// Changing the next line WILL break hoverIntent functionality. Very bad.
			addArrow = function($a){$a.addClass(c.anchorClass)};

		return this.each(function() {
			var s = this.serial = sf.o.length;
			var o = $.extend({},sf.defaults,op);
			o.$path = $('li.'+o.pathClass,this).slice(0,o.pathLevels).each(function(){
				$(this).addClass([o.hoverClass,c.bcClass].join(' '))
					.filter('li:has(ul)').removeClass(o.pathClass);
			});
			sf.o[s] = sf.op = o;
			$('li:has(ul)',this)[($.fn.hoverIntent && !o.disableHI) ? 'hoverIntent' : 'hover'](over,out).each(function() {})
			.not('.'+c.bcClass)
				.hideSuperfishUl();

			var $a = $('a',this);
			$a.each(function(i){
				var $li = $a.eq(i).parents('li');
				$a.eq(i).focus(function(){over.call($li);}).blur(function(){out.call($li);});
			});
			o.onInit.call(this);
			
		}).each(function() {
			var menuClasses = [c.menuClass];
			$(this).addClass(menuClasses.join(' '));
		});
	};

	var sf = $.fn.superfish;
	sf.o = [];
	sf.op = {};
	sf.c = {
		bcClass     : 'sf-breadcrumb',
		menuClass   : 'sf-js-enabled',
		anchorClass : 'sf-with-ul',
	};
	sf.defaults = {
		hoverClass	: 'sfhover',
		pathClass	: 'current',
		pathLevels	: 1,
		delay		: 700,
		animation	: {opacity:'show', height:'show'},
		speed		: 300,
		disableHI	: false,		// Leave as false. True disables hoverIntent detection (not good).
		onInit		: function(){}, // callback functions
		onBeforeShow: function(){},
		onShow		: function(){},
		onHide		: function(){}
	};
	$.fn.extend({
		hideSuperfishUl : function(){
			var o = sf.op,
				not = (o.retainPath===true) ? o.$path : '';
			o.retainPath = false;
			var $ul = $(['li.',o.hoverClass].join(''),this).add(this).not(not).removeClass(o.hoverClass)
					.find('>ul').hide().css('opacity','0');
			o.onHide.call($ul);
			return this;
		},
		showSuperfishUl : function(){
			var o = sf.op,
				sh = sf.c,
				$ul = this.addClass(o.hoverClass)
					.find('>ul:hidden').css('opacity','1');
			o.onBeforeShow.call($ul);
			$ul.animate(o.animation,o.speed,function(){o.onShow.call($ul);});
			return this;
		}
	});

})(jQuery);
