"use strict";function _possibleConstructorReturn(e,t){if(!e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return!t||"object"!=typeof t&&"function"!=typeof t?e:t}function _inherits(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}var _typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol?"symbol":typeof e},_createClass=function(){function e(e,t){for(var o=0;o<t.length;o++){var n=t[o];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,o,n){return o&&e(t.prototype,o),n&&e(t,n),t}}();!function(e){var t=this;if("undefined"==typeof e.Navigation||e.Navigation.firstLoadDone!==!0){"function"!=typeof window.console.log&&(window.console.log=function(){}),"function"!=typeof window.console.group&&(window.console.group=function(){}),"function"!=typeof window.console.groupEnd&&(window.console.groupEnd=function(){}),"function"!=typeof window.console.clear&&(window.console.clear=function(){}),window.mk=function(){return document.createElement.apply(document,arguments)},e.mk=function(t,o){var n=e(document.createElement.call(document,t));return"string"==typeof o&&n.attr("id",o),n};var o=function(){function e(){_classCallCheck(this,e),this.emulatedStorage={}}return _createClass(e,[{key:"getItem",value:function(e){return"undefined"==typeof this.emulatedStorage[e]?null:this.emulatedStorage[e]}},{key:"setItem",value:function(e,t){this.emulatedStorage[e]="string"==typeof t?t:""+t}},{key:"removeItem",value:function(e){delete this.emulatedStorage[e]}}]),e}(),n=function(){function e(t){_classCallCheck(this,e);var n=t+"Storage";try{this.store=window[t+"Storage"]}catch(i){console.error(n+" is unavailable, falling back to EmulatedStorage"),this.store=new o}}return _createClass(e,[{key:"get",value:function(e){var t=null;try{t=this.store.getItem(e)}catch(o){}return t}},{key:"set",value:function(e,t){try{this.store.setItem(e,t)}catch(o){}}},{key:"remove",value:function(e){try{this.store.removeItem(e)}catch(t){}}}]),e}();e.LocalStorage=new n("local"),e.SessionStorage=new n("session"),e.toAbsoluteURL=function(e){var t=mk("a");return t.href=e,t.href},window.$w=e(window),window.$d=e(document),window.CommonElements=function(){window.$header=e("header"),window.$sbToggle=e(".sidebar-toggle"),window.$main=e("#main"),window.$content=e("#content"),window.$sidebar=e("#sidebar"),window.$footer=e("footer"),window.$body=e("body"),window.$head=e("head"),window.$navbar=$header.find("nav")},window.CommonElements(),window.Key={Enter:13,Space:32,LeftArrow:37,RightArrow:39,Tab:9,Comma:188},e.isKey=function(e,t){return t.keyCode===e},function(e){var t={order:"Do MMMM YYYY, H:mm:ss"};t.orderwd="dddd, "+t.order;var o=function(e){function t(e,o){_classCallCheck(this,t);var n=_possibleConstructorReturn(this,Object.getPrototypeOf(t).call(this,e));return n.name="DateFormatError",n.element=o,n}return _inherits(t,e),t}(Error),n=function(){function n(){_classCallCheck(this,n)}return _createClass(n,null,[{key:"Update",value:function(){e("time[datetime]:not(.nodt)").addClass("dynt").each(function(){var i=e(this),a=i.attr("datetime");if("string"!=typeof a)throw new TypeError('Invalid date data type: "'+("undefined"==typeof a?"undefined":_typeof(a))+'"');var r=moment(a);if(!r.isValid())throw new o('Invalid date format: "'+a+'"',this);var s=moment(),c=!i.attr("data-noweekday"),l=r.from(s),d=i.parent().children(".dynt-el"),u=i.data("dyntime-beforeupdate");if("function"==typeof u){var f=u(n.Difference(s.toDate(),r.toDate()));if(f===!1)return}d.length>0||i.hasClass("no-dynt-el")?(i.html(r.format(c?t.orderwd:t.order)),d.html(l)):i.attr("title",r.format(t.order)).html(l)})}},{key:"Difference",value:function(e,t){var o=(e.getTime()-t.getTime())/1e3,n={past:o>0,time:Math.abs(o),target:t},i=n.time;return n.day=Math.floor(i/this.InSeconds.day),i-=n.day*this.InSeconds.day,n.hour=Math.floor(i/this.InSeconds.hour),i-=n.hour*this.InSeconds.hour,n.minute=Math.floor(i/this.InSeconds.minute),i-=n.minute*this.InSeconds.minute,n.second=Math.floor(i),n.day>=7&&(n.week=Math.floor(n.day/7),n.day-=7*n.week),n.week>=4&&(n.month=Math.floor(n.week/4),n.week-=4*n.month),n.month>=12&&(n.year=Math.floor(n.month/12),n.month-=12*n.year),n}}]),n}();n.InSeconds={year:31557600,month:2592e3,week:604800,day:86400,hour:3600,minute:60},window.Time=n,n.Update(),setInterval(n.Update,1e4)}(jQuery),e.capitalize=function(e,t){return t?e.replace(/((?:^|\s)[a-z])/g,function(e){return e.toUpperCase()}):1===e.length?e.toUpperCase():e[0].toUpperCase()+e.substring(1)},"function"!=typeof Array.prototype.includes&&(Array.prototype.includes=function(e){return t.indexOf(e)!==-1}),e.pad=function(t,o,n,i){for("string"!=typeof t&&(t=""+t),"string"!=typeof o&&(o="0"),n="number"!=typeof n&&!isFinite(n)&&isNaN(n)?2:parseInt(n,10),"boolean"!=typeof i&&(i=!0);t.length<n;)t=i===e.pad.left?o+t:t+o;return t},e.pad.right=!(e.pad.left=!0),e.scaleResize=function(e,t,o){var n=void 0,i={scale:o.scale,width:o.width,height:o.height};if(isNaN(i.scale))if(isNaN(i.width))n=i.height/t,i.width=Math.round(e*n),i.scale=n;else{if(!isNaN(i.height))throw new Error("[scalaresize] Invalid arguments");n=i.width/e,i.height=Math.round(t*n),i.scale=n}else i.height=Math.round(t*i.scale),i.width=Math.round(e*i.scale);return i},e.clearSelection=function(){if(window.getSelection){var e=window.getSelection();e.empty?e.empty():e.removeAllRanges&&e.removeAllRanges()}else document.selection&&document.selection.empty()},e.toArray=function(e){var t=arguments.length<=1||void 0===arguments[1]?0:arguments[1];return[].slice.call(e,t)},$w.on("ajaxerror",function(){var t="";if(arguments.length>1){var o=e.toArray(arguments,1);if("abort"===o[1])return;t=" Details:<pre><code>"+o.slice(1).join("\n").replace(/</g,"&lt;")+"</code></pre>Response body:";var n=/^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;n.test(o[0].responseText)?t+='<div class="reset">'+o[0].responseText.replace(n,"$1")+"</div>":"string"==typeof o[0].responseText&&(t+="<pre><code>"+o[0].responseText.replace(/</g,"&lt;")+"</code></pre>")}e.Dialog.fail(!1,"There was an error while processing your request."+t)}),e.mkAjaxHandler=function(e){return function(t){return"object"!==("undefined"==typeof t?"undefined":_typeof(t))?(console.log(t),void $w.triggerHandler("ajaxerror")):void("function"==typeof e&&e.call(t))}},e.callCallback=function(t,o,n){return"object"===("undefined"==typeof o?"undefined":_typeof(o))&&e.isArray(o)||(n=o,o=[]),"function"!=typeof t?n:t.apply(window,o)},e.fn.mkData=function(t){var o=e(this).serializeArray(),n={};return e.each(o,function(e,t){n[t.name]=t.value}),"object"===("undefined"==typeof t?"undefined":_typeof(t))&&e.extend(n,t),n},e.getCSRFToken=function(){var e=document.cookie.match(/CSRF_TOKEN=([a-z\d]+)/i);if(e&&e.length)return e[1];throw new Error("Missing CSRF_TOKEN")},e.ajaxPrefilter(function(t,o){if("POST"===(o.type||t.type).toUpperCase()){var n=e.getCSRFToken();if("undefined"==typeof t.data&&(t.data=""),"string"==typeof t.data){var i=t.data.length>0?t.data.split("&"):[];i.push("CSRF_TOKEN="+n),t.data=i.join("&")}else t.data.CSRF_TOKEN=n}});var i=void 0,a={401:function(){e.Dialog.fail(void 0,"Cross-site Request Forgery attack detected. Please notify the site administartors.")},404:function(){e.Dialog.fail(!1,"Error 404: The requested endpoint ("+i.replace(/</g,"&lt;").replace(/\//g,"/<wbr>")+") could not be found")},500:function(){e.Dialog.fail(!1,'The request failed due to an internal server error. If this persists, please <a class="send-feedback">let us know</a>!')},503:function(){e.Dialog.fail(!1,'The request failed because the server is temporarily unavailable. This whouldn\'t take too long, please try again in a few seconds.<br>If the problem still persist after a few minutes, please let us know by clicking the "Send feedback" link in the footer.')}};e.ajaxSetup({dataType:"json",error:function(t){"function"!=typeof a[t.status]&&$w.triggerHandler("ajaxerror",e.toArray(arguments)),$body.removeClass("loading")},beforeSend:function(e,t){return i=t.url},statusCode:a});var r=void 0;e.copy=function(t,o){if(!document.queryCommandSupported("copy"))return prompt("Copy with Ctrl+C, close with Enter",t),!0;var n=e.mk("textarea"),i=!1;n.css({opacity:0,width:0,height:0,position:"fixed",left:"-10px",top:"50%",display:"block"}).text(t).appendTo("body").focus(),n.get(0).select();try{i=document.execCommand("copy")}catch(a){}setTimeout(function(){if(n.remove(),"undefined"==typeof r||o){if("undefined"==typeof r&&(r=e.mk("span").attr({id:"copy-notify","class":i?void 0:"fail"}).html('<span class="typcn typcn-clipboard"></span> <span class="typcn typcn-'+(i?"tick":"cancel")+'"></span>').appendTo($body)),o){var t=r.outerWidth(),a=r.outerHeight(),s=o.clientY-a/2;return r.stop().css({top:s,left:o.clientX-t/2,bottom:"initial",right:"initial",opacity:1}).animate({top:s-20,opacity:0},1e3,function(){e(this).remove(),r=void 0})}r.fadeTo("fast",1)}else r.stop().css("opacity",1);r.delay(i?300:1e3).fadeTo("fast",0,function(){e(this).remove(),r=void 0})},1)},e.hex2rgb=function(e){return{r:parseInt(e.substring(1,3),16),g:parseInt(e.substring(3,5),16),b:parseInt(e.substring(5,7),16)}},e.rgb2hex=function(e){return"#"+(16777216+(parseInt(e.r,10)<<16)+(parseInt(e.g,10)<<8)+parseInt(e.b,10)).toString(16).toUpperCase().substring(1)},"function"!=typeof e.expr[":"].valid&&(e.expr[":"].valid=function(t){return"object"===_typeof(t.validity)?t.validity.valid:function(t){var o=e(t),n=o.attr("pattern"),i=o.hasAttr("required"),a=o.val();return!(i&&("string"!=typeof a||!a.length))&&(!n||new RegExp(n).test(a))}(t)}),e.roundTo=function(e,t){var o=Math.pow(10,t);return Math.round(e*o)/o},e.rangeLimit=function(e,t){var o=void 0,n=void 0,i=2;switch(arguments.length-i){case 1:o=0,n=arguments[i];break;case 2:o=arguments[i],n=arguments[i+1];break;default:throw new Error("Invalid number of parameters for $.rangeLimit")}return t&&(e>n?e=o:e<o&&(e=n)),Math.min(n,Math.max(o,e))},e.fn.select=function(){var e=document.createRange();e.selectNodeContents(this.get(0));var t=window.getSelection();t.removeAllRanges(),t.addRange(e)};var s=/^#?([A-Fa-f0-9]{3})$/;window.SHORT_HEX_COLOR_PATTERN=s,e.hexpand=function(e){var t=e.trim().match(s);return t?(t=t[1],"#"+t[0]+t[0]+t[1]+t[1]+t[2]+t[2]):e.replace(/^#?/,"#")},e.yiq=function(t){var o=e.hex2rgb(t);return(299*o.r+587*o.g+114*o.b)/1e3},e.fn.toggleHtml=function(t){return this.html(t[e.rangeLimit(t.indexOf(this.html())+1,!0,t.length-1)])},e.fn.moveAttr=function(t,o){return this.each(function(){var n=e(this),i=n.attr(t);"undefined"!=typeof i&&n.removeAttr(t).attr(o,i)})},e.fn.backgroundImageUrl=function(e){return this.css("background-image",'url("'+e.replace(/"/g,"%22")+'")')},e.attributifyRegex=function(e){return e.toString().replace(/(^\/|\/[img]*$)/g,"")},e.fn.patternAttr=function(t){return this.attr("pattern",e.attributifyRegex(t))},e.fn.enable=function(){return this.attr("disabled",!1)},e.fn.disable=function(){return this.attr("disabled",!0)},e.fn.hasAttr=function(e){return this.get(0).hasAttribute(e)},e.scrollTo=function(t,o,n){var i=function(){return!1};e("html,body").on("mousewheel scroll",i).animate({scrollTop:t},o,n).off("mousewheel scroll",i),$w.on("beforeunload",function(){e("html,body").stop().off("mousewheel scroll",i)})},e.getAceEditor=function(t,o,n){var i=function(){return e.Dialog.fail(!1,"Failed to load Ace Editor")},a=function(){e.Dialog.clearNotice(),n("ace/mode/"+o)};"undefined"==typeof window.ace?(e.Dialog.wait(t,"Loading Ace Editor"),e.getScript("/js/min/ace/ace.js",function(){window.ace.config.set("basePath","/js/min/ace"),a()}).fail(i)):a()},e.aceInit=function(e){e.$blockScrolling=1/0,e.setShowPrintMargin(!1);var t=e.getSession();return t.setUseSoftTabs(!1),t.setOption("indentedSoftWrap",!1),t.setOption("useWorker",!0),t.on("changeAnnotation",function(){for(var e=t.getAnnotations()||[],o=0,n=e.length,i=!1;o<n;)/doctype first\. Expected/.test(e[o].text)?(e.splice(o,1),n--,i=!0):o++;i&&t.setAnnotations(e)}),t},window.URL=function(t){var o=document.createElement("a"),n={};return o.href=t,e.each(["hash","host","hostname","href","origin","pathname","port","protocol","search"],function(e,t){n[t]=o[t]}),n.pathString=n.pathname.replace(/^([^\/].*)$/,"/$1")+n.search+n.hash,n},window.OpenSidebarByDefault=function(){return Math.max(document.documentElement.clientWidth,window.innerWidth||0)>=1200};var c=function(e,t){var o="undefined"!=typeof window.screenLeft?window.screenLeft:screen.left,n="undefined"!=typeof window.screenTop?window.screenTop:screen.top,i=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:screen.width,a=window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:screen.height,r=i/2-e/2+o,s=a/2-t/2+n;return{top:s,left:r}};e.PopupOpenCenter=function(e,t,o,n){var i=c(o,n),a=window.open(e,t,"scrollbars=yes,width="+o+",height="+n+",top="+i.top+",left="+i.left);return window.focus&&a.focus(),a},e.PopupMoveCenter=function(e,t,o){var n=c(t,o);e.resizeTo(t,o),e.moveTo(n.left,n.top)};var l=window.OAUTH_URL,d=function(){return(~~(99999999*Math.random())).toString(36)};$d.on("click","#turbo-sign-in",function(t){t.preventDefault();var o=e(this),n=o.parent().html();o.disable(),l=o.attr("data-url");var i=d(),a=!1,r=void 0,s=void 0;window[" "+i]=function(){a=!0,"request"===e.Dialog._open.type?e.Dialog.clearNotice(/Redirecting you to DeviantArt/):e.Dialog.close(),s.close()};try{s=window.open(l+"&state="+i)}catch(c){return e.Dialog.fail(!1,"Could not open login pop-up. Please open another page")}e.Dialog.wait(!1,"Redirecting you to DeviantArt"),r=setInterval(function(){try{if(!s||s.closed){if(clearInterval(r),a)return;e.Dialog.fail(!1,n)}}catch(t){}},500)});var u=function(){console.log("> DocReadyAlwaysRun()"),$d.triggerHandler("paginate-refresh"),e.LocalStorage.remove("cookie_consent");var t=e.LocalStorage.get("cookie_consent_v2");l=window.OAUTH_URL,e("#signin").off("click").on("click",function(){var o=e(this),n=function(t){if(t){e.Dialog.close(),e.LocalStorage.set("cookie_consent_v2",1),o.disable();var n=function(){e.Dialog.wait(!1,"Redirecting you to DeviantArt"),location.href=l+"&state="+encodeURIComponent(location.href.replace(location.origin,""))};if(navigator.userAgent.indexOf("Trident")!==-1)return n();e.Dialog.wait("Sign-in process","Opening popup window");var i=d(),a=!1,r=void 0,s=void 0;window[" "+i]=function(t){a=!0,clearInterval(r),e.Dialog.wait(!1,"Reloading page"),e.Navigation.reload(function(){t&&window.ga("set","userId",t),e.Dialog.close(),s.close()})};try{s=e.PopupOpenCenter(l+"&state="+i,"login","450","580")}catch(c){}var u=function(){if(!a){if(document.cookie.indexOf("auth=")!==-1)return window[" "+i];e.Dialog.fail(!1,"Popup-based login unsuccessful"),n()}};r=setInterval(function(){try{s&&!s.closed||(clearInterval(r),u())}catch(e){}},500),$w.on("beforeunload",function(){a=!0,s.close()}),e.Dialog.wait(!1,"Waiting for you to sign in")}};t?n(!0):e.Dialog.confirm("Privacy Notice",'<p>Dear User,</p><p>We must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.</p><p>If you would like to avoid these completly harmless pieces of text which are required to use this website, click "Decline" and continue browsing as a guest.</p><p><em>This warning will not appear again if you accept our use of persistent cookies.</em></p>\n<strong>Update (<time datetime="2016-07-25T11:20Z"></time>)</strong><p>In addition to persistent cookies, we use Google Analytics to track website traffic. Recently Google has made available a feature which allows us to tie this data to specific users which helps in debugging certain issues that may arise.</p><p>If you do not wish your activity to be tied to your user ID you\'ll be able to turn this off by clicking the "Account" menu item and un-ticking the approperiate check box in the "Settings" section after you\'ve logged in, but keep in mind that if you experience issues, tracking down the cause will be harder for me to do so.</p><p>I\'d like to take this opportunity to mention <a href="https://www.ublock.org/" target="_blank">uBlock</a>, a great extension that\'ll prevent not just us but many other sites from tracking your activity. This is not sponsored or anything, I just thought I\'d let you know.</p><p>Sincerely,<br>The developer</p>',["Accept","Decline"],n)}),e("#signout").off("click").on("click",function(){var t="Sign out";e.Dialog.confirm(t,"Are you sure you want to sign out?",function(o){o&&(e.Dialog.wait(t,"Signing out"),e.post("/signout",e.mkAjaxHandler(function(){return this.status?void e.Navigation.reload(function(){e.Dialog.close()}):e.Dialog.fail(t,this.message)})))})});try{if(/^https/.test(location.protocol))throw void 0;var o=e.SessionStorage.get("canhttps");if("false"===o)throw void 0;e.ajax({method:"POST",url:"https://"+location.host+"/ping",success:e.mkAjaxHandler(function(){this.status&&$sidebar.append(e.mk("a").attr({"class":"btn green typcn typcn-lock-closed",href:location.href.replace(/^http:/,"https:")}).text("Switch to HTTPS")),e.SessionStorage.set("canhttps",o=this.status.toString())}),error:function(){e.SessionStorage.set("canhttps",o="false")}})}catch(n){}},f=function(){function t(){_classCallCheck(this,t),this._DocReadyHandlers=[],this._xhr=!1,this._lastLoadedPathname=window.location.pathname,this.firstLoadDone=!1}return _createClass(t,[{key:"docReady",value:function(){console.log("> _docReady()"),u();for(var e=0,t=this._DocReadyHandlers.length;e<t;e++)this._DocReadyHandlers[e].call(window),console.log("> DocReadyHandlers[%d]()",e)}},{key:"flushDocReady",value:function(){for(var e=0,t=this._DocReadyHandlers.length;e<t;e++)"function"==typeof this._DocReadyHandlers[e].flush&&(this._DocReadyHandlers[e].flush(),console.log("Flushed DocReady handler #%d",e));this._DocReadyHandlers=[]}},{key:"_loadCSS",value:function(t,o,n){if(!t.length)return e.callCallback(n);console.group("Loading CSS");var i=this;!function a(r){if(r>=t.length)return console.groupEnd(),e.callCallback(n);var s=t[r];i=e.ajax({url:s,dataType:"text",success:function(t){t=t.replace(/url\((['"])?(?:\.\.\/)+/g,"url($1/"),$head.append(e.mk("style").attr("href",s).text(t)),console.log("%c#%d (%s)","color:green",r,s)},error:function(){console.log("%c#%d (%s)","color:red",r,s)},complete:function(){o(),a(r+1)}})}(0)}},{key:"_loadJS",value:function(t,o,n){if(!t.length)return e.callCallback(n);console.group("Loading JS");var i=this;!function a(r){if(r>=t.length)return console.groupEnd(),e.callCallback(n);var s=t[r];i._xhr=e.ajax({url:s,dataType:"text",success:function(t){$body.append(e.mk("script").attr("data-src",s).text(t)),console.log("%c#%d (%s)","color:green",r,s)},error:function(){console.log("%c#%d (%s)","color:red",r,s)},complete:function(){o(),a(r+1)}})}(0)}},{key:"visit",value:function(t,o,n){console.clear(),console.group("[AJAX-Nav] PING %s (block_reload: %s)",t,n);var i=this;if(i._xhr!==!1){try{i._xhr.abort(),console.log("Previous AJAX request aborted")}catch(a){}i._xhr=!1}$body.addClass("loading");var r=$header.children(".loader");0===r.length&&(r=e.mk("div").attr("class","loader").appendTo($header)),r.css("width","0").addClass("loading");var s=e.ajax({url:t,data:{"via-js":!0},success:e.mkAjaxHandler(function(){if(i._xhr!==s)return console.log("%cAJAX request objects do not match, bail","color:red"),void console.groupEnd();if(!this.status)return $body.removeClass("loading"),i._xhr=!1,console.log("%cNavigation error %s","color:red",this.message),console.groupEnd(),e.Dialog.fail("Navigation error",this.message);r.css("width","20%"),t=new URL(this.responseURL).pathString+new URL(t).hash,$w.triggerHandler("unload"),window.OpenSidebarByDefault()||$sbToggle.trigger("sb-close");var a=this.css,c=this.js,l=this.content,d=this.sidebar,u=this.footer,f=this.title,h=this.avatar,p=this.signedIn;$main.empty();var g=!1,m=new URL(location.href),w=!n&&m.pathString===t;if(i.flushDocReady(),console.groupCollapsed("Checking JS files to skip..."),$body.children("script[src], script[data-src]").each(function(){var t=e(this),o=t.attr("src")||t.attr("data-src");if(w)return/min\/dialog\.js/.test(o)||t.remove(),!0;var n=c.indexOf(o);if(n===-1||/min\/(colorguide[\.\-]|episodes-manage|moment-timezone|episode)/.test(o)){if(o.includes("global"))return!(g=!0);t.remove()}else c.splice(n,1),console.log("%cSkipped %s","color:saddlebrown",o)}),console.log("%cFinished.","color:green"),console.groupEnd(),g!==!1)return console.log("%cFull page reload forced by changes in global.js","font-weight:bold;color:orange"),console.groupEnd(),location.href=t;console.groupCollapsed("Checking CSS files to skip...");var v="link[href], style[href]";$head.children(v).each(function(){var t=e(this),o=t.attr("href"),n=a.indexOf(o);n!==-1?(a.splice(n,1),console.log("%cSkipped %s","color:saddlebrown",o)):t.attr("data-remove","true")}),console.log("%cFinished.","color:green"),console.groupEnd(),console.groupEnd(),console.group("[AJAX-Nav] GET %s",t);var y=0,b=a.length+c.length;$w.trigger("beforeunload"),i._loadCSS(a,function(){y++,r.css("width",e.roundTo(100*(y/b),2)+"%")},function(){$head.children(v.replace(/href/g,"data-remove=true")).remove(),$main.addClass("pls-wait").html(l),$sidebar.html(d),$footer.html(u),Time.Update(),window.setUpcomingEpisodeCountdown();var a=$header.find("nav").children();a.children().first().children("img").attr("src",h),a.children(":not(:first-child)").remove(),a.append($sidebar.find("nav").children().children().clone()),window.CommonElements(),n||history[m.pathString===t?"replaceState":"pushState"]({"via-js":!0},"",t),document.title=f,i._lastLoadedPathname=window.location.pathname,i._loadJS(c,function(){y++,r.css("width",e.roundTo(100*(y/b),2)+"%")},function(){i.docReady(),console.log("%cDocument ready","color:green"),console.groupEnd(),$body.removeClass("loading"),$main.removeClass("pls-wait"),window.WSNotifications(p),e.callCallback(o),setTimeout(function(){i._xhr===!1&&r.removeClass("loading")},200),i._xhr=!1})})})});i._xhr=s}},{key:"reload",value:function(e){this.visit(location.pathname+location.search+location.hash,e)}}]),t}();e.Navigation=new f,window.DocReady={push:function(t,o){"function"==typeof o&&(t.flush=o),e.Navigation._DocReadyHandlers.push(t)}}}}(jQuery),$(function(){$.Navigation.firstLoadDone||($.Navigation.firstLoadDone=!0,console.log("[HTTP-Nav] > $(document).ready()"),console.group("[HTTP-Nav] GET "+window.location.pathname+window.location.search+window.location.hash),window.ServiceUnavailableError!==!0&&$.get("/footer-git",$.mkAjaxHandler(function(){this.footer&&$footer.prepend(this.footer)})),function(){var e=function(){setTimeout(function(){$w.trigger("resize")},510)};$sbToggle.off("click sb-open sb-close").on("click",function(e){e.preventDefault(),$sbToggle.trigger("sb-"+($body.hasClass("sidebar-open")?"close":"open"))}).on("sb-open sb-close",function(t){var o="close"===t.type.substring(3);$body[o?"removeClass":"addClass"]("sidebar-open");try{$.LocalStorage[o?"set":"remove"]("sidebar-closed","true")}catch(n){}e()});var t=void 0;try{t=$.LocalStorage.get("sidebar-closed")}catch(o){}"true"!==t&&window.OpenSidebarByDefault()&&($body.addClass("sidebar-open"),e())}(),function(){var e=void 0,t=void 0,o=function(){"undefined"!=typeof t&&(clearInterval(t),t=void 0)},n=function(){var t="function"==typeof e.parent&&e.parent().length>0,n={},i=void 0,a=void 0;if(t&&(i=new Date,a=new Date(e.attr("datetime")),n=Time.Difference(i,a)),!t||n.past)return t&&e.parents("li").remove(),o(),window.setUpcomingEpisodeCountdown();var r=void 0;n.time<Time.InSeconds.month?(n.week>0&&(n.day+=7*n.week),r="in ",n.day>0&&(r+=n.day+" day"+(1!==n.day?"s":"")+" & "),n.hour>0&&(r+=n.hour+":"),r+=$.pad(n.minute)+":"+$.pad(n.second)):r=moment(a).from(i),e.text(r)};window.setUpcomingEpisodeCountdown=function(){var i=$("#upcoming");if(i.length){var a=i.children("ul").children();if(!a.length)return i.remove();e=a.first().find("time").addClass("nodt"),o(),t=setInterval(n,1e3),n(),i.find("li").each(function(){var e=$(this),t=e.children(".calendar"),o=moment(e.find(".countdown").data("airs")||e.find("time").attr("datetime"));t.children(".top").text(o.format("MMM")),t.children(".bottom").text(o.format("D"))}),Time.Update()}},window.setUpcomingEpisodeCountdown()}(),$(document).off("click",".send-feedback").on("click",".send-feedback",function(e){e.preventDefault(),e.stopPropagation(),$("#ctxmenu").hide(),$.Dialog.info($.Dialog.isOpen()?void 0:"Send feedback","<h3>How to send feedback</h3>\n\t\t\t<p>If you're having an issue with the site and would like to let us know or have an idea/feature request you'd like to share, here's how:</p>\n\t\t\t<ul>\n\t\t\t\t<li><a href='https://discord.gg/0vv70fepSINi2Hy8'>Join our Discord server</a> and describe your issue in the <strong>#support</strong> channel</li>\n\t\t\t\t<li><a href='http://mlp-vectorclub.deviantart.com/notes/'>Send a note </a>to the group on DeviantArt</li>\n\t\t\t\t<li><a href='mailto:seinopsys@gmail.com'>Send an e-mail</a> to seinopsys@gmail.com</li>\n\t\t\t\t<li>If you have a GitHub account, you can also  <a href=\""+$footer.find("a.issues").attr("href")+"\">create an issue</a> on the project's GitHub page.\n\t\t\t</ul>")}),$(document).off("click",".action--color-avg").on("click",".action--color-avg",function(e){e.preventDefault(),e.stopPropagation();var t="Colour Average Calculator",o=function(){$.Dialog.close(),$.Dialog.request(t,window.$ColorAvgFormTemplate.clone(!0,!0),!1,function(e){e.triggerHandler("added")})};"undefined"==typeof window.$ColorAvgFormTemplate?!function(){$.Dialog.wait(t,"Loading form, please wait");var e="/js/min/global-color_avg_form.js";$.getScript(e,o).fail(function(){setTimeout(function(){$.Dialog.close(function(){$.Dialog.wait(t,"Loading script (attempt #2)"),$.getScript(e.replace(/min\./,""),o).fail(function(){$.Dialog.fail(t,"Form could not be loaded")})})},1)})}():o()}),$d.on("click","a[href]",function(e){if(e.which>2)return!0;var t=this;return t.host!==location.host||(t.pathname===location.pathname&&t.search===location.search?t.protocol!==location.protocol||(e.preventDefault(),window._trighashchange=t.hash!==location.hash,window._trighashchange===!0&&history.replaceState(history.state,"",t.href),setTimeout(function(){delete window._trighashchange},1),void $w.triggerHandler("hashchange")):!/^.*\/[^.]*$/.test(t.pathname)||(0!==$(this).parents("#dialogContent").length&&$.Dialog.close(),e.preventDefault(),void $.Navigation.visit(this.href)))}),$w.on("popstate",function(e){if("undefined"==typeof window._trighashchange){var t=e.originalEvent.state,o=function(e,t){return $.Navigation.visit(e,t,!0)};return null===t||t["via-js"]||t.paginate!==!0?void o(location.href):$w.trigger("nav-popstate",[t,o])}}),function(){function e(e){window.io&&e&&(r(),a.off("click",".mark-read").on("click",".mark-read",function(e){e.preventDefault();var t=$(this);if(!t.is(":disabled")){var o=t.attr("data-id"),n={read_action:t.attr("data-value")},i=function(){t.css("opacity",".5").disable(),$.post("/notifications/mark-read/"+o,n,$.mkAjaxHandler(function(){if(!this.status)return t.css("opacity","").enable(),$.Dialog.fail("Mark notification as read",this.message)}))};n.read_action?$.Dialog.confirm("Actionable notification",'Please confirm your choice: <strong class="color-'+t.attr("class").replace(/^.*variant-(\w+)\b.*$/,"$1")+'">'+t.attr("title")+"</strong>",["Confirm","Cancel"],function(e){e&&($.Dialog.close(),i())}):i()}}),t||(t=io("https://ws."+location.hostname+":8667/",{reconnectionDelay:5e3}),t.on("connect",function(){console.log("[WS] Connected")}),t.on("auth",o(function(e){console.log("[WS] Authenticated as "+e.name)})),t.on("notif-cnt",o(function(e){var t=e.cnt?parseInt(e.cnt,10):0;console.log("[WS] Got notification count (data.cnt=%d, cnt=%d)",e.cnt,t),r(),0===t?i.stop().slideUp("fast",function(){a.empty(),n.empty()}):$.post("/notifications/get",$.mkAjaxHandler(function(){n.text(t),a.html(this.list),Time.Update(),i.stop().slideDown()}))})),t.on("rip",function(){console.log("[WS] Authentication failed"),t.disconnect(0)}),t.on("disconnect",function(){console.log("[WS] Disconnected")})))}var t=void 0,o=function(e){return function(t){if("string"==typeof t)try{t=JSON.parse(t)}catch(o){}e(t)}},n=void 0,i=void 0,a=void 0,r=function(){n=$sbToggle.children(".notif-cnt"),0===n.length&&(n=$.mk("span").attr({"class":"notif-cnt",title:"New notifications"}).prependTo($sbToggle)),i=$sidebar.children(".notifications"),a=i.children(".notif-list")};e(window.signedIn),window.WSNotifications=function(t){e(t)}}(),$.Navigation.docReady(),console.log("%cDocument ready","color:green"),console.groupEnd())}),$w.on("load",function(){$body.removeClass("loading")});
//# sourceMappingURL=/js/min/global.js.map
