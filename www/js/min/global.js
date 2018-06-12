"use strict";!function(){var t=function(e){e.fluidbox({immediateOpen:!0,loader:!0}).on("openstart.fluidbox",function(){$body.addClass("fluidbox-open"),$(this).parents("#dialogContent").length&&$body.addClass("fluidbox-in-dialog")}).on("openend.fluidbox",function(){var e=$(this),o=e.attr("href");e.data("href",o),e.removeAttr("href"),0===e.find(".fluidbox__ghost").children().length&&e.find(".fluidbox__ghost").append($.mk("img").attr("src",o).css({opacity:0,width:"100%",height:"100%"}))}).on("closestart.fluidbox",function(){$body.removeClass("fluidbox-open");var e=$(this);e.attr("href",e.data("href")),e.removeData("href")}).on("closeend.fluidbox",function(){$body.removeClass("fluidbox-in-dialog")})};$.fn.fluidboxThis=function(e){var o=this;return"function"==typeof $.fn.fluidbox?(t(this),$.callCallback(e)):$.getScript("/js/min/jquery.ba-throttle-debounce.js",function(){$.getScript("/js/min/jquery.fluidbox.js",function(){t(o),$.callCallback(e)}).fail(function(){$.Dialog.fail(!1,"Loading Fluidbox plugin failed")})}).fail(function(){$.Dialog.fail(!1,"Loading Debounce/throttle plugin failed")}),this};var l,i,r,n,s=function(e,o){var t=void 0!==window.screenLeft?window.screenLeft:screen.left,i=void 0!==window.screenTop?window.screenTop:screen.top,n=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:screen.width;return{top:(window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:screen.height)/2-o/2+i,left:n/2-e/2+t}};$.PopupOpenCenter=function(e,o,t,i){var n=s(t,i),a=window.open(e,o,"scrollbars=yes,width="+t+",height="+i+",top="+n.top+",left="+n.left);return window.focus&&a.focus(),a},$.PopupMoveCenter=function(e,o,t){var i=s(o,t);e.resizeTo(o,t),e.moveTo(i.left,i.top)},$d.on("click","#turbo-sign-in",function(e){e.preventDefault();var o=$(this),t=o.parent().html();o.disable();var i=!1,n=void 0,a=void 0;window.__authCallback=function(){i=!0,"request"===$.Dialog._open.type?$.Dialog.clearNotice(/Redirecting you to DeviantArt/):$.Dialog.close(),a.close()};try{a=window.open("/da-auth/begin")}catch(e){return $.Dialog.fail(!1,"Could not open login pop-up. Please open another page")}$.Dialog.wait(!1,"Redirecting you to DeviantArt"),n=setInterval(function(){try{if(!a||a.closed){if(clearInterval(n),i)return;$.Dialog.fail(!1,t)}}catch(e){}},500)}),$.Navigation={visit:function(e){window.location.href=e},reload:function(){0<arguments.length&&void 0!==arguments[0]&&arguments[0]&&$.Dialog.wait(!1,"Reloading page",!0),window.location.reload()}},$sbToggle.off("click sb-open sb-close").on("click",function(e){e.preventDefault(),window.sidebarForcedVisible()||$sbToggle.trigger("sb-"+($body.hasClass("sidebar-open")?"close":"open"))}).on("sb-open sb-close",function(e){var o="close"===e.type.substring(3);$body[o?"removeClass":"addClass"]("sidebar-open");try{$.LocalStorage[o?"set":"remove"]("sidebar-closed","true")}catch(e){}setTimeout(function(){$w.trigger("resize")},510)}),i=l=void 0,r=function(){void 0!==i&&(clearInterval(i),i=void 0)},n=function e(){var o="function"==typeof l.parent&&0!==l.parent().length,t={},i=void 0,n=void 0;if(o&&(i=new Date,n=new Date(l.attr("datetime")),t=Time.Difference(i,n)),!o||t.past)return r(),void $.API.get("/about/upcoming",$.mkAjaxHandler(function(){if(!this.status)return console.error("Failed to load upcoming event list: "+this.message);var e=$("#upcoming");e.find("ul").html(this.html),this.html?e.removeClass("hidden"):e.addClass("hidden"),window.setUpcomingCountdown()}));var a=void 0;t.time<Time.InSeconds.month&&0===t.month?(0<t.week&&(t.day+=7*t.week),a="in ",0<t.day&&(a+=t.day+" day"+(1!==t.day?"s":"")+" & "),0<t.hour&&(a+=t.hour+":"),a+=$.pad(t.minute)+":"+$.pad(t.second)):(r(),setTimeout(e,1e4),a=moment(n).from(i)),l.text(a)},window.setUpcomingCountdown=function(){var e=$("#upcoming");if(e.length){var o=e.children("ul").children();if(!o.length)return e.addClass("hidden");e.removeClass("hidden"),l=o.first().find("time").addClass("nodt"),r(),i=setInterval(n,1e3),n(),e.find("li").each(function(){var e=$(this),o=e.children(".calendar"),t=moment(e.find(".countdown").data("airs")||e.find("time").attr("datetime"));o.children(".top").text(t.format("MMM")),o.children(".bottom").text(t.format("D"))}),Time.Update(),o.find(".title").simplemarquee({speed:25,cycles:1/0,space:25,handleHover:!1,delayBetweenCycles:0}).addClass("marquee")}},window.setUpcomingCountdown(),$(document).off("click",".send-feedback").on("click",".send-feedback",function(e){e.preventDefault(),e.stopPropagation(),$("#ctxmenu").hide();var o=["seinopsys","gmail.com"].join("@");$.Dialog.info($.Dialog.isOpen()?void 0:"Contact","<h3>How to contact us</h3>\n\t\t\t<p>You can use any of the following methods to reach out to us:</p>\n\t\t\t<ul>\n\t\t\t\t<li><a href='https://discord.gg/0vv70fepSILbdJOD'>Join our Discord server</a> and describe your issue/idea in the <strong>#support</strong> channel</li>\n\t\t\t\t<li><a href='http://mlp-vectorclub.deviantart.com/notes/'>Send a note </a>to the group on DeviantArt</li>\n\t\t\t\t<li><a href='mailto:"+o+"'>Send an e-mail</a> to "+o+"</li>\n\t\t\t</ul>")}),$(document).off("click",".action--color-avg").on("click",".action--color-avg",function(e){e.preventDefault(),e.stopPropagation();var o="Color Average Calculator",t=function(){$.Dialog.close();var e=window.$ColorAvgFormTemplate.clone(!0,!0);$.Dialog.request(o,e,!1,function(){e.triggerHandler("added")})};if(void 0===window.$ColorAvgFormTemplate){$.Dialog.wait(o,"Loading form, please wait");var i="/js/min/global-color_avg_form.js";$.getScript(i,t).fail(function(){setTimeout(function(){$.Dialog.close(function(){$.Dialog.wait(o,"Loading script (attempt #2)"),$.getScript(i.replace(/min\./,""),t).fail(function(){$.Dialog.fail(o,"Form could not be loaded")})})},1)})}else t()});var a=$("html");if($.isRunningStandalone()){var d=a.scrollTop(),e=function(){if(window.withinMobileBreakpoint()&&!a.is(":animated")){var e=a.scrollTop(),o=$header.outerHeight(),t=parseInt($header.css("top"),10);$header.css("top",d<e?Math.max(-o,t-(e-d)):Math.min(0,t+(d-e))),d=e}};$d.on("scroll",e),e()}var o=$("#to-the-top").on("click",function(e){e.preventDefault(),a.stop().animate({scrollTop:0},200),o.removeClass("show")});function c(){if(window.withinMobileBreakpoint()&&!a.is(":animated")){var e=0!==a.scrollTop();!e&&o.hasClass("show")?o.removeClass("show"):e&&!o.hasClass("show")&&o.addClass("show")}}$d.on("scroll",c),c(),$.LocalStorage.remove("cookie_consent_v2"),$("#signin").off("click").on("click",function(){var n=$(this);n.disable();var e=function(){$.Dialog.wait(!1,"Redirecting you to DeviantArt"),$.Navigation.visit("/da-auth/begin?return="+encodeURIComponent($.hrefToPath(location.href)))};if(-1!==navigator.userAgent.indexOf("Trident"))return e();$.Dialog.wait("Sign-in process","Opening popup window");var a=!1,l=void 0,r=void 0;window.__authCallback=function(e,o){if(clearInterval(l),!0!==e)a=!0,$.Dialog.success(!1,"Signed in successfully"),r.close(),$.Navigation.reload(!0);else{if(o.jQuery){var t=o.$("#content").children("h1").html(),i=o.$("#content").children(".notice").html();$.Dialog.fail(!1,'<p class="align-center"><strong>'+t+"</strong></p><p>"+i+"</p>"),r.close()}else $.Dialog.fail(!1,"Sign in failed, check popup for details.");n.enable()}};try{r=$.PopupOpenCenter("/da-auth/begin","login","450","580")}catch(e){}l=setInterval(function(){try{r&&!r.closed||(clearInterval(l),function(){if(!a){if(-1!==document.cookie.indexOf("auth="))return window.__authCallback;$.Dialog.fail(!1,"Popup-based login failed"),e()}}())}catch(e){}},500),$w.on("beforeunload",function(){a=!0,r.close()}),$.Dialog.wait(!1,"Waiting for you to sign in")}),$("#signout").off("click").on("click",function(){var o="Sign out";$.Dialog.confirm(o,"Are you sure you want to sign out?",function(e){e&&($.Dialog.wait(o,"Signing out"),$.API.post("/da-auth/sign-out",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(o,this.message);$.Navigation.reload()})))})});var u=$("#session-update-indicator");if(u.length){var f="Session refresh issue";setTimeout(function e(){null!==u&&$.API.get("/da-auth/status",$.mkAjaxHandler(function(){if(null!==u){if(!this.status)return $.Dialog.fail(f,this.message);if(!0===this.updating)return setTimeout(e,1e3);!0===this.deleted&&$.Dialog.fail(f,"We couldn't refresh your DeviantArt session automatically so you have been signed out. Due to elements on the page assuming you are signed in some actions will not work as expected until the page is reloaded."),$(".logged-in").replaceWith(this.loggedIn)}}))},1e3)}window.ServiceUnavailableError||$body.swipe($.throttle(10,function(e,o){if(!window.sidebarForcedVisible()&&$body.hasClass("sidebar-open")){var t=Math.abs(o.x),i=Math.abs(o.y),n=Math.min($body.width()/2,200);"left"!==e.x||t<n||75<i||$sbToggle.trigger("click")}}))}(),$w.on("load",function(){$body.removeClass("loading")});
//# sourceMappingURL=/js/min/global.js.map
