/* @name BeautyTips
 * @desc a tooltips/baloon-help plugin for jQuery
 * @author Jeff Robbins - Lullabot - http://www.lullabot.com
 * @version 0.9.5 release candidate 1  (5/20/2009) */
 
jQuery.bt = {version: '0.9.5-rc1'};
 
/* @type jQuery
 * @cat Plugins/bt
 * @requires jQuery v1.2+
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 * Encourage development. If you use BeautyTips for anything cool 
 * or on a site that people have heard of, please drop me a note.
 * - jeff ^at lullabot > com
 * No guarantees, warranties, or promises of any kind */

;(function($) { 
  /* @credit Inspired by Karl Swedberg's ClueTip
   * (http://plugins.learningjquery.com/cluetip/), which in turn was inspired
   * by Cody Lindley's jTip (http://www.codylindley.com)
   * Usage:
   * The function can be called in a number of ways.
   * $(selector).bt();
   * $(selector).bt('Content text');
   * $(selector).bt('Content text', {option1: value, option2: value});
   * $(selector).bt({option1: value, option2: value});
   * For more/better documentation and lots of examples, visit the demo page included with the distribution */ 

  jQuery.fn.bt = function(content, options) {
    if (typeof content != 'string') {
      var contentSelect = true;
      options = content;
      content = false;
    }
    else {
      var contentSelect = false;
    }
    if (jQuery.fn.hoverIntent && jQuery.bt.defaults.trigger == 'hover') {
      jQuery.bt.defaults.trigger = 'hoverIntent';
    }
    return this.each(function(index) {
      var opts = jQuery.extend(false, jQuery.bt.defaults, jQuery.bt.options, options);
      opts.overlap = -10;
      var ajaxTimeout = false;
      if (opts.killTitle) {
        $(this).find('[title]').andSelf().each(function() {
          if (!$(this).attr('bt-xTitle')) {
            $(this).attr('bt-xTitle', $(this).attr('title')).attr('title', '');
          }
        });
      }
      if (typeof opts.trigger == 'string') {
        opts.trigger = [opts.trigger];
      }
      if (opts.trigger[0] == 'hoverIntent') {
        var hoverOpts = jQuery.extend(opts.hoverIntentOpts, {
          over: function() {
            this.btOn();
          },
          out: function() {
            this.btOff();
          }});
        $(this).hoverIntent(hoverOpts);
      }
      else if (opts.trigger[0] == 'hover') {
        $(this).hover(
          function() {
            this.btOn();
          },
          function() {
            this.btOff();
          }
        );
      }
      else if (opts.trigger[0] == 'now') {
        if ($(this).hasClass('bt-active')) {
          this.btOff();
        }
        else {
          this.btOn();
        }
      }
      else if (opts.trigger[0] == 'none') {
      }
      else if (opts.trigger.length > 1 && opts.trigger[0] != opts.trigger[1]) {
        $(this)
          .bind(opts.trigger[0], function() {
            this.btOn();
          })
          .bind(opts.trigger[1], function() {
            this.btOff();
          });
      }
      else {
        $(this).bind(opts.trigger[0], function() {
          if ($(this).hasClass('bt-active')) {
            this.btOff();
          }
          else {
            this.btOn();
          }
        });
      }
      this.btOn = function () {
        if (typeof $(this).data('bt-box') == 'object') {
          this.btOff();
        }
        opts.preBuild.apply(this);
        $(jQuery.bt.vars.closeWhenOpenStack).btOff();
        $(this).addClass('bt-active ' + opts.activeClass);
        if (contentSelect && opts.ajaxPath == null) {
          if (opts.killTitle) {
            $(this).attr('title', $(this).attr('bt-xTitle'));
          }
          content = $.isFunction(opts.contentSelector) ? opts.contentSelector.apply(this) : eval(opts.contentSelector);
          if (opts.killTitle) {
            $(this).attr('title', '');
          }
        }
        if (opts.ajaxPath != null && content == false) {
          if (typeof opts.ajaxPath == 'object') {
            var url = eval(opts.ajaxPath[0]);
            url += opts.ajaxPath[1] ? ' ' + opts.ajaxPath[1] : '';
          }
          else {
            var url = opts.ajaxPath;
          }
          var off = url.indexOf(" ");
          if ( off >= 0 ) {
            var selector = url.slice(off, url.length);
            url = url.slice(0, off);
          }
          var cacheData = opts.ajaxCache ? $(document.body).data('btCache-' + url.replace(/\./g, '')) : null;
          if (typeof cacheData == 'string') {
            content = selector ? $("<div/>").append(cacheData.replace(/<script(.|\s)*?\/script>/g, "")).find(selector) : cacheData;
          }
          else {
            var target = this;
            var ajaxOpts = jQuery.extend(false,
            {
              type: opts.ajaxType,
              data: opts.ajaxData,
              cache: opts.ajaxCache,
              url: url,
              complete: function(XMLHttpRequest, textStatus) {
                if (textStatus == 'success' || textStatus == 'notmodified') {
                  if (opts.ajaxCache) {
                    $(document.body).data('btCache-' + url.replace(/\./g, ''), XMLHttpRequest.responseText);
                  }
                  ajaxTimeout = false;
                  content = selector ?
                    $("<div/>")
                      .append(XMLHttpRequest.responseText.replace(/<script(.|\s)*?\/script>/g, ""))
                      .find(selector) :
                    XMLHttpRequest.responseText;
                }
                else {
                  if (textStatus == 'timeout') {
                    ajaxTimeout = true;
                  }
                  content = opts.ajaxError.replace(/%error/g, XMLHttpRequest.statusText);
                }
                if ($(target).hasClass('bt-active')) {
                  target.btOn();
                }
              }
            }, opts.ajaxOpts);
            jQuery.ajax(ajaxOpts);
            content = opts.ajaxLoading;
          }
        }
        if (opts.offsetParent){
          var offsetParent = $(opts.offsetParent);
          var offsetParentPos = offsetParent.offset();
          var pos = $(this).offset();
          var top = numb(pos.top) - numb(offsetParentPos.top) + numb($(this).css('margin-top'));
          var left = numb(pos.left) - numb(offsetParentPos.left) + numb($(this).css('margin-left'));
        }
        else {
          var offsetParent = ($(this).css('position') == 'absolute') ? $(this).parents().eq(0).offsetParent() : $(this).offsetParent();
          var pos = $(this).btPosition();
          var top = numb(pos.top) + numb($(this).css('margin-top'));
          var left = numb(pos.left) + numb($(this).css('margin-left'));
        }
        var width = $(this).btOuterWidth();
        var height = $(this).outerHeight();
        if (typeof content == 'object') {
          var original = content;
          var clone = $(original).clone(true).show();
          var origClones = $(original).data('bt-clones') || [];
          origClones.push(clone);
          $(original).data('bt-clones', origClones);
          $(clone).data('bt-orig', original);
          $(this).data('bt-content-orig', {original: original, clone: clone});
          content = clone;
        }
        if (typeof content == 'null' || content == '') {
          return;
        }
        var $text = $('<div class="bt-content"></div>').append(content).css({position: 'absolute', zIndex: opts.textzIndex, left: 0, top: 0});
        var $box = $('<div class="bt-wrapper"></div>').append($text).css({position: 'absolute', zIndex: opts.wrapperzIndex, visibility:'hidden'}).appendTo(offsetParent);
        $(this).data('bt-box', $box);
        var scrollTop = numb($(document).scrollTop());
        var scrollLeft = numb($(document).scrollLeft());
        var docWidth = numb($(window).width());
        var docHeight = numb($(window).height());
        var winRight = scrollLeft + docWidth;
        var winBottom = scrollTop + docHeight;
        var space = new Object();
        var thisOffset = $(this).offset();
        space.top = thisOffset.top - scrollTop;
        space.bottom = docHeight - ((thisOffset + height) - scrollTop);
        space.left = thisOffset.left - scrollLeft;
        space.right = docWidth - ((thisOffset.left + width) - scrollLeft);
        var textOutHeight = numb($text.outerHeight());
        var textOutWidth = numb($text.btOuterWidth());
        if (opts.positions.constructor == String) {
          opts.positions = opts.positions.replace(/ /, '').split(',');
        }
        if (opts.positions[0] == 'most') {
          var position = 'top';
          for (var pig in space) {  //      <-------  pigs in space!
            position = space[pig] > space[position] ? pig : position;
          }
        }
        else {
          for (var x in opts.positions) {
            var position = opts.positions[x];
            if ((position == 'left' || position == 'right') && space[position] > textOutWidth) {
              break;
            }
            else if ((position == 'top' || position == 'bottom') && space[position] > textOutHeight) {
              break;
            }
          }
        }
		// Keep the next two lines intact as backups.
		//var horiz = left + ((width - textOutWidth) * .5);
        var horiz = left + (width * .5);
        var vert = top + ((height - textOutHeight) * .5);
        var points = new Array();
        var textTop, textLeft, textRight, textBottom, textTopSpace, textBottomSpace, textLeftSpace, textRightSpace, textCenter;
        switch(position) {

         case 'top':
            $text.css('margin-bottom', 0);
            $box.css({top: (top - $text.outerHeight(true)), left: horiz});
            textRightSpace = (winRight - opts.windowMargin) - ($text.offset().left + $text.btOuterWidth(true));
            var xShift = 0;
            if (textRightSpace < 0) {
              // shift it left
              $box.css('left', (numb($box.css('left')) + textRightSpace) + 'px');
              xShift -= textRightSpace;
            }
            // we test left space second to ensure that left of box is visible
            textLeftSpace = ($text.offset().left + numb($text.css('margin-left'))) - (scrollLeft + opts.windowMargin);
            if (textLeftSpace < 0) {
              // shift it right
              $box.css('left', (numb($box.css('left')) - textLeftSpace) + 'px');
              xShift += textLeftSpace;
            }
            textTop = $text.btPosition().top + numb($text.css('margin-top'));
            textLeft = $text.btPosition().left + numb($text.css('margin-left'));
            textRight = textLeft + $text.btOuterWidth();
            textBottom = textTop + $text.outerHeight();
            textCenter = {x: textLeft + $text.btOuterWidth(), y: textTop + $text.outerHeight()};
            break;

          case 'bottom':
            // spike on top
            $text.css('margin-top', 0);
            $box.css({top: (top + height), left: horiz});
            // move text up/down if extends out of window
            textRightSpace = (winRight - opts.windowMargin) - ($text.offset().left + $text.btOuterWidth(true));
            var xShift = 0;
            if (textRightSpace < 0) {
              // shift it left
              $box.css('left', (numb($box.css('left')) + textRightSpace) + 'px');
              xShift -= textRightSpace;
            }
            // we ensure left space second to ensure that left of box is visible
            textLeftSpace = ($text.offset().left + numb($text.css('margin-left')))  - (scrollLeft + opts.windowMargin);
            if (textLeftSpace < 0) {
              // shift it right
              $box.css('left', (numb($box.css('left')) - textLeftSpace) + 'px');
              xShift += textLeftSpace;
            }
            textTop = $text.btPosition().top + numb($text.css('margin-top'));
            textLeft = $text.btPosition().left + numb($text.css('margin-left'));
            textRight = textLeft + $text.btOuterWidth();
            textBottom = textTop + $text.outerHeight();
            textCenter = {x: textLeft + $text.btOuterWidth(), y: textTop + $text.outerHeight()};
            break;

          case 'left':
            $text.css('margin-right', 0);
            $box.css({top: vert + 'px', left: (left - $text.btOuterWidth(true)) + 'px'});
            textBottomSpace = (winBottom - opts.windowMargin) - ($text.offset().top + $text.outerHeight(true));
            var yShift = 0;
            if (textBottomSpace < 0) {
              $box.css('top', (numb($box.css('top')) + textBottomSpace) + 'px');
              yShift -= textBottomSpace;
            }
            textTopSpace = ($text.offset().top + numb($text.css('margin-top'))) - (scrollTop + opts.windowMargin);
            if (textTopSpace < 0) {
              $box.css('top', (numb($box.css('top')) - textTopSpace) + 'px');
              yShift += textTopSpace;
            }
            textTop = $text.btPosition().top + numb($text.css('margin-top'));
            textLeft = $text.btPosition().left + numb($text.css('margin-left'));
            textRight = textLeft + $text.btOuterWidth();
            textBottom = textTop + $text.outerHeight();
            textCenter = {x: textLeft + $text.btOuterWidth(), y: textTop + $text.outerHeight()};
            break;
          case 'right':
            $text.css('margin-left', 0);
            $box.css({top: vert + 'px', left: ((left + width) - opts.overlap) + 'px'});
            textBottomSpace = (winBottom - opts.windowMargin) - ($text.offset().top + $text.outerHeight(true));
            var yShift = 0;
            if (textBottomSpace < 0) {
              $box.css('top', (numb($box.css('top')) + textBottomSpace) + 'px');
              yShift -= textBottomSpace;
            }
            textTopSpace = ($text.offset().top + numb($text.css('margin-top'))) - (scrollTop + opts.windowMargin);
            if (textTopSpace < 0) {
              $box.css('top', (numb($box.css('top')) - textTopSpace) + 'px');
              yShift += textTopSpace;
            }
            textTop = $text.btPosition().top + numb($text.css('margin-top'));
            textLeft = $text.btPosition().left + numb($text.css('margin-left'));
            textRight = textLeft + $text.btOuterWidth();
            textBottom = textTop + $text.outerHeight();
            textCenter = {x: textLeft + $text.btOuterWidth(), y: textTop + $text.outerHeight()};
            break;
        }
        opts.preShow.apply(this, [$box[0]]);
        $box.css({display:'none', visibility: 'visible'});
        opts.showTip.apply(this, [$box[0]]);
        if ((opts.ajaxPath != null && opts.ajaxCache == false) || ajaxTimeout) {
          content = false;
        }
        if (opts.clickAnywhereToClose) {
          jQuery.bt.vars.clickAnywhereStack.push(this);
          $(document).click(jQuery.bt.docClick);
        }
        if (opts.closeWhenOthersOpen) {
          jQuery.bt.vars.closeWhenOpenStack.push(this);
        }
        opts.postShow.apply(this, [$box[0]]);
      };
      this.btOff = function() {
        var box = $(this).data('bt-box');
        opts.preHide.apply(this, [box]);
        var i = this;
        i.btCleanup = function(){
          var box = $(i).data('bt-box');
          var contentOrig = $(i).data('bt-content-orig');
          var overlay = $(i).data('bt-overlay');
          if (typeof box == 'object') {
            $(box).remove();
            $(i).removeData('bt-box');
          }
          if (typeof contentOrig == 'object') {
            var clones = $(contentOrig.original).data('bt-clones');
            $(contentOrig).data('bt-clones', arrayRemove(clones, contentOrig.clone));        
          }
          if (typeof overlay == 'object') {
            $(overlay).remove();
            $(i).removeData('bt-overlay');
          }
          jQuery.bt.vars.clickAnywhereStack = arrayRemove(jQuery.bt.vars.clickAnywhereStack, i);
          jQuery.bt.vars.closeWhenOpenStack = arrayRemove(jQuery.bt.vars.closeWhenOpenStack, i);
          $(i).removeClass('bt-active ' + opts.activeClass);
          opts.postHide.apply(i);
        }
        opts.hideTip.apply(this, [box, i.btCleanup]);
      };
      var refresh = this.btRefresh = function() {
        this.btOff();
        this.btOn();
      };
    });
    function numb(num) {
      return parseInt(num) || 0;
    }; 
    function arrayRemove(arr, elem) {
      var x, newArr = new Array();
      for (x in arr) {
        if (arr[x] != elem) {
          newArr.push(arr[x]);
        }
      }
      return newArr;
    };
  };
  jQuery.fn.btPosition = function() {
    function num(elem, prop) {
      return elem[0] && parseInt( jQuery.curCSS(elem[0], prop, true), 10 ) || 0;
    };
    var left = 0, top = 0, results;
    if ( this[0] ) {
      var offsetParent = this.offsetParent(),
      offset       = this.offset(),
      parentOffset = /^body|html$/i.test(offsetParent[0].tagName) ? { top: 0, left: 0 } : offsetParent.offset();
      offset.top  -= num( this, 'marginTop' );
      offset.left -= num( this, 'marginLeft' );
      parentOffset.top  += num( offsetParent, 'borderTopWidth' );
      parentOffset.left += num( offsetParent, 'borderLeftWidth' );
      results = {
        top:  offset.top  - parentOffset.top,
        left: offset.left - parentOffset.left
      };
    }
    return results;
  };
  jQuery.fn.btOuterWidth = function(margin) {
      function num(elem, prop) {
          return elem[0] && parseInt(jQuery.curCSS(elem[0], prop, true), 10) || 0;
      };
      return this["innerWidth"]()
      + num(this, "borderLeftWidth")
      + num(this, "borderRightWidth")
      + (margin ? num(this, "marginLeft")
      + num(this, "marginRight") : 0);
  };
  jQuery.fn.btOn = function() {
    return this.each(function(index){
      if (jQuery.isFunction(this.btOn)) {
        this.btOn();
      }
    });
  };
  jQuery.fn.btOff = function() {
    return this.each(function(index){
      if (jQuery.isFunction(this.btOff)) {
        this.btOff();
      }
    });
  };
  jQuery.bt.vars = {clickAnywhereStack: [], closeWhenOpenStack: []};
  jQuery.bt.docClick = function(e) {
    if (!e) {
      var e = window.event;
    };
    if (!$(e.target).parents().andSelf().filter('.bt-wrapper, .bt-active').length && jQuery.bt.vars.clickAnywhereStack.length) {
      $(jQuery.bt.vars.clickAnywhereStack).btOff();
      $(document).unbind('click', jQuery.bt.docClick);
    }
  };
  /* Defaults can be written for an entire page by redefining attributes:
   * jQuery.bt.options.width = 400;
   * Be sure to use *jQuery.bt.options* and not jQuery.bt.defaults when overriding
   * Each of these options may also be overridden globally or at time of call.*/
  jQuery.bt.defaults = {
    trigger:             'hover',            // trigger to show/hide tip - hoverIntent becomes default if available
    clickAnywhereToClose:true,               // clicking outside of the tip will close it 
    closeWhenOthersOpen: true,               // tip will be closed before another opens
    killTitle:           true,               // kill title tags to avoid double tooltips
    textzIndex:          9999,               // z-index for the text
    boxzIndex:           9998,               // z-index for the "talk" box (should always be less than textzIndex)
    wrapperzIndex:       9997,
    offsetParent:        null,               // DOM node to append the tooltip into. Must be positioned relative or absolute.
    positions:           ['top', 'bottom'],  // preference of positions for tip (will use first with available space) 'top', 'bottom', 'left', 'right', 'most'
    windowMargin:     10,                    // space (px) to leave between text box and browser edge
    activeClass:      'bt-active',           // class added to TARGET element when its BeautyTip is active
    contentSelector:  "$(this).attr('title')", // if there is no content argument, use this selector to retrieve the title
                                             // a function which returns the content may also be passed here
    ajaxPath:         null,                  // if using ajax request for content, this contains url and (opt) selector                                             
    ajaxError:        '<strong>ERROR:</strong> <em>%error</em>',
                                             // error text, use "%error" to insert error from server
    ajaxLoading:     '<blink>Loading...</blink>',  // yes folks, it's the blink tag!
    ajaxData:         {},                    // key/value pairs
    ajaxType:         'GET',                 // 'GET' or 'POST'
    ajaxCache:        true,                  // cache ajax results and do not send request to same url multiple times
    ajaxOpts:         {},                    // any other ajax options - timeout, passwords, processing functions, etc...
    preBuild:         function(){},          // function to run before popup is built
    preShow:          function(box){},       // function to run before popup is displayed
    showTip:          function(box){
                        $(box).show();
                      },
    postShow:         function(box){},       // function to run after popup is built and displayed
    
    preHide:          function(box){},       // function to run before popup is removed
    hideTip:          function(box, callback) {
                        $(box).hide();
                        callback();          // you MUST call "callback" at the end of your animations
                      },
    postHide:         function(){},          // function to run after popup is removed
    hoverIntentOpts:  {                      // options for hoverIntent (if installed)
                        interval: 100,       // http://cherne.net/brian/resources/jquery.hoverIntent.html
                        timeout: 500
                      }
  };
  jQuery.bt.options = {};
})(jQuery);
