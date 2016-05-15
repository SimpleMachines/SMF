(function($){$.fn.SMFtooltip=function(oInstanceSettings){$.fn.SMFtooltip.oDefaultsSettings={followMouse:1,hoverIntent:{sensitivity:10,interval:300,timeout:50},positionTop:12,positionLeft:12,tooltipID:'smf_tooltip',tooltipTextID:'smf_tooltipText',tooltipClass:'tooltip',tooltipSwapClass:'smf_swaptip',tooltipContent:'html'};var oSettings=$.extend({},$.fn.SMFtooltip.oDefaultsSettings,oInstanceSettings||{});$(this).each(function(){var sTitle=$('<span class="'+oSettings.tooltipSwapClass+'">'+htmlspecialchars(this.title)+'</span>').hide();$(this).append(sTitle).attr('title','')});var positionTooltip=function(event){var iPosx=0;var iPosy=0;if(!event)
var event=window.event;if(event.pageX||event.pageY){iPosx=event.pageX;iPosy=event.pageY}
else if(event.clientX||event.clientY){iPosx=event.clientX+document.body.scrollLeft+document.documentElement.scrollLeft;iPosy=event.clientY+document.body.scrollTop+document.documentElement.scrollTop}
var oPosition={x:iPosx+oSettings.positionLeft,y:iPosy+oSettings.positionTop,w:$('#'+oSettings.tooltipID).width(),h:$('#'+oSettings.tooltipID).height()}
var oLimits={x:$(window).scrollLeft(),y:$(window).scrollTop(),w:$(window).width()-24,h:$(window).height()-24};if((oPosition.y+oPosition.h>oLimits.y+oLimits.h)&&(oPosition.x+oPosition.w>oLimits.x+oLimits.w)){oPosition.x=(oPosition.x-oPosition.w)-45;oPosition.y=(oPosition.y-oPosition.h)-45}
else if((oPosition.x+oPosition.w)>(oLimits.x+oLimits.w)){oPosition.x=oPosition.x-(((oPosition.x+oPosition.w)-(oLimits.x+oLimits.w))+24)}
else if(oPosition.y+oPosition.h>oLimits.y+oLimits.h){oPosition.y=oPosition.y-(((oPosition.y+oPosition.h)-(oLimits.y+oLimits.h))+24)}
$('#'+oSettings.tooltipID).css({'left':oPosition.x+'px','top':oPosition.y+'px'})}
var showTooltip=function(){$('#'+oSettings.tooltipID+' #'+oSettings.tooltipTextID).show()}
var hideTooltip=function(valueOfThis){$('#'+oSettings.tooltipID).fadeOut('slow').trigger("unload").remove()}
function htmlspecialchars(string){return $('<span>').text(string).html()}
return this.each(function(index){if($.fn.hoverIntent){$(this).hoverIntent({sensitivity:oSettings.hoverIntent.sensitivity,interval:oSettings.hoverIntent.interval,over:smf_tooltip_on,timeout:oSettings.hoverIntent.timeout,out:smf_tooltip_off})}
else{$(this).hover(smf_tooltip_on,smf_tooltip_off)}
function smf_tooltip_on(event){if($(this).children('.'+oSettings.tooltipSwapClass).text()){$('body').append('<div id="'+oSettings.tooltipID+'" class="'+oSettings.tooltipClass+'"><div id="'+oSettings.tooltipTextID+'" style="display:none;"></div></div>');var tt=$('#'+oSettings.tooltipID);var ttContent=$('#'+oSettings.tooltipID+' #'+oSettings.tooltipTextID);if(oSettings.tooltipContent=='html')
ttContent.html($(this).children('.'+oSettings.tooltipSwapClass).html());else ttContent.text($(this).children('.'+oSettings.tooltipSwapClass).text());oSettings.tooltipContent
tt.show();showTooltip();positionTooltip(event)}
return !1};function smf_tooltip_off(event){hideTooltip(this);return !1};if(oSettings.followMouse){$(this).bind("mousemove",function(event){positionTooltip(event);return !1})}
$(this).bind("click",function(event){hideTooltip(this);return !0})})};$.fn.fadeOutAndRemove=function(speed){$(this).fadeOut(speed,function(){$(this).remove()})};$.fn.rangeToPercent=function(number,min,max){return((number-min)/(max-min))};$.fn.percentToRange=function(percent,min,max){return((max-min)*percent+min)}})(jQuery);(function($){$.fn.animaDrag=function(o,callback){var defaults={speed:400,interval:300,easing:null,cursor:'move',boundary:document.body,grip:null,overlay:!0,after:function(e){},during:function(e){},before:function(e){},afterEachAnimation:function(e){}}
if(typeof callback=='function'){defaults.after=callback}
o=$.extend(defaults,o||{});return this.each(function(){var id,startX,startY,draggableStartX,draggableStartY,dragging=!1,Ev,draggable=this,grip=($(this).find(o.grip).length>0)?$(this).find(o.grip):$(this);if(o.boundary){var limitTop=$(o.boundary).offset().top,limitLeft=$(o.boundary).offset().left,limitBottom=limitTop+$(o.boundary).innerHeight(),limitRight=limitLeft+$(o.boundary).innerWidth()}
grip.mousedown(function(e){o.before.call(draggable,e);var lastX,lastY;dragging=!0;Ev=e;startX=lastX=e.pageX;startY=lastY=e.pageY;draggableStartX=$(draggable).offset().left;draggableStartY=$(draggable).offset().top;$(draggable).css({position:'absolute',left:draggableStartX+'px',top:draggableStartY+'px',cursor:o.cursor,zIndex:'1010'}).addClass('anima-drag').appendTo(document.body);if(o.overlay&&$('#anima-drag-overlay').length==0){$('<div id="anima-drag-overlay"></div>').css({position:'absolute',top:'smf_tooltip',left:'smf_tooltip',zIndex:'1000',width:$(document.body).outerWidth()+'px',height:$(document.body).outerHeight()+'px'}).appendTo(document.body)}
else if(o.overlay){$('#anima-drag-overlay').show()}
id=setInterval(function(){if(lastX!=Ev.pageX||lastY!=Ev.pageY){var positionX=draggableStartX-(startX-Ev.pageX),positionY=draggableStartY-(startY-Ev.pageY);if(positionX<limitLeft&&o.boundary){positionX=limitLeft}
else if(positionX+$(draggable).innerWidth()>limitRight&&o.boundary){positionX=limitRight-$(draggable).outerWidth()}
if(positionY<limitTop&&o.boundary){positionY=limitTop}
else if(positionY+$(draggable).innerHeight()>limitBottom&&o.boundary){positionY=limitBottom-$(draggable).outerHeight()}
$(draggable).stop().animate({left:positionX+'px',top:positionY+'px'},o.speed,o.easing,function(){o.afterEachAnimation.call(draggable,Ev)})}
lastX=Ev.pageX;lastY=Ev.pageY},o.interval);($.browser.safari||e.preventDefault())});$(document).mousemove(function(e){if(dragging){Ev=e;o.during.call(draggable,e)}});$(document).mouseup(function(e){if(dragging){$(draggable).css({cursor:'',zIndex:'990'}).removeClass('anima-drag');$('#anima-drag-overlay').hide().appendTo(document.body);clearInterval(id);o.after.call(draggable,e);dragging=!1}})})}})(jQuery);(function($,w){"use strict";var methods=(function(){var c={bcClass:'sf-breadcrumb',menuClass:'sf-js-enabled',anchorClass:'sf-with-ul',menuArrowClass:'sf-arrows'},ios=(function(){var ios=/^(?![\w\W]*Windows Phone)[\w\W]*(iPhone|iPad|iPod)/i.test(navigator.userAgent);if(ios){$('html').css('cursor','pointer').on('click',$.noop)}
return ios})(),wp7=(function(){var style=document.documentElement.style;return('behavior' in style&&'fill' in style&&/iemobile/i.test(navigator.userAgent))})(),unprefixedPointerEvents=(function(){return(!!w.PointerEvent)})(),toggleMenuClasses=function($menu,o){var classes=c.menuClass;if(o.cssArrows){classes+=' '+c.menuArrowClass}
$menu.toggleClass(classes)},setPathToCurrent=function($menu,o){return $menu.find('li.'+o.pathClass).slice(0,o.pathLevels).addClass(o.hoverClass+' '+c.bcClass).filter(function(){return($(this).children(o.popUpSelector).hide().show().length)}).removeClass(o.pathClass)},toggleAnchorClass=function($li){$li.children('a').toggleClass(c.anchorClass)},toggleTouchAction=function($menu){var msTouchAction=$menu.css('ms-touch-action');var touchAction=$menu.css('touch-action');touchAction=touchAction||msTouchAction;touchAction=(touchAction==='pan-y')?'auto':'pan-y';$menu.css({'ms-touch-action':touchAction,'touch-action':touchAction})},applyHandlers=function($menu,o){var targets='li:has('+o.popUpSelector+')';if($.fn.hoverIntent&&!o.disableHI){$menu.hoverIntent(over,out,targets)}
else{$menu.on('mouseenter.superfish',targets,over).on('mouseleave.superfish',targets,out)}
var touchevent='MSPointerDown.superfish';if(unprefixedPointerEvents){touchevent='pointerdown.superfish'}
if(!ios){touchevent+=' touchend.superfish'}
if(wp7){touchevent+=' mousedown.superfish'}
$menu.on('focusin.superfish','li',over).on('focusout.superfish','li',out).on(touchevent,'a',o,touchHandler)},touchHandler=function(e){var $this=$(this),o=getOptions($this),$ul=$this.siblings(e.data.popUpSelector);if(o.onHandleTouch.call($ul)===!1){return this}
if($ul.length>0&&$ul.is(':hidden')){$this.one('click.superfish',!1);if(e.type==='MSPointerDown'||e.type==='pointerdown'){$this.trigger('focus')}else{$.proxy(over,$this.parent('li'))()}}},over=function(){var $this=$(this),o=getOptions($this);clearTimeout(o.sfTimer);$this.siblings().superfish('hide').end().superfish('show')},out=function(){var $this=$(this),o=getOptions($this);if(ios){$.proxy(close,$this,o)()}
else{clearTimeout(o.sfTimer);o.sfTimer=setTimeout($.proxy(close,$this,o),o.delay)}},close=function(o){o.retainPath=($.inArray(this[0],o.$path)>-1);this.superfish('hide');if(!this.parents('.'+o.hoverClass).length){o.onIdle.call(getMenu(this));if(o.$path.length){$.proxy(over,o.$path)()}}},getMenu=function($el){return $el.closest('.'+c.menuClass)},getOptions=function($el){return getMenu($el).data('sf-options')};return{hide:function(instant){if(this.length){var $this=this,o=getOptions($this);if(!o){return this}
var not=(o.retainPath===!0)?o.$path:'',$ul=$this.find('li.'+o.hoverClass).add(this).not(not).removeClass(o.hoverClass).children(o.popUpSelector),speed=o.speedOut;if(instant){$ul.show();speed=0}
o.retainPath=!1;if(o.onBeforeHide.call($ul)===!1){return this}
$ul.stop(!0,!0).animate(o.animationOut,speed,function(){var $this=$(this);o.onHide.call($this)})}
return this},show:function(){var o=getOptions(this);if(!o){return this}
var $this=this.addClass(o.hoverClass),$ul=$this.children(o.popUpSelector);if(o.onBeforeShow.call($ul)===!1){return this}
$ul.stop(!0,!0).animate(o.animation,o.speed,function(){o.onShow.call($ul)});return this},destroy:function(){return this.each(function(){var $this=$(this),o=$this.data('sf-options'),$hasPopUp;if(!o){return !1}
$hasPopUp=$this.find(o.popUpSelector).parent('li');clearTimeout(o.sfTimer);toggleMenuClasses($this,o);toggleAnchorClass($hasPopUp);toggleTouchAction($this);$this.off('.superfish').off('.hoverIntent');$hasPopUp.children(o.popUpSelector).attr('style',function(i,style){return style.replace(/display[^;]+;?/g,'')});o.$path.removeClass(o.hoverClass+' '+c.bcClass).addClass(o.pathClass);$this.find('.'+o.hoverClass).removeClass(o.hoverClass);o.onDestroy.call($this);$this.removeData('sf-options')})},init:function(op){return this.each(function(){var $this=$(this);if($this.data('sf-options')){return !1}
var o=$.extend({},$.fn.superfish.defaults,op),$hasPopUp=$this.find(o.popUpSelector).parent('li');o.$path=setPathToCurrent($this,o);$this.data('sf-options',o);toggleMenuClasses($this,o);toggleAnchorClass($hasPopUp);toggleTouchAction($this);applyHandlers($this,o);$hasPopUp.not('.'+c.bcClass).superfish('hide',!0);o.onInit.call(this)})}}})();$.fn.superfish=function(method,args){if(methods[method]){return methods[method].apply(this,Array.prototype.slice.call(arguments,1))}
else if(typeof method==='object'||!method){return methods.init.apply(this,arguments)}
else{return $.error('Method '+method+' does not exist on jQuery.fn.superfish')}};$.fn.superfish.defaults={popUpSelector:'ul,.sf-mega',hoverClass:'sfHover',pathClass:'overrideThisToUse',pathLevels:1,delay:800,animation:{opacity:'show'},animationOut:{opacity:'hide'},speed:'normal',speedOut:'fast',cssArrows:!0,disableHI:!1,onInit:$.noop,onBeforeShow:$.noop,onShow:$.noop,onBeforeHide:$.noop,onHide:$.noop,onIdle:$.noop,onDestroy:$.noop,onHandleTouch:$.noop}})(jQuery,window);(function($){$.fn.hoverIntent=function(handlerIn,handlerOut,selector){var cfg={interval:100,sensitivity:7,timeout:0};if(typeof handlerIn==="object"){cfg=$.extend(cfg,handlerIn)}else if($.isFunction(handlerOut)){cfg=$.extend(cfg,{over:handlerIn,out:handlerOut,selector:selector})}else{cfg=$.extend(cfg,{over:handlerIn,out:handlerIn,selector:handlerOut})}
var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){$(ob).off("mousemove.hoverIntent",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev])}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev])};var handleHover=function(e){var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t)}
if(e.type=="mouseenter"){pX=ev.pageX;pY=ev.pageY;$(ob).on("mousemove.hoverIntent",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob)},cfg.interval)}}else{$(ob).off("mousemove.hoverIntent",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob)},cfg.timeout)}}};return this.on({'mouseenter.hoverIntent':handleHover,'mouseleave.hoverIntent':handleHover},cfg.selector)}})(jQuery);$(function(){if(smf_member_id>0)
$('div.boardindex_table div.cat_bar').each(function(index,el){var catid=el.id.replace('category_','');new smc_Toggle({bToggleEnabled:!0,bCurrentlyCollapsed:$('#category_'+catid+'_upshrink').data('collapsed'),aSwappableContainers:['category_'+catid+'_boards'],aSwapImages:[{sId:'category_'+catid+'_upshrink',msgExpanded:'',msgCollapsed:''}],oThemeOptions:{bUseThemeSettings:!0,sOptionName:'collapse_category_'+catid,sSessionVar:smf_session_var,sSessionId:smf_session_id}})})});$(function(){$('.mobile_act').click(function(){$('#mobile_action').show()});$('.hide_popup').click(function(){$('#mobile_action').hide()});$('.mobile_mod').click(function(){$('#mobile_moderation').show()});$('.hide_popup').click(function(){$('#mobile_moderation').hide()});$('.mobile_user_menu').click(function(){$('#mobile_user_menu').show()});$('.hide_popup').click(function(){$('#mobile_user_menu').hide()})});var smf_formSubmitted=!1;var lastKeepAliveCheck=new Date().getTime();var smf_editorArray=new Array();var ua=navigator.userAgent.toLowerCase();var is_opera=ua.indexOf('opera')!=-1;var is_ff=(ua.indexOf('firefox')!=-1||ua.indexOf('iceweasel')!=-1||ua.indexOf('icecat')!=-1||ua.indexOf('shiretoko')!=-1||ua.indexOf('minefield')!=-1)&&!is_opera;var is_gecko=ua.indexOf('gecko')!=-1&&!is_opera;var is_chrome=ua.indexOf('chrome')!=-1;var is_safari=ua.indexOf('applewebkit')!=-1&&!is_chrome;var is_webkit=ua.indexOf('applewebkit')!=-1;var is_ie=ua.indexOf('msie')!=-1&&!is_opera;var is_ie11=ua.indexOf('trident')!=-1&&ua.indexOf('gecko')!=-1;var is_iphone=ua.indexOf('iphone')!=-1||ua.indexOf('ipod')!=-1;var is_android=ua.indexOf('android')!=-1;var ajax_indicator_ele=null;if(!('XMLHttpRequest' in window)&&'ActiveXObject' in window)
window.XMLHttpRequest=function(){return new ActiveXObject('MSXML2.XMLHTTP')};if(!('forms' in document))
document.forms=document.getElementsByTagName('form');if(!('getElementsByClassName' in document)){document.getElementsByClassName=function(className){return $('".'+className+'"')}}
function getXMLDocument(sUrl,funcCallback){if(!window.XMLHttpRequest)
return null;var oMyDoc=new XMLHttpRequest();var bAsync=typeof(funcCallback)!='undefined';var oCaller=this;if(bAsync){oMyDoc.onreadystatechange=function(){if(oMyDoc.readyState!=4)
return;if(oMyDoc.responseXML!=null&&oMyDoc.status==200){if(funcCallback.call){funcCallback.call(oCaller,oMyDoc.responseXML)}
else{oCaller.tmpMethod=funcCallback;oCaller.tmpMethod(oMyDoc.responseXML);delete oCaller.tmpMethod}}}}
oMyDoc.open('GET',sUrl,bAsync);oMyDoc.send(null);return oMyDoc}
function sendXMLDocument(sUrl,sContent,funcCallback){if(!window.XMLHttpRequest)
return !1;var oSendDoc=new window.XMLHttpRequest();var oCaller=this;if(typeof(funcCallback)!='undefined'){oSendDoc.onreadystatechange=function(){if(oSendDoc.readyState!=4)
return;if(oSendDoc.responseXML!=null&&oSendDoc.status==200)
funcCallback.call(oCaller,oSendDoc.responseXML);else funcCallback.call(oCaller,!1)}}
oSendDoc.open('POST',sUrl,!0);if('setRequestHeader' in oSendDoc)
oSendDoc.setRequestHeader('Content-Type','application/x-www-form-urlencoded');oSendDoc.send(sContent);return !0}
String.prototype.oCharsetConversion={from:'',to:''};String.prototype.php_to8bit=function(){if(smf_charset=='UTF-8'){var n,sReturn='';for(var i=0,iTextLen=this.length;i<iTextLen;i++){n=this.charCodeAt(i);if(n<128)
sReturn+=String.fromCharCode(n);else if(n<2048)
sReturn+=String.fromCharCode(192|n>>6)+String.fromCharCode(128|n&63);else if(n<65536)
sReturn+=String.fromCharCode(224|n>>12)+String.fromCharCode(128|n>>6&63)+String.fromCharCode(128|n&63);else sReturn+=String.fromCharCode(240|n>>18)+String.fromCharCode(128|n>>12&63)+String.fromCharCode(128|n>>6&63)+String.fromCharCode(128|n&63)}
return sReturn}
else if(this.oCharsetConversion.from.length==0){switch(smf_charset){case 'ISO-8859-1':this.oCharsetConversion={from:'\xa0-\xff',to:'\xa0-\xff'};break;case 'ISO-8859-2':this.oCharsetConversion={from:'\xa0\u0104\u02d8\u0141\xa4\u013d\u015a\xa7\xa8\u0160\u015e\u0164\u0179\xad\u017d\u017b\xb0\u0105\u02db\u0142\xb4\u013e\u015b\u02c7\xb8\u0161\u015f\u0165\u017a\u02dd\u017e\u017c\u0154\xc1\xc2\u0102\xc4\u0139\u0106\xc7\u010c\xc9\u0118\xcb\u011a\xcd\xce\u010e\u0110\u0143\u0147\xd3\xd4\u0150\xd6\xd7\u0158\u016e\xda\u0170\xdc\xdd\u0162\xdf\u0155\xe1\xe2\u0103\xe4\u013a\u0107\xe7\u010d\xe9\u0119\xeb\u011b\xed\xee\u010f\u0111\u0144\u0148\xf3\xf4\u0151\xf6\xf7\u0159\u016f\xfa\u0171\xfc\xfd\u0163\u02d9',to:'\xa0-\xff'};break;case 'ISO-8859-5':this.oCharsetConversion={from:'\xa0\u0401-\u040c\xad\u040e-\u044f\u2116\u0451-\u045c\xa7\u045e\u045f',to:'\xa0-\xff'};break;case 'ISO-8859-9':this.oCharsetConversion={from:'\xa0-\xcf\u011e\xd1-\xdc\u0130\u015e\xdf-\xef\u011f\xf1-\xfc\u0131\u015f\xff',to:'\xa0-\xff'};break;case 'ISO-8859-15':this.oCharsetConversion={from:'\xa0-\xa3\u20ac\xa5\u0160\xa7\u0161\xa9-\xb3\u017d\xb5-\xb7\u017e\xb9-\xbb\u0152\u0153\u0178\xbf-\xff',to:'\xa0-\xff'};break;case 'tis-620':this.oCharsetConversion={from:'\u20ac\u2026\u2018\u2019\u201c\u201d\u2022\u2013\u2014\xa0\u0e01-\u0e3a\u0e3f-\u0e5b',to:'\x80\x85\x91-\x97\xa0-\xda\xdf-\xfb'};break;case 'windows-1251':this.oCharsetConversion={from:'\u0402\u0403\u201a\u0453\u201e\u2026\u2020\u2021\u20ac\u2030\u0409\u2039\u040a\u040c\u040b\u040f\u0452\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u2122\u0459\u203a\u045a\u045c\u045b\u045f\xa0\u040e\u045e\u0408\xa4\u0490\xa6\xa7\u0401\xa9\u0404\xab-\xae\u0407\xb0\xb1\u0406\u0456\u0491\xb5-\xb7\u0451\u2116\u0454\xbb\u0458\u0405\u0455\u0457\u0410-\u044f',to:'\x80-\x97\x99-\xff'};break;case 'windows-1253':this.oCharsetConversion={from:'\u20ac\u201a\u0192\u201e\u2026\u2020\u2021\u2030\u2039\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u2122\u203a\xa0\u0385\u0386\xa3-\xa9\xab-\xae\u2015\xb0-\xb3\u0384\xb5-\xb7\u0388-\u038a\xbb\u038c\xbd\u038e-\u03a1\u03a3-\u03ce',to:'\x80\x82-\x87\x89\x8b\x91-\x97\x99\x9b\xa0-\xa9\xab-\xd1\xd3-\xfe'};break;case 'windows-1255':this.oCharsetConversion={from:'\u20ac\u201a\u0192\u201e\u2026\u2020\u2021\u02c6\u2030\u2039\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u02dc\u2122\u203a\xa0-\xa3\u20aa\xa5-\xa9\xd7\xab-\xb9\xf7\xbb-\xbf\u05b0-\u05b9\u05bb-\u05c3\u05f0-\u05f4\u05d0-\u05ea\u200e\u200f',to:'\x80\x82-\x89\x8b\x91-\x99\x9b\xa0-\xc9\xcb-\xd8\xe0-\xfa\xfd\xfe'};break;case 'windows-1256':this.oCharsetConversion={from:'\u20ac\u067e\u201a\u0192\u201e\u2026\u2020\u2021\u02c6\u2030\u0679\u2039\u0152\u0686\u0698\u0688\u06af\u2018\u2019\u201c\u201d\u2022\u2013\u2014\u06a9\u2122\u0691\u203a\u0153\u200c\u200d\u06ba\xa0\u060c\xa2-\xa9\u06be\xab-\xb9\u061b\xbb-\xbe\u061f\u06c1\u0621-\u0636\xd7\u0637-\u063a\u0640-\u0643\xe0\u0644\xe2\u0645-\u0648\xe7-\xeb\u0649\u064a\xee\xef\u064b-\u064e\xf4\u064f\u0650\xf7\u0651\xf9\u0652\xfb\xfc\u200e\u200f\u06d2',to:'\x80-\xff'};break;default:this.oCharsetConversion={from:'',to:''};break}
var funcExpandString=function(sSearch){var sInsert='';for(var i=sSearch.charCodeAt(0),n=sSearch.charCodeAt(2);i<=n;i++)
sInsert+=String.fromCharCode(i);return sInsert};this.oCharsetConversion.from=this.oCharsetConversion.from.replace(/.\-./g,funcExpandString);this.oCharsetConversion.to=this.oCharsetConversion.to.replace(/.\-./g,funcExpandString)}
var sReturn='',iOffsetFrom=0;for(var i=0,n=this.length;i<n;i++){iOffsetFrom=this.oCharsetConversion.from.indexOf(this.charAt(i));sReturn+=iOffsetFrom>-1?this.oCharsetConversion.to.charAt(iOffsetFrom):(this.charCodeAt(i)>127?'&#'+this.charCodeAt(i)+';':this.charAt(i))}
return sReturn}
String.prototype.php_strtr=function(sFrom,sTo){return this.replace(new RegExp('['+sFrom+']','g'),function(sMatch){return sTo.charAt(sFrom.indexOf(sMatch))})}
String.prototype.php_strtolower=function(){return typeof(smf_iso_case_folding)=='boolean'&&smf_iso_case_folding==!0?this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde','abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9e\xff\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe'):this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')}
String.prototype.php_urlencode=function(){return escape(this).replace(/\+/g,'%2b').replace('*','%2a').replace('/','%2f').replace('@','%40')}
String.prototype.php_htmlspecialchars=function(){return this.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
String.prototype.php_unhtmlspecialchars=function(){return this.replace(/&quot;/g,'"').replace(/&gt;/g,'>').replace(/&lt;/g,'<').replace(/&amp;/g,'&')}
String.prototype.php_addslashes=function(){return this.replace(/\\/g,'\\\\').replace(/'/g,'\\\'')}
String.prototype._replaceEntities=function(sInput,sDummy,sNum){return String.fromCharCode(parseInt(sNum))}
String.prototype.removeEntities=function(){return this.replace(/&(amp;)?#(\d+);/g,this._replaceEntities)}
String.prototype.easyReplace=function(oReplacements){var sResult=this;for(var sSearch in oReplacements)
sResult=sResult.replace(new RegExp('%'+sSearch+'%','g'),oReplacements[sSearch]);return sResult}
function reqWin(desktopURL,alternateWidth,alternateHeight,noScrollbars){if((alternateWidth&&self.screen.availWidth*0.8<alternateWidth)||(alternateHeight&&self.screen.availHeight*0.8<alternateHeight)){noScrollbars=!1;alternateWidth=Math.min(alternateWidth,self.screen.availWidth*0.8);alternateHeight=Math.min(alternateHeight,self.screen.availHeight*0.8)}
else noScrollbars=typeof(noScrollbars)=='boolean'&&noScrollbars==!0;window.open(desktopURL,'requested_popup','toolbar=no,location=no,status=no,menubar=no,scrollbars='+(noScrollbars?'no':'yes')+',width='+(alternateWidth?alternateWidth:480)+',height='+(alternateHeight?alternateHeight:220)+',resizable=no');return !1}
function reqOverlayDiv(desktopURL,sHeader,sIcon){var sAjax_indicator='<div class="centertext"><img src="'+smf_images_url+'/loading_sm.gif"></div>';var sIcon=smf_images_url+'/'+(typeof(sIcon)=='string'?sIcon:'helptopics.png');var sHeader=typeof(sHeader)=='string'?sHeader:help_popup_heading_text;var oContainer=new smc_Popup({heading:sHeader,content:sAjax_indicator,icon:sIcon});var oPopup_body=$('#'+oContainer.popup_id).find('.popup_content');$.ajax({url:desktopURL,type:"GET",dataType:"html",beforeSend:function(){},success:function(data,textStatus,xhr){var help_content=$('<div id="temp_help">').html(data).find('a[href$="self.close();"]').hide().prev('br').hide().parent().html();oPopup_body.html(help_content)},error:function(xhr,textStatus,errorThrown){oPopup_body.html(textStatus)}});return !1}
function smc_PopupMenu(oOptions){this.opt=(typeof oOptions=='object')?oOptions:{};this.opt.menus={}}
smc_PopupMenu.prototype.add=function(sItem,sUrl){var $menu=$('#'+sItem+'_menu'),$item=$('#'+sItem+'_menu_top');if($item.length==0)
return;this.opt.menus[sItem]={open:!1,loaded:!1,sUrl:sUrl,itemObj:$item,menuObj:$menu};$item.click({obj:this},function(e){e.preventDefault();e.data.obj.toggle(sItem)})}
smc_PopupMenu.prototype.toggle=function(sItem){if(!!this.opt.menus[sItem].open)
this.close(sItem);else this.open(sItem)}
smc_PopupMenu.prototype.open=function(sItem){this.closeAll();if(!this.opt.menus[sItem].loaded){this.opt.menus[sItem].menuObj.html('<div class="loading">'+(typeof(ajax_notification_text)!=null?ajax_notification_text:'')+'</div>');this.opt.menus[sItem].menuObj.load(this.opt.menus[sItem].sUrl,function(){if($(this).hasClass('scrollable'))
$(this).customScrollbar({skin:"default-skin",hScroll:!1,updateOnWindowResize:!0})});this.opt.menus[sItem].loaded=!0}
this.opt.menus[sItem].menuObj.addClass('visible');this.opt.menus[sItem].itemObj.addClass('open');this.opt.menus[sItem].open=!0;$(document).on('click.menu',{obj:this},function(e){if($(e.target).closest(e.data.obj.opt.menus[sItem].menuObj.parent()).length)
return;e.data.obj.closeAll();$(document).off('click.menu')})}
smc_PopupMenu.prototype.close=function(sItem){this.opt.menus[sItem].menuObj.removeClass('visible');this.opt.menus[sItem].itemObj.removeClass('open');this.opt.menus[sItem].open=!1;$(document).off('click.menu')}
smc_PopupMenu.prototype.closeAll=function(){for(var prop in this.opt.menus)
if(!!this.opt.menus[prop].open)
this.close(prop)}
function smc_Popup(oOptions){this.opt=oOptions;this.popup_id=this.opt.custom_id?this.opt.custom_id:'smf_popup';this.show()}
smc_Popup.prototype.show=function(){popup_class='popup_window '+(this.opt.custom_class?this.opt.custom_class:'description');if(this.opt.icon_class)
icon='<span class="'+this.opt.icon_class+'"></span> ';else icon=this.opt.icon?'<img src="'+this.opt.icon+'" class="icon" alt=""> ':'';$('body').append('<div id="'+this.popup_id+'" class="popup_container"><div class="'+popup_class+'"><div class="catbg popup_heading"><a href="javascript:void(0);" class="generic_icons hide_popup"></a>'+icon+this.opt.heading+'</div><div class="popup_content">'+this.opt.content+'</div></div></div>');this.popup_body=$('#'+this.popup_id).children('.popup_window');this.popup_body.parent().fadeIn(300);var popup_instance=this;$(document).mouseup(function(e){if($('#'+popup_instance.popup_id).has(e.target).length===0)
popup_instance.hide()}).keyup(function(e){if(e.keyCode==27)
popup_instance.hide()});$('#'+this.popup_id).find('.hide_popup').click(function(){return popup_instance.hide()});return !1}
smc_Popup.prototype.hide=function(){$('#'+this.popup_id).fadeOut(300,function(){$(this).remove()});return !1}
function storeCaret(oTextHandle){if('createTextRange' in oTextHandle)
oTextHandle.caretPos=document.selection.createRange().duplicate()}
function replaceText(text,oTextHandle){if('caretPos' in oTextHandle&&'createTextRange' in oTextHandle){var caretPos=oTextHandle.caretPos;caretPos.text=caretPos.text.charAt(caretPos.text.length-1)==' '?text+' ':text;caretPos.select()}
else if('selectionStart' in oTextHandle){var begin=oTextHandle.value.substr(0,oTextHandle.selectionStart);var end=oTextHandle.value.substr(oTextHandle.selectionEnd);var scrollPos=oTextHandle.scrollTop;oTextHandle.value=begin+text+end;if(oTextHandle.setSelectionRange){oTextHandle.focus();var goForward=is_opera?text.match(/\n/g).length:0;oTextHandle.setSelectionRange(begin.length+text.length+goForward,begin.length+text.length+goForward)}
oTextHandle.scrollTop=scrollPos}
else{oTextHandle.value+=text;oTextHandle.focus(oTextHandle.value.length-1)}}
function surroundText(text1,text2,oTextHandle){if('caretPos' in oTextHandle&&'createTextRange' in oTextHandle){var caretPos=oTextHandle.caretPos,temp_length=caretPos.text.length;caretPos.text=caretPos.text.charAt(caretPos.text.length-1)==' '?text1+caretPos.text+text2+' ':text1+caretPos.text+text2;if(temp_length==0){caretPos.moveStart('character',-text2.length);caretPos.moveEnd('character',-text2.length);caretPos.select()}
else oTextHandle.focus(caretPos)}
else if('selectionStart' in oTextHandle){var begin=oTextHandle.value.substr(0,oTextHandle.selectionStart);var selection=oTextHandle.value.substr(oTextHandle.selectionStart,oTextHandle.selectionEnd-oTextHandle.selectionStart);var end=oTextHandle.value.substr(oTextHandle.selectionEnd);var newCursorPos=oTextHandle.selectionStart;var scrollPos=oTextHandle.scrollTop;oTextHandle.value=begin+text1+selection+text2+end;if(oTextHandle.setSelectionRange){var goForward=is_opera?text1.match(/\n/g).length:0,goForwardAll=is_opera?(text1+text2).match(/\n/g).length:0;if(selection.length==0)
oTextHandle.setSelectionRange(newCursorPos+text1.length+goForward,newCursorPos+text1.length+goForward);else oTextHandle.setSelectionRange(newCursorPos,newCursorPos+text1.length+selection.length+text2.length+goForwardAll);oTextHandle.focus()}
oTextHandle.scrollTop=scrollPos}
else{oTextHandle.value+=text1+text2;oTextHandle.focus(oTextHandle.value.length-1)}}
function isEmptyText(theField){if(typeof(theField)=='string')
var theValue=theField;else var theValue=theField.value;while(theValue.length>0&&(theValue.charAt(0)==' '||theValue.charAt(0)=='\t'))
theValue=theValue.substring(1,theValue.length);while(theValue.length>0&&(theValue.charAt(theValue.length-1)==' '||theValue.charAt(theValue.length-1)=='\t'))
theValue=theValue.substring(0,theValue.length-1);if(theValue=='')
return !0;else return !1}
function submitonce(theform){smf_formSubmitted=!0;for(var i=0;i<smf_editorArray.length;i++)
smf_editorArray[i].doSubmit();}
function submitThisOnce(oControl){var oForm='form' in oControl?oControl.form:oControl;var aTextareas=oForm.getElementsByTagName('textarea');for(var i=0,n=aTextareas.length;i<n;i++)
aTextareas[i].readOnly=!0;return !smf_formSubmitted}
function setInnerHTML(oElement,sToValue){oElement.innerHTML=sToValue}
function getInnerHTML(oElement){return oElement.innerHTML}
function setOuterHTML(oElement,sToValue){if('outerHTML' in oElement)
oElement.outerHTML=sToValue;else{var range=document.createRange();range.setStartBefore(oElement);oElement.parentNode.replaceChild(range.createContextualFragment(sToValue),oElement)}}
function in_array(variable,theArray){for(var i in theArray)
if(theArray[i]==variable)
return !0;return !1}
function array_search(variable,theArray){for(var i in theArray)
if(theArray[i]==variable)
return i;return null}
function selectRadioByName(oRadioGroup,sName){if(!('length' in oRadioGroup))
return oRadioGroup.checked=!0;for(var i=0,n=oRadioGroup.length;i<n;i++)
if(oRadioGroup[i].value==sName)
return oRadioGroup[i].checked=!0;return !1}
function selectAllRadio(oInvertCheckbox,oForm,sMask,sValue,bIgnoreDisabled){for(var i=0;i<oForm.length;i++)
if(oForm[i].name!=undefined&&oForm[i].name.substr(0,sMask.length)==sMask&&oForm[i].value==sValue&&(!oForm[i].disabled||(typeof(bIgnoreDisabled)=='boolean'&&bIgnoreDisabled)))
oForm[i].checked=!0}
function invertAll(oInvertCheckbox,oForm,sMask,bIgnoreDisabled){for(var i=0;i<oForm.length;i++){if(!('name' in oForm[i])||(typeof(sMask)=='string'&&oForm[i].name.substr(0,sMask.length)!=sMask&&oForm[i].id.substr(0,sMask.length)!=sMask))
continue;if(!oForm[i].disabled||(typeof(bIgnoreDisabled)=='boolean'&&bIgnoreDisabled))
oForm[i].checked=oInvertCheckbox.checked}}
var lastKeepAliveCheck=new Date().getTime();function smf_sessionKeepAlive(){var curTime=new Date().getTime();if(smf_scripturl&&curTime-lastKeepAliveCheck>900000){var tempImage=new Image();tempImage.src=smf_prepareScriptUrl(smf_scripturl)+'action=keepalive;time='+curTime;lastKeepAliveCheck=curTime}
window.setTimeout('smf_sessionKeepAlive();',1200000)}
window.setTimeout('smf_sessionKeepAlive();',1200000);function smf_setThemeOption(theme_var,theme_value,theme_id,theme_cur_session_id,theme_cur_session_var,theme_additional_vars){if(theme_cur_session_id==null)
theme_cur_session_id=smf_session_id;if(typeof(theme_cur_session_var)=='undefined')
theme_cur_session_var='sesc';if(theme_additional_vars==null)
theme_additional_vars='';var tempImage=new Image();tempImage.src=smf_prepareScriptUrl(smf_scripturl)+'action=jsoption;var='+theme_var+';val='+theme_value+';'+theme_cur_session_var+'='+theme_cur_session_id+theme_additional_vars+(theme_id==null?'':'&th='+theme_id)+';time='+(new Date().getTime())}
function expandPages(spanNode,baseLink,firstPage,lastPage,perPage){var replacement='',i,oldLastPage=0;var perPageLimit=50;if((lastPage-firstPage)/perPage>perPageLimit){oldLastPage=lastPage;lastPage=firstPage+perPageLimit*perPage}
for(i=firstPage;i<lastPage;i+=perPage)
replacement+=baseLink.replace(/%1\$d/,i).replace(/%2\$s/,1+i/perPage).replace(/%%/g,'%');$(spanNode).before(replacement);if(oldLastPage)
spanNode.onclick=function(){expandPages(spanNode,baseLink,lastPage,oldLastPage,perPage)};else $(spanNode).remove()}
function smc_preCacheImage(sSrc){if(!('smc_aCachedImages' in window))
window.smc_aCachedImages=[];if(!in_array(sSrc,window.smc_aCachedImages)){var oImage=new Image();oImage.src=sSrc}}
function smc_Cookie(oOptions){this.opt=oOptions;this.oCookies={};this.init()}
smc_Cookie.prototype.init=function(){if('cookie' in document&&document.cookie!=''){var aCookieList=document.cookie.split(';');for(var i=0,n=aCookieList.length;i<n;i++){var aNameValuePair=aCookieList[i].split('=');this.oCookies[aNameValuePair[0].replace(/^\s+|\s+$/g,'')]=decodeURIComponent(aNameValuePair[1])}}}
smc_Cookie.prototype.get=function(sKey){return sKey in this.oCookies?this.oCookies[sKey]:null}
smc_Cookie.prototype.set=function(sKey,sValue){document.cookie=sKey+'='+encodeURIComponent(sValue)}
function smc_Toggle(oOptions){this.opt=oOptions;this.bCollapsed=!1;this.oCookie=null;this.init()}
smc_Toggle.prototype.init=function(){if('bToggleEnabled' in this.opt&&!this.opt.bToggleEnabled)
return;if('oCookieOptions' in this.opt&&this.opt.oCookieOptions.bUseCookie){this.oCookie=new smc_Cookie({});var cookieValue=this.oCookie.get(this.opt.oCookieOptions.sCookieName)
if(cookieValue!=null)
this.opt.bCurrentlyCollapsed=cookieValue=='1'}
if('aSwapImages' in this.opt){for(var i=0,n=this.opt.aSwapImages.length;i<n;i++){this.opt.aSwapImages[i].isCSS=(typeof this.opt.aSwapImages[i].srcCollapsed=='undefined');if(this.opt.aSwapImages[i].isCSS){if(!this.opt.aSwapImages[i].cssCollapsed)
this.opt.aSwapImages[i].cssCollapsed='toggle_down';if(!this.opt.aSwapImages[i].cssExpanded)
this.opt.aSwapImages[i].cssExpanded='toggle_up'}
else{smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed)}
$('#'+this.opt.aSwapImages[i].sId).show();var oImage=document.getElementById(this.opt.aSwapImages[i].sId);if(typeof(oImage)=='object'&&oImage!=null){oImage.instanceRef=this;oImage.onclick=function(){this.instanceRef.toggle();this.blur()}
oImage.style.cursor='pointer'}}}
if('aSwapLinks' in this.opt){for(var i=0,n=this.opt.aSwapLinks.length;i<n;i++){var oLink=document.getElementById(this.opt.aSwapLinks[i].sId);if(typeof(oLink)=='object'&&oLink!=null){if(oLink.style.display=='none')
oLink.style.display='';oLink.instanceRef=this;oLink.onclick=function(){this.instanceRef.toggle();this.blur();return !1}}}}
if(this.opt.bCurrentlyCollapsed)
this.changeState(!0,!0)}
smc_Toggle.prototype.changeState=function(bCollapse,bInit){bInit=typeof(bInit)=='undefined'?!1:!0;if(!bInit&&bCollapse&&'funcOnBeforeCollapse' in this.opt){this.tmpMethod=this.opt.funcOnBeforeCollapse;this.tmpMethod();delete this.tmpMethod}
else if(!bInit&&!bCollapse&&'funcOnBeforeExpand' in this.opt){this.tmpMethod=this.opt.funcOnBeforeExpand;this.tmpMethod();delete this.tmpMethod}
if('aSwapImages' in this.opt){for(var i=0,n=this.opt.aSwapImages.length;i<n;i++){if(this.opt.aSwapImages[i].isCSS){$('#'+this.opt.aSwapImages[i].sId).toggleClass(this.opt.aSwapImages[i].cssCollapsed,bCollapse).toggleClass(this.opt.aSwapImages[i].cssExpanded,!bCollapse).attr('title',bCollapse?this.opt.aSwapImages[i].altCollapsed:this.opt.aSwapImages[i].altExpanded)}
else{var oImage=document.getElementById(this.opt.aSwapImages[i].sId);if(typeof(oImage)=='object'&&oImage!=null){var sTargetSource=bCollapse?this.opt.aSwapImages[i].srcCollapsed:this.opt.aSwapImages[i].srcExpanded;if(oImage.src!=sTargetSource)
oImage.src=sTargetSource;oImage.alt=oImage.title=bCollapse?this.opt.aSwapImages[i].altCollapsed:this.opt.aSwapImages[i].altExpanded}}}}
if('aSwapLinks' in this.opt){for(var i=0,n=this.opt.aSwapLinks.length;i<n;i++){var oLink=document.getElementById(this.opt.aSwapLinks[i].sId);if(typeof(oLink)=='object'&&oLink!=null)
setInnerHTML(oLink,bCollapse?this.opt.aSwapLinks[i].msgCollapsed:this.opt.aSwapLinks[i].msgExpanded)}}
for(var i=0,n=this.opt.aSwappableContainers.length;i<n;i++){if(this.opt.aSwappableContainers[i]==null)
continue;var oContainer=document.getElementById(this.opt.aSwappableContainers[i]);if(typeof(oContainer)=='object'&&oContainer!=null){if(!!this.opt.bNoAnimate||bInit){$(oContainer).toggle(!bCollapse)}
else{if(bCollapse)
$(oContainer).slideUp();else $(oContainer).slideDown()}}}
this.bCollapsed=bCollapse;if('oCookieOptions' in this.opt&&this.opt.oCookieOptions.bUseCookie)
this.oCookie.set(this.opt.oCookieOptions.sCookieName,this.bCollapsed|0);if(!bInit&&'oThemeOptions' in this.opt&&this.opt.oThemeOptions.bUseThemeSettings)
smf_setThemeOption(this.opt.oThemeOptions.sOptionName,this.bCollapsed|0,'sThemeId' in this.opt.oThemeOptions?this.opt.oThemeOptions.sThemeId:null,smf_session_id,smf_session_var,'sAdditionalVars' in this.opt.oThemeOptions?this.opt.oThemeOptions.sAdditionalVars:null)}
smc_Toggle.prototype.toggle=function(){this.changeState(!this.bCollapsed)}
function ajax_indicator(turn_on){if(ajax_indicator_ele==null){ajax_indicator_ele=document.getElementById('ajax_in_progress');if(ajax_indicator_ele==null&&typeof(ajax_notification_text)!=null){create_ajax_indicator_ele()}}
if(ajax_indicator_ele!=null){ajax_indicator_ele.style.display=turn_on?'block':'none'}}
function create_ajax_indicator_ele(){ajax_indicator_ele=document.createElement('div');ajax_indicator_ele.id='ajax_in_progress';ajax_indicator_ele.innerHTML+=ajax_notification_text;document.body.appendChild(ajax_indicator_ele)}
function createEventListener(oTarget){if(!('addEventListener' in oTarget)){if(oTarget.attachEvent){oTarget.addEventListener=function(sEvent,funcHandler,bCapture){oTarget.attachEvent('on'+sEvent,funcHandler)}
oTarget.removeEventListener=function(sEvent,funcHandler,bCapture){oTarget.detachEvent('on'+sEvent,funcHandler)}}
else{oTarget.addEventListener=function(sEvent,funcHandler,bCapture){oTarget['on'+sEvent]=funcHandler}
oTarget.removeEventListener=function(sEvent,funcHandler,bCapture){oTarget['on'+sEvent]=null}}}}
function grabJumpToContent(elem){var oXMLDoc=getXMLDocument(smf_prepareScriptUrl(smf_scripturl)+'action=xmlhttp;sa=jumpto;xml');var aBoardsAndCategories=new Array();var bIE5x=!('implementation' in document);ajax_indicator(!0);if(oXMLDoc.responseXML){var items=oXMLDoc.responseXML.getElementsByTagName('smf')[0].getElementsByTagName('item');for(var i=0,n=items.length;i<n;i++){aBoardsAndCategories[aBoardsAndCategories.length]={id:parseInt(items[i].getAttribute('id')),isCategory:items[i].getAttribute('type')=='category',name:items[i].firstChild.nodeValue.removeEntities(),is_current:!1,childLevel:parseInt(items[i].getAttribute('childlevel'))}}}
ajax_indicator(!1);for(var i=0,n=aJumpTo.length;i<n;i++)
aJumpTo[i].fillSelect(aBoardsAndCategories);if(bIE5x)
elem.options[iIndexPointer].selected=!0;elem.style.width='auto';elem.focus()}
var aJumpTo=new Array();function JumpTo(oJumpToOptions){this.opt=oJumpToOptions;this.dropdownList=null;this.showSelect()}
JumpTo.prototype.showSelect=function(){var sChildLevelPrefix='';for(var i=this.opt.iCurBoardChildLevel;i>0;i--)
sChildLevelPrefix+=this.opt.sBoardChildLevelIndicator;setInnerHTML(document.getElementById(this.opt.sContainerId),this.opt.sJumpToTemplate.replace(/%select_id%/,this.opt.sContainerId+'_select').replace(/%dropdown_list%/,'<select '+(this.opt.bDisabled==!0?'disabled ':'')+(this.opt.sClassName!=undefined?'class="'+this.opt.sClassName+'" ':'')+'name="'+(this.opt.sCustomName!=undefined?this.opt.sCustomName:this.opt.sContainerId+'_select')+'" id="'+this.opt.sContainerId+'_select" '+('implementation' in document?'':'onmouseover="grabJumpToContent(this);" ')+('onbeforeactivate' in document?'onbeforeactivate':'onfocus')+'="grabJumpToContent(this);"><option value="'+(this.opt.bNoRedirect!=undefined&&this.opt.bNoRedirect==!0?this.opt.iCurBoardId:'?board='+this.opt.iCurBoardId+'.0')+'">'+sChildLevelPrefix+this.opt.sBoardPrefix+this.opt.sCurBoardName.removeEntities()+'</option></select>&nbsp;'+(this.opt.sGoButtonLabel!=undefined?'<input type="button" class="button_submit" value="'+this.opt.sGoButtonLabel+'" onclick="window.location.href = \''+smf_prepareScriptUrl(smf_scripturl)+'board='+this.opt.iCurBoardId+'.0\';">':'')));this.dropdownList=document.getElementById(this.opt.sContainerId+'_select')}
JumpTo.prototype.fillSelect=function(aBoardsAndCategories){var iIndexPointer=0;var oDashOption=document.createElement('option');oDashOption.appendChild(document.createTextNode(this.opt.sCatSeparator));oDashOption.disabled='disabled';oDashOption.value='';if('onbeforeactivate' in document)
this.dropdownList.onbeforeactivate=null;else this.dropdownList.onfocus=null;if(this.opt.bNoRedirect)
this.dropdownList.options[0].disabled='disabled';var oListFragment=document.createDocumentFragment();for(var i=0,n=aBoardsAndCategories.length;i<n;i++){var j,sChildLevelPrefix,oOption;if(!aBoardsAndCategories[i].isCategory&&aBoardsAndCategories[i].id==this.opt.iCurBoardId){this.dropdownList.insertBefore(oListFragment,this.dropdownList.options[0]);oListFragment=document.createDocumentFragment();continue}
if(aBoardsAndCategories[i].isCategory)
oListFragment.appendChild(oDashOption.cloneNode(!0));else for(j=aBoardsAndCategories[i].childLevel,sChildLevelPrefix='';j>0;j--)
sChildLevelPrefix+=this.opt.sBoardChildLevelIndicator;oOption=document.createElement('option');oOption.appendChild(document.createTextNode((aBoardsAndCategories[i].isCategory?this.opt.sCatPrefix:sChildLevelPrefix+this.opt.sBoardPrefix)+aBoardsAndCategories[i].name));if(!this.opt.bNoRedirect)
oOption.value=aBoardsAndCategories[i].isCategory?'#c'+aBoardsAndCategories[i].id:'?board='+aBoardsAndCategories[i].id+'.0';else{if(aBoardsAndCategories[i].isCategory)
oOption.disabled='disabled';else oOption.value=aBoardsAndCategories[i].id}
oListFragment.appendChild(oOption);if(aBoardsAndCategories[i].isCategory)
oListFragment.appendChild(oDashOption.cloneNode(!0))}
this.dropdownList.appendChild(oListFragment);if(!this.opt.bNoRedirect)
this.dropdownList.onchange=function(){if(this.selectedIndex>0&&this.options[this.selectedIndex].value)
window.location.href=smf_scripturl+this.options[this.selectedIndex].value.substr(smf_scripturl.indexOf('?')==-1||this.options[this.selectedIndex].value.substr(0,1)!='?'?0:1)}}
var aIconLists=new Array();function IconList(oOptions){if(!window.XMLHttpRequest)
return;this.opt=oOptions;this.bListLoaded=!1;this.oContainerDiv=null;this.funcMousedownHandler=null;this.funcParent=this;this.iCurMessageId=0;this.iCurTimeout=0;if(!('sSessionVar' in this.opt))
this.opt.sSessionVar='sesc';this.initIcons()}
IconList.prototype.initIcons=function(){for(var i=document.images.length-1,iPrefixLength=this.opt.sIconIdPrefix.length;i>=0;i--)
if(document.images[i].id.substr(0,iPrefixLength)==this.opt.sIconIdPrefix)
setOuterHTML(document.images[i],'<div title="'+this.opt.sLabelIconList+'" onclick="'+this.opt.sBackReference+'.openPopup(this, '+document.images[i].id.substr(iPrefixLength)+')" onmouseover="'+this.opt.sBackReference+'.onBoxHover(this, true)" onmouseout="'+this.opt.sBackReference+'.onBoxHover(this, false)" style="background: '+this.opt.sBoxBackground+'; cursor: pointer; padding: 3px; text-align: center;"><img src="'+document.images[i].src+'" alt="'+document.images[i].alt+'" id="'+document.images[i].id+'" style="margin: 0px; padding: '+(is_ie?'3px':'3px 0px 3px 0px')+';"></div>');}
IconList.prototype.onBoxHover=function(oDiv,bMouseOver){oDiv.style.border=bMouseOver?this.opt.iBoxBorderWidthHover+'px solid '+this.opt.sBoxBorderColorHover:'';oDiv.style.background=bMouseOver?this.opt.sBoxBackgroundHover:this.opt.sBoxBackground;oDiv.style.padding=bMouseOver?(3-this.opt.iBoxBorderWidthHover)+'px':'3px'}
IconList.prototype.openPopup=function(oDiv,iMessageId){this.iCurMessageId=iMessageId;if(!this.bListLoaded&&this.oContainerDiv==null){this.oContainerDiv=document.createElement('div');this.oContainerDiv.id='iconList';this.oContainerDiv.style.display='none';this.oContainerDiv.style.cursor='pointer';this.oContainerDiv.style.position='absolute';this.oContainerDiv.style.background=this.opt.sContainerBackground;this.oContainerDiv.style.border=this.opt.sContainerBorder;this.oContainerDiv.style.padding='6px 0px';document.body.appendChild(this.oContainerDiv);ajax_indicator(!0);sendXMLDocument.call(this,smf_prepareScriptUrl(smf_scripturl)+'action=xmlhttp;sa=messageicons;board='+this.opt.iBoardId+';xml','',this.onIconsReceived);createEventListener(document.body)}
var aPos=smf_itemPos(oDiv);this.oContainerDiv.style.top=(aPos[1]+oDiv.offsetHeight)+'px';this.oContainerDiv.style.left=(aPos[0]-1)+'px';this.oClickedIcon=oDiv;if(this.bListLoaded)
this.oContainerDiv.style.display='block';document.body.addEventListener('mousedown',this.onWindowMouseDown,!1)}
IconList.prototype.onIconsReceived=function(oXMLDoc){var icons=oXMLDoc.getElementsByTagName('smf')[0].getElementsByTagName('icon');var sItems='';for(var i=0,n=icons.length;i<n;i++)
sItems+='<span onmouseover="'+this.opt.sBackReference+'.onItemHover(this, true)" onmouseout="'+this.opt.sBackReference+'.onItemHover(this, false);" onmousedown="'+this.opt.sBackReference+'.onItemMouseDown(this, \''+icons[i].getAttribute('value')+'\');" style="padding: 2px 3px; line-height: 20px; border: '+this.opt.sItemBorder+'; background: '+this.opt.sItemBackground+'"><img src="'+icons[i].getAttribute('url')+'" alt="'+icons[i].getAttribute('name')+'" title="'+icons[i].firstChild.nodeValue+'" style="vertical-align: middle"></span>';setInnerHTML(this.oContainerDiv,sItems);this.oContainerDiv.style.display='block';this.bListLoaded=!0;if(is_ie)
this.oContainerDiv.style.width=this.oContainerDiv.clientWidth+'px';ajax_indicator(!1)}
IconList.prototype.onItemHover=function(oDiv,bMouseOver){oDiv.style.background=bMouseOver?this.opt.sItemBackgroundHover:this.opt.sItemBackground;oDiv.style.border=bMouseOver?this.opt.sItemBorderHover:this.opt.sItemBorder;if(this.iCurTimeout!=0)
window.clearTimeout(this.iCurTimeout);if(bMouseOver)
this.onBoxHover(this.oClickedIcon,!0);else this.iCurTimeout=window.setTimeout(this.opt.sBackReference+'.collapseList();',500)}
IconList.prototype.onItemMouseDown=function(oDiv,sNewIcon){if(this.iCurMessageId!=0){ajax_indicator(!0);this.tmpMethod=getXMLDocument;var oXMLDoc=this.tmpMethod(smf_prepareScriptUrl(smf_scripturl)+'action=jsmodify;topic='+this.opt.iTopicId+';msg='+this.iCurMessageId+';'+smf_session_var+'='+smf_session_id+';icon='+sNewIcon+';xml');delete this.tmpMethod;ajax_indicator(!1);var oMessage=oXMLDoc.responseXML.getElementsByTagName('smf')[0].getElementsByTagName('message')[0];if(oMessage.getElementsByTagName('error').length==0){if(this.opt.bShowModify&&oMessage.getElementsByTagName('modified').length!=0)
setInnerHTML(document.getElementById('modified_'+this.iCurMessageId),oMessage.getElementsByTagName('modified')[0].childNodes[0].nodeValue);this.oClickedIcon.getElementsByTagName('img')[0].src=oDiv.getElementsByTagName('img')[0].src}}}
IconList.prototype.onWindowMouseDown=function(){for(var i=aIconLists.length-1;i>=0;i--){aIconLists[i].funcParent.tmpMethod=aIconLists[i].collapseList;aIconLists[i].funcParent.tmpMethod();delete aIconLists[i].funcParent.tmpMethod}}
IconList.prototype.collapseList=function(){this.onBoxHover(this.oClickedIcon,!1);this.oContainerDiv.style.display='none';this.iCurMessageId=0;document.body.removeEventListener('mousedown',this.onWindowMouseDown,!1)}
function smf_mousePose(oEvent){var x=0;var y=0;if(oEvent.pageX){y=oEvent.pageY;x=oEvent.pageX}
else if(oEvent.clientX){x=oEvent.clientX+(document.documentElement.scrollLeft?document.documentElement.scrollLeft:document.body.scrollLeft);y=oEvent.clientY+(document.documentElement.scrollTop?document.documentElement.scrollTop:document.body.scrollTop)}
return[x,y]}
function smf_itemPos(itemHandle){var itemX=0;var itemY=0;if('offsetParent' in itemHandle){itemX=itemHandle.offsetLeft;itemY=itemHandle.offsetTop;while(itemHandle.offsetParent&&typeof(itemHandle.offsetParent)=='object'){itemHandle=itemHandle.offsetParent;itemX+=itemHandle.offsetLeft;itemY+=itemHandle.offsetTop}}
else if('x' in itemHandle){itemX=itemHandle.x;itemY=itemHandle.y}
return[itemX,itemY]}
function smf_prepareScriptUrl(sUrl){return sUrl.indexOf('?')==-1?sUrl+'?':sUrl+(sUrl.charAt(sUrl.length-1)=='?'||sUrl.charAt(sUrl.length-1)=='&'||sUrl.charAt(sUrl.length-1)==';'?'':';')}
var aOnloadEvents=new Array();function addLoadEvent(fNewOnload){if(typeof(fNewOnload)=='function'&&(!('onload' in window)||typeof(window.onload)!='function'))
window.onload=fNewOnload;else if(aOnloadEvents.length==0){aOnloadEvents[0]=window.onload;aOnloadEvents[1]=fNewOnload;window.onload=function(){for(var i=0,n=aOnloadEvents.length;i<n;i++){if(typeof(aOnloadEvents[i])=='function')
aOnloadEvents[i]();else if(typeof(aOnloadEvents[i])=='string')
eval(aOnloadEvents[i])}}}
else aOnloadEvents[aOnloadEvents.length]=fNewOnload}
function smfFooterHighlight(element,value){element.src=smf_images_url+'/'+(value?'h_':'')+element.id+'.png'}
function smfSelectText(oCurElement,bActOnElement){if(typeof(bActOnElement)=='boolean'&&bActOnElement)
var oCodeArea=document.getElementById(oCurElement);else var oCodeArea=oCurElement.parentNode.nextSibling;if(typeof(oCodeArea)!='object'||oCodeArea==null)
return !1;if('createTextRange' in document.body){var oCurRange=document.body.createTextRange();oCurRange.moveToElementText(oCodeArea);oCurRange.select()}
else if(window.getSelection){var oCurSelection=window.getSelection();if(oCurSelection.setBaseAndExtent){var oLastChild=oCodeArea.lastChild;oCurSelection.setBaseAndExtent(oCodeArea,0,oLastChild,'innerText' in oLastChild?oLastChild.innerText.length:oLastChild.textContent.length)}
else{var curRange=document.createRange();curRange.selectNodeContents(oCodeArea);oCurSelection.removeAllRanges();oCurSelection.addRange(curRange)}}
return !1}
function smc_saveEntities(sFormName,aElementNames,sMask){if(typeof(sMask)=='string'){for(var i=0,n=document.forms[sFormName].elements.length;i<n;i++)
if(document.forms[sFormName].elements[i].id.substr(0,sMask.length)==sMask)
aElementNames[aElementNames.length]=document.forms[sFormName].elements[i].name}
for(var i=0,n=aElementNames.length;i<n;i++){if(aElementNames[i]in document.forms[sFormName])
document.forms[sFormName][aElementNames[i]].value=document.forms[sFormName][aElementNames[i]].value.replace(/&#/g,'&#38;#')}}
function cleanFileInput(idElement){if(is_opera||is_ie||is_safari||is_chrome){document.getElementById(idElement).outerHTML=document.getElementById(idElement).outerHTML}
else{document.getElementById(idElement).type='input';document.getElementById(idElement).type='file'}}
function applyWindowClasses(oList){var bAlternate=!1;oListItems=oList.getElementsByTagName("LI");for(i=0;i<oListItems.length;i++){if(oListItems[i].id=="")
continue;oListItems[i].className="windowbg"+(bAlternate?"2":"");bAlternate=!bAlternate}}
function reActivate(){document.forms.postmodify.message.readOnly=!1}
function showimage(){document.images.icons.src=icon_urls[document.forms.postmodify.icon.options[document.forms.postmodify.icon.selectedIndex].value]}
function pollOptions(){var expire_time=document.getElementById('poll_expire');if(isEmptyText(expire_time)||expire_time.value==0){document.forms.postmodify.poll_hide[2].disabled=!0;if(document.forms.postmodify.poll_hide[2].checked)
document.forms.postmodify.poll_hide[1].checked=!0}
else document.forms.postmodify.poll_hide[2].disabled=!1}
function generateDays(offset){offset=typeof(offset)!='undefined'?offset:'';var days=0,selected=0;var dayElement=document.getElementById("day"+offset),yearElement=document.getElementById("year"+offset),monthElement=document.getElementById("month"+offset);var monthLength=[31,28,31,30,31,30,31,31,30,31,30,31];if(yearElement.options[yearElement.selectedIndex].value%4==0)
monthLength[1]=29;selected=dayElement.selectedIndex;while(dayElement.options.length)
dayElement.options[0]=null;days=monthLength[monthElement.value-1];for(i=1;i<=days;i++)
dayElement.options[dayElement.length]=new Option(i,i);if(selected<days)
dayElement.selectedIndex=selected}
function toggleLinked(form){form.board.disabled=!form.link_to_board.checked}
function initSearch(){if(document.forms.searchform.search.value.indexOf("%u")!=-1)
document.forms.searchform.search.value=unescape(document.forms.searchform.search.value)}
function selectBoards(ids,aFormID){var toggle=!0;var aForm=document.getElementById(aFormID);for(i=0;i<ids.length;i++)
toggle=toggle&aForm["brd"+ids[i]].checked;for(i=0;i<ids.length;i++)
aForm["brd"+ids[i]].checked=!toggle}
function updateRuleDef(optNum){if(document.getElementById("ruletype"+optNum).value=="gid"){document.getElementById("defdiv"+optNum).style.display="none";document.getElementById("defseldiv"+optNum).style.display=""}
else if(document.getElementById("ruletype"+optNum).value=="bud"||document.getElementById("ruletype"+optNum).value==""){document.getElementById("defdiv"+optNum).style.display="none";document.getElementById("defseldiv"+optNum).style.display="none"}
else{document.getElementById("defdiv"+optNum).style.display="";document.getElementById("defseldiv"+optNum).style.display="none"}}
function updateActionDef(optNum){if(document.getElementById("acttype"+optNum).value=="lab"){document.getElementById("labdiv"+optNum).style.display=""}
else{document.getElementById("labdiv"+optNum).style.display="none"}}
$(function(){$('.buttonlist > .dropmenu').each(function(index,item){$(item).prev().click(function(e){e.stopPropagation();e.preventDefault();if($(item).is(':visible')){$(item).css('display','none');return !0}
$(item).css('display','block');$(item).css('top',$(this).offset().top+$(this).height());$(item).css('left',Math.max($(this).offset().left-$(item).width()+$(this).outerWidth(),0));$(item).height($(item).find('div:first').height())});$(document).click(function(){$(item).css('display','none')})});$(document).on('click','.you_sure',function(){var custom_message=$(this).attr('data-confirm');return confirm(custom_message?custom_message.replace(/-n-/g,"\n"):smf_you_sure)});$('.smf_select_text').on('click',function(e){e.preventDefault();var actOnElement=$(this).attr('data-actonelement');return typeof actOnElement!=="undefined"?smfSelectText(actOnElement,!0):smfSelectText(this)})});$(document).ready(function(){$('ul.dropmenu, ul.quickbuttons').superfish({delay:250,speed:100,sensitivity:8,interval:50,timeout:1});$('.preview').SMFtooltip();$('a.bbc_link img.bbc_img').parent().css('border','smf_tooltip')});function smf_codeBoxFix(){var codeFix=$('code');$.each(codeFix,function(index,tag){if(is_webkit&&$(tag).height()<20)
$(tag).css({height:($(tag).height+20)+'px'});else if(is_ff&&($(tag)[0].scrollWidth>$(tag).innerWidth()||$(tag).innerWidth()==0))
$(tag).css({overflow:'scroll'});else if('currentStyle' in $(tag)&&$(tag)[0].currentStyle.overflow=='auto'&&($(tag).innerHeight()==''||$(tag).innerHeight()=='auto')&&($(tag)[0].scrollWidth>$(tag).innerWidth()||$(tag).innerWidth==0)&&($(tag).outerHeight()!=0))
$(tag).css({height:($(tag).height+24)+'px'})})}
if(is_ie||is_webkit||is_ff)
addLoadEvent(smf_codeBoxFix);function smc_toggleImageDimensions(){var images=$('img.bbc_img');$.each(images,function(key,img){if($(img).hasClass('resized')){$(img).css({cursor:'pointer'});$(img).on('click',function(){var size=$(this)[0].style.width=='auto'?'':'auto';$(this).css({width:size,height:size})})}})}
addLoadEvent(smc_toggleImageDimensions);function smf_addButton(stripId,image,options){$('#'+stripId).append('<a href="'+options.sUrl+'" class="button last" '+('sCustom' in options?options.sCustom:'')+' '+('sId' in options?' id="'+options.sId+'_text"':'')+'>'+options.sText+'</a>')}