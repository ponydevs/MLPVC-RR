"use strict";function _possibleConstructorReturn(e,t){if(!e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return!t||"object"!=typeof t&&"function"!=typeof t?e:t}function _inherits(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}var _typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},_createClass=function(){function e(e,t){for(var n=0;n<t.length;n++){var o=t[n];o.enumerable=o.enumerable||!1,o.configurable=!0,"value"in o&&(o.writable=!0),Object.defineProperty(e,o.key,o)}}return function(t,n,o){return n&&e(t.prototype,n),o&&e(t,o),t}}();!function(e){if(void 0===e.Navigation||!0!==e.Navigation.firstLoadDone){"function"!=typeof window.console.log&&(window.console.log=function(){}),"function"!=typeof window.console.group&&(window.console.group=function(){}),"function"!=typeof window.console.groupEnd&&(window.console.groupEnd=function(){}),"function"!=typeof window.console.clear&&(window.console.clear=function(){}),window.mk=function(){return document.createElement.apply(document,arguments)},e.mk=function(t,n){var o=e(document.createElement.call(document,t));return"string"==typeof n&&o.attr("id",n),o};var t=function(){function e(){_classCallCheck(this,e),this.emulatedStorage={}}return _createClass(e,[{key:"getItem",value:function(e){return void 0===this.emulatedStorage[e]?null:this.emulatedStorage[e]}},{key:"setItem",value:function(e,t){this.emulatedStorage[e]="string"==typeof t?t:""+t}},{key:"removeItem",value:function(e){delete this.emulatedStorage[e]}}]),e}(),n=function(){function e(n){_classCallCheck(this,e);var o=n+"Storage";try{this.store=window[n+"Storage"]}catch(e){console.error(o+" is unavailable, falling back to EmulatedStorage"),this.store=new t}}return _createClass(e,[{key:"get",value:function(e){var t=null;try{t=this.store.getItem(e)}catch(e){}return t}},{key:"set",value:function(e,t){try{this.store.setItem(e,t)}catch(e){}}},{key:"remove",value:function(e){try{this.store.removeItem(e)}catch(e){}}}]),e}();e.LocalStorage=new n("local"),e.SessionStorage=new n("session"),e.toAbsoluteURL=function(e){var t=mk("a");return t.href=e,t.href},window.$w=e(window),window.$d=e(document),window.CommonElements=function(){window.$header=e("header"),window.$sbToggle=e(".sidebar-toggle"),window.$main=e("#main"),window.$content=e("#content"),window.$sidebar=e("#sidebar"),window.$footer=e("footer"),window.$body=e("body"),window.$head=e("head"),window.$navbar=$header.find("nav")},window.CommonElements(),window.Key={Tab:9,Enter:13,Alt:18,Space:32,LeftArrow:37,UpArrrow:38,RightArrow:39,DownArrrow:40,Delete:46,0:48,1:49,A:65,H:72,I:73,O:79,Z:90,Comma:188},e.isKey=function(e,t){return t.keyCode===e},function(e){var t={order:"Do MMMM YYYY, H:mm:ss"};t.orderwd="dddd, "+t.order;var n=function(e){function t(e,n){_classCallCheck(this,t);var o=_possibleConstructorReturn(this,(t.__proto__||Object.getPrototypeOf(t)).call(this,e));return o.name="DateFormatError",o.element=n,o}return _inherits(t,Error),t}(),o=function(){function o(){_classCallCheck(this,o)}return _createClass(o,null,[{key:"Update",value:function(){e("time[datetime]:not(.nodt)").addClass("dynt").each(function(){var r=e(this),i=r.attr("datetime");if("string"!=typeof i)throw new TypeError('Invalid date data type: "'+(void 0===i?"undefined":_typeof(i))+'"');var a=moment(i);if(!a.isValid())throw new n('Invalid date format: "'+i+'"',this);var s=moment(),c=!r.attr("data-noweekday"),l=a.from(s),u=r.parent().children(".dynt-el"),d=r.data("dyntime-beforeupdate");"function"==typeof d&&!1===d(o.Difference(s.toDate(),a.toDate()))||(u.length>0||r.hasClass("no-dynt-el")?(r.html(a.format(c?t.orderwd:t.order)),u.html(l)):r.attr("title",a.format(t.order)).html(l))})}},{key:"Difference",value:function(e,t){var n=(e.getTime()-t.getTime())/1e3,o={past:n>0,time:Math.abs(n),target:t},r=o.time;return o.day=Math.floor(r/this.InSeconds.day),r-=o.day*this.InSeconds.day,o.hour=Math.floor(r/this.InSeconds.hour),r-=o.hour*this.InSeconds.hour,o.minute=Math.floor(r/this.InSeconds.minute),r-=o.minute*this.InSeconds.minute,o.second=Math.floor(r),o.day>=7&&(o.week=Math.floor(o.day/7),o.day-=7*o.week),o.week>=4&&(o.month=Math.floor(o.week/4),o.week-=4*o.month),o.month>=12&&(o.year=Math.floor(o.month/12),o.month-=12*o.year),o}}]),o}();o.InSeconds={year:31557600,month:2592e3,week:604800,day:86400,hour:3600,minute:60},window.Time=o,o.Update(),setInterval(o.Update,1e4)}(jQuery),e.capitalize=function(e,t){return t?e.replace(/((?:^|\s)[a-z])/g,function(e){return e.toUpperCase()}):1===e.length?e.toUpperCase():e[0].toUpperCase()+e.substring(1)},"function"!=typeof Array.prototype.includes&&(Array.prototype.includes=function(e){return-1!==this.indexOf(e)}),"function"!=typeof String.prototype.includes&&(String.prototype.includes=function(e){return-1!==this.indexOf(e)}),e.pad=function(t,n,o,r){if("string"!=typeof t&&(t=""+t),"string"!=typeof n&&(n="0"),o="number"!=typeof o&&!isFinite(o)&&isNaN(o)?2:parseInt(o,10),"boolean"!=typeof r&&(r=!0),o<=t.length)return t;var i=new Array(o-t.length+1).join(n);return t=r===e.pad.left?i+t:t+i},e.pad.right=!(e.pad.left=!0),e.scaleResize=function(e,t,n){var o=!(arguments.length>3&&void 0!==arguments[3])||arguments[3],r=void 0,i={scale:n.scale,width:n.width,height:n.height};if(isNaN(i.scale))if(isNaN(i.width)){if(isNaN(i.height))throw new Error("[scalaresize] Invalid arguments");o||(i.height=Math.min(i.height,t)),r=i.height/t,!o&&r>1&&(r=1),i.width=Math.round(e*r),i.scale=r}else o||(i.width=Math.min(i.width,e)),r=i.width/e,!o&&r>1&&(r=1),i.height=Math.round(t*r),i.scale=r;else(o||i.scale<=1)&&(i.height=Math.round(t*i.scale),i.width=Math.round(e*i.scale));return i},e.clearSelection=function(){if(window.getSelection){var e=window.getSelection();e.empty?e.empty():e.removeAllRanges&&e.removeAllRanges()}else document.selection&&document.selection.empty()},e.toArray=function(e){var t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0;return[].slice.call(e,t)},e.clearFocus=function(){document.activeElement!==$body[0]&&document.activeElement.blur()},$w.on("ajaxerror",function(){var t="";if(arguments.length>1){var n=e.toArray(arguments,1);if("abort"===n[1])return;t=" Details:<pre><code>"+n.slice(1).join("\n").replace(/</g,"&lt;")+"</code></pre>Response body:";var o=/^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;o.test(n[0].responseText)?t+='<div class="reset">'+n[0].responseText.replace(o,"$1")+"</div>":"string"==typeof n[0].responseText&&(t+="<pre><code>"+n[0].responseText.replace(/</g,"&lt;")+"</code></pre>")}e.Dialog.fail(!1,"There was an error while processing your request."+t)}),e.mkAjaxHandler=function(e){return function(t){if("object"!==(void 0===t?"undefined":_typeof(t)))return console.log(t),void $w.triggerHandler("ajaxerror");"function"==typeof e&&e.call(t)}},e.callCallback=function(t,n,o){return"object"===(void 0===n?"undefined":_typeof(n))&&e.isArray(n)||(o=n,n=[]),"function"!=typeof t?o:t.apply(window,n)},e.fn.mkData=function(t){var n=this.find(":input:valid").serializeArray(),o={};return e.each(n,function(e,t){/\[]$/.test(t.name)?(void 0===o[t.name]&&(o[t.name]=[]),o[t.name].push(t.value)):o[t.name]=t.value}),"object"===(void 0===t?"undefined":_typeof(t))&&e.extend(o,t),o},e.getCSRFToken=function(){var e=document.cookie.match(/CSRF_TOKEN=([a-z\d]+)/i);if(e&&e.length)return e[1];throw new Error("Missing CSRF_TOKEN")},e.ajaxPrefilter(function(t,n){if("POST"===(n.type||t.type).toUpperCase()){var o=e.getCSRFToken();if(void 0===t.data&&(t.data=""),"string"==typeof t.data){var r=t.data.length>0?t.data.split("&"):[];r.push("CSRF_TOKEN="+o),t.data=r.join("&")}else t.data.CSRF_TOKEN=o}});var o=void 0,r={401:function(){e.Dialog.fail(void 0,"Cross-site Request Forgery attack detected. Please <a class='send-feedback'>let us know</a> about this issue so we can look into it.")},404:function(){e.Dialog.fail(!1,"Error 404: The requested endpoint ("+o.replace(/</g,"&lt;").replace(/\//g,"/<wbr>")+") could not be found")},500:function(){e.Dialog.fail(!1,'A request failed due to an internal server error. If this persists, please <a class="send-feedback">let us know</a>!')},503:function(){e.Dialog.fail(!1,'A request failed because the server is temporarily unavailable. This shouldn’t take too long, please try again in a few seconds.<br>If the problem still persist after a few minutes, please let us know by clicking the "Send feedback" link in the footer.')},504:function(){e.Dialog.fail(!1,'A request failed because the server took too long to respond. A refresh should fix this issue, but if it doesn\'t, please <a class="send-feedback">let us know</a>.')}};e.ajaxSetup({dataType:"json",error:function(t){"function"!=typeof r[t.status]&&$w.triggerHandler("ajaxerror",e.toArray(arguments))},beforeSend:function(e,t){o=t.url},statusCode:r});var i=void 0;e.copy=function(t,n){if(!document.queryCommandSupported("copy"))return prompt("Copy with Ctrl+C, close with Enter",t),!0;var o=e.mk("textarea"),r=!1;o.css({opacity:0,width:0,height:0,position:"fixed",left:"-10px",top:"50%",display:"block"}).text(t).appendTo("body").focus(),o.get(0).select();try{r=document.execCommand("copy")}catch(e){}setTimeout(function(){if(o.remove(),void 0===i||n){if(void 0===i&&(i=e.mk("span").attr({id:"copy-notify",class:r?void 0:"fail"}).html('<span class="typcn typcn-clipboard fa fa-clipboard"></span> <span class="typcn typcn-'+(r?"tick":"cancel")+" fa fa-"+(r?"check":"times")+'"></span>').appendTo($body)),n){var t=i.outerWidth(),a=i.outerHeight(),s=n.clientY-a/2;return i.stop().css({top:s,left:n.clientX-t/2,bottom:"initial",right:"initial",opacity:1}).animate({top:s-20,opacity:0},1e3,function(){e(this).remove(),i=void 0})}i.fadeTo("fast",1)}else i.stop().css("opacity",1);i.delay(r?300:1e3).fadeTo("fast",0,function(){e(this).remove(),i=void 0})},1)},e.compareFaL=function(e,t){return JSON.stringify(e)===JSON.stringify(t)},e.hex2rgb=function(e){return{r:parseInt(e.substring(1,3),16),g:parseInt(e.substring(3,5),16),b:parseInt(e.substring(5,7),16)}},e.rgb2hex=function(e){return"#"+(16777216+(parseInt(e.r,10)<<16)+(parseInt(e.g,10)<<8)+parseInt(e.b,10)).toString(16).toUpperCase().substring(1)},"function"!=typeof e.expr[":"].valid&&(e.expr[":"].valid=function(t){return"object"===_typeof(t.validity)?t.validity.valid:function(t){var n=e(t),o=n.attr("pattern"),r=n.hasAttr("required"),i=n.val();return!(r&&("string"!=typeof i||!i.length))&&(!o||new RegExp(o).test(i))}(t)}),e.roundTo=function(e){var t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:0;0===t&&console.warn("$.roundTo called with precision 0; you might as well use Math.round");var n=Math.pow(10,t);return Math.round(e*n)/n},e.rangeLimit=function(e,t){var n=void 0,o=void 0;switch(arguments.length-2){case 1:n=0,o=arguments[2];break;case 2:n=arguments[2],o=arguments[3];break;default:throw new Error("Invalid number of parameters for $.rangeLimit")}return t&&(e>o?e=n:e<n&&(e=o)),Math.min(o,Math.max(n,e))},e.fn.select=function(){var e=document.createRange();e.selectNodeContents(this.get(0));var t=window.getSelection();t.removeAllRanges(),t.addRange(e)};var a=/^#?([A-Fa-f0-9]{3})$/;window.SHORT_HEX_COLOR_PATTERN=a,e.hexpand=function(e){var t=e.trim().match(a);return t?"#"+(t=t[1])[0]+t[0]+t[1]+t[1]+t[2]+t[2]:e.replace(/^#?/,"#")},e.yiq=function(t){if("string"!=typeof t)throw new Error("Invalid hex value ("+t+")");var n=e.hex2rgb(t);return(299*n.r+587*n.g+114*n.b)/1e3},e.momentToYMD=function(e){return e.format("YYYY-MM-DD")},e.momentToHM=function(e){return e.format("HH:mm")},e.mkMoment=function(e,t,n){return moment(e+"T"+t+(n?"Z":""))},e.escapeRegex=function(e){return e.replace(/[-\/\\^$*+?.()|[\]{}]/g,"\\$&")},e.fn.toggleHtml=function(t){return this.html(t[e.rangeLimit(t.indexOf(this.html())+1,!0,t.length-1)])},e.fn.moveAttr=function(t,n){return this.each(function(){var o=e(this),r=o.attr(t);void 0!==r&&o.removeAttr(t).attr(n,r)})},e.fn.backgroundImageUrl=function(e){return this.css("background-image",'url("'+e.replace(/"/g,"%22")+'")')},e.attributifyRegex=function(e){return"string"==typeof e?e:e.toString().replace(/(^\/|\/[img]*$)/g,"")},e.fn.patternAttr=function(t){if(void 0===t)throw new Error("$.fn.patternAttr: regex is undefined");return this.attr("pattern",e.attributifyRegex(t))},e.fn.enable=function(){return this.attr("disabled",!1)},e.fn.disable=function(){return this.attr("disabled",!0)},e.fn.hasAttr=function(e){return this.get(0).hasAttribute(e)},e.fn.isOverflowing=function(){var e=this.get(0),t=e.style.overflow;t&&"visible"!==t||(e.style.overflow="hidden");var n=e.clientWidth<e.scrollWidth||e.clientHeight<e.scrollHeight;return e.style.overflow=t,n},e.scrollTo=function(t,n,o){var r=function(){return!1};e("html,body").on("mousewheel scroll",r).animate({scrollTop:t},n,o).off("mousewheel scroll",r),$w.on("beforeunload",function(){e("html,body").stop().off("mousewheel scroll",r)})},e.getAceEditor=function(t,n,o){var r=function(){e.Dialog.clearNotice(),o("ace/mode/"+n)};void 0===window.ace?(e.Dialog.wait(t,"Loading Ace Editor"),e.getScript("/js/min/ace/ace.js",function(){window.ace.config.set("basePath","/js/min/ace"),r()}).fail(function(){return e.Dialog.fail(!1,"Failed to load Ace Editor")})):r()},e.aceInit=function(e){e.$blockScrolling=1/0,e.setShowPrintMargin(!1);var t=e.getSession();return t.setUseSoftTabs(!1),t.setOption("indentedSoftWrap",!1),t.setOption("useWorker",!0),t.on("changeAnnotation",function(){for(var e=t.getAnnotations()||[],n=0,o=e.length,r=!1;n<o;)/doctype first\. Expected/.test(e[n].text)?(e.splice(n,1),o--,r=!0):n++;r&&t.setAnnotations(e)}),t},e.isInViewport=function(e){var t=void 0;try{t=e.getBoundingClientRect()}catch(e){return!0}return t.bottom>0&&t.right>0&&t.left<(window.innerWidth||document.documentElement.clientWidth)&&t.top<(window.innerHeight||document.documentElement.clientHeight)},e.fn.isInViewport=function(){return!!this[0]&&e.isInViewport(this[0])},e.loadImages=function(t){var n=e(t);return new Promise(function(e){n.find("img").length?n.find("img").on("load error",function(t){e(n,t)}):e(n)})},e.isRunningStandalone=function(){return window.matchMedia("(display-mode: standalone)").matches},window.URL=function(t){var n=document.createElement("a"),o={};return n.href=t,e.each(["hash","host","hostname","href","origin","pathname","port","protocol","search"],function(e,t){o[t]=n[t]}),o.pathString=o.pathname.replace(/^([^\/].*)$/,"/$1")+o.search+o.hash,o},window.sidebarForcedVisible=function(){return Math.max(document.documentElement.clientWidth,window.innerWidth||0)>=1200},window.withinMobileBreakpoint=function(){return Math.max(document.documentElement.clientWidth,window.innerWidth||0)<=650}}}(jQuery);
//# sourceMappingURL=/js/min/shared-utils.js.map
