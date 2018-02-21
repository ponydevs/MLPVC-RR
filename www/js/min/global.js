"use strict";$(function(){var e=function(e){e.fluidbox({immediateOpen:!0,loader:!0}).on("openstart.fluidbox",function(){$body.addClass("fluidbox-open"),$(this).parents("#dialogContent").length&&$body.addClass("fluidbox-in-dialog")}).on("openend.fluidbox",function(){var e=$(this),o=e.attr("href");e.data("href",o),e.removeAttr("href"),0===e.find(".fluidbox__ghost").children().length&&e.find(".fluidbox__ghost").append($.mk("img").attr("src",o).css({opacity:0,width:"100%",height:"100%"}))}).on("closestart.fluidbox",function(){$body.removeClass("fluidbox-open");var e=$(this);e.attr("href",e.data("href")),e.removeData("href")}).on("closeend.fluidbox",function(){$body.removeClass("fluidbox-in-dialog")})};$.fn.fluidboxThis=function(o){var t=this;return"function"==typeof $.fn.fluidbox?(e(this),$.callCallback(o)):$.getScript("/js/min/jquery.ba-throttle-debounce.js",function(){$.getScript("/js/min/jquery.fluidbox.js",function(){e(t),$.callCallback(o)}).fail(function(){$.Dialog.fail(!1,"Loading Fluidbox plugin failed")})}).fail(function(){$.Dialog.fail(!1,"Loading Debounce/throttle plugin failed")}),this};var o,t,i,n,a=function(e,o){var t=void 0!==window.screenLeft?window.screenLeft:screen.left,i=void 0!==window.screenTop?window.screenTop:screen.top,n=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:screen.width;return{top:(window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:screen.height)/2-o/2+i,left:n/2-e/2+t}};$.PopupOpenCenter=function(e,o,t,i){var n=a(t,i),l=window.open(e,o,"scrollbars=yes,width="+t+",height="+i+",top="+n.top+",left="+n.left);return window.focus&&l.focus(),l},$.PopupMoveCenter=function(e,o,t){var i=a(o,t);e.resizeTo(o,t),e.moveTo(i.left,i.top)},$d.on("click","#turbo-sign-in",function(e){e.preventDefault();var o=$(this),t=o.parent().html();o.disable();var i=!1,n=void 0,a=void 0;window.__authCallback=function(){i=!0,"request"===$.Dialog._open.type?$.Dialog.clearNotice(/Redirecting you to DeviantArt/):$.Dialog.close(),a.close()};try{a=window.open("/da-auth/begin")}catch(e){return $.Dialog.fail(!1,"Could not open login pop-up. Please open another page")}$.Dialog.wait(!1,"Redirecting you to DeviantArt"),n=setInterval(function(){try{if(!a||a.closed){if(clearInterval(n),i)return;$.Dialog.fail(!1,t)}}catch(e){}},500)}),$.Navigation={visit:function(e){window.location.href=e},reload:function(){arguments.length>0&&void 0!==arguments[0]&&arguments[0]&&$.Dialog.wait(!1,"Reloading page",!0),window.location.reload()}},window.DocReady={push:function(e,o){"function"==typeof o&&(e.flush=o),$.Navigation._DocReadyHandlers.push(e)}},!0!==window.ServiceUnavailableError&&$.get("/footer-git",$.mkAjaxHandler(function(){this.footer&&$footer.prepend(this.footer)})),$sbToggle.off("click sb-open sb-close").on("click",function(e){e.preventDefault(),window.sidebarForcedVisible()||$sbToggle.trigger("sb-"+($body.hasClass("sidebar-open")?"close":"open"))}).on("sb-open sb-close",function(e){var o="close"===e.type.substring(3);$body[o?"removeClass":"addClass"]("sidebar-open");try{$.LocalStorage[o?"set":"remove"]("sidebar-closed","true")}catch(e){}setTimeout(function(){$w.trigger("resize")},510)}),o=void 0,t=void 0,i=function(){void 0!==t&&(clearInterval(t),t=void 0)},n=function e(){var t="function"==typeof o.parent&&0!==o.parent().length,n={},a=void 0,l=void 0;if(t&&(a=new Date,l=new Date(o.attr("datetime")),n=Time.Difference(a,l)),!t||n.past)return i(),void $.post("/about/upcoming",$.mkAjaxHandler(function(){if(!this.status)return console.error("Failed to load upcoming event list: "+this.message);var e=$("#upcoming");e.find("ul").html(this.html),this.html?e.removeClass("hidden"):e.addClass("hidden"),window.setUpcomingCountdown()}));var r=void 0;n.time<Time.InSeconds.month&&0===n.month?(n.week>0&&(n.day+=7*n.week),r="in ",n.day>0&&(r+=n.day+" day"+(1!==n.day?"s":"")+" & "),n.hour>0&&(r+=n.hour+":"),r+=$.pad(n.minute)+":"+$.pad(n.second)):(i(),setTimeout(e,1e4),r=moment(l).from(a)),o.text(r)},window.setUpcomingCountdown=function(){var e=$("#upcoming");if(e.length){var a=e.children("ul").children();if(!a.length)return e.addClass("hidden");e.removeClass("hidden"),o=a.first().find("time").addClass("nodt"),i(),t=setInterval(n,1e3),n(),e.find("li").each(function(){var e=$(this),o=e.children(".calendar"),t=moment(e.find(".countdown").data("airs")||e.find("time").attr("datetime"));o.children(".top").text(t.format("MMM")),o.children(".bottom").text(t.format("D"))}),Time.Update(),a.find(".title").simplemarquee({speed:25,cycles:1/0,space:25,handleHover:!1,delayBetweenCycles:0}).addClass("marquee")}},window.setUpcomingCountdown(),$(document).off("click",".send-feedback").on("click",".send-feedback",function(e){e.preventDefault(),e.stopPropagation(),$("#ctxmenu").hide();var o=["seinopsys","gmail.com"].join("@");$.Dialog.info($.Dialog.isOpen()?void 0:"Send feedback","<h3>How to send feedback</h3>\n\t\t\t<p>If you're having an issue with the site and would like to let us know or have an idea/feature request you'd like to share, here's how:</p>\n\t\t\t<ul>\n\t\t\t\t<li><a href='https://discord.gg/0vv70fepSILbdJOD'>Join our Discord server</a> and describe your issue/idea in the <strong>#support</strong> channel</li>\n\t\t\t\t<li><a href='http://mlp-vectorclub.deviantart.com/notes/'>Send a note </a>to the group on DeviantArt</li>\n\t\t\t\t<li><a href='mailto:"+o+"'>Send an e-mail</a> to "+o+'</li>\n\t\t\t\t<li>If you have a GitHub account, you can also  <a href="'+$footer.find("a.issues").attr("href")+"\">create an issue</a> on the project's GitHub page.\n\t\t\t</ul>")}),$(document).off("click",".action--color-avg").on("click",".action--color-avg",function(e){e.preventDefault(),e.stopPropagation();var o="Color Average Calculator",t=function(){$.Dialog.close();var e=window.$ColorAvgFormTemplate.clone(!0,!0);$.Dialog.request(o,e,!1,function(){e.triggerHandler("added")})};if(void 0===window.$ColorAvgFormTemplate){$.Dialog.wait(o,"Loading form, please wait");var i="/js/min/global-color_avg_form.js";$.getScript(i,t).fail(function(){setTimeout(function(){$.Dialog.close(function(){$.Dialog.wait(o,"Loading script (attempt #2)"),$.getScript(i.replace(/min\./,""),t).fail(function(){$.Dialog.fail(o,"Form could not be loaded")})})},1)})}else t()});var l=$("html");if($.isRunningStandalone()){var r=l.scrollTop(),s=function(){if(window.withinMobileBreakpoint()&&!l.is(":animated")){var e=l.scrollTop(),o=$header.outerHeight(),t=parseInt($header.css("top"),10);$header.css("top",e>r?Math.max(-o,t-(e-r)):Math.min(0,t+(r-e))),r=e}};$d.on("scroll",s),s()}var c=$("#to-the-top").on("click",function(e){e.preventDefault(),l.stop().animate({scrollTop:0},200),c.removeClass("show")});function d(){if(window.withinMobileBreakpoint()&&!l.is(":animated")){var e=0!==l.scrollTop();!e&&c.hasClass("show")?c.removeClass("show"):e&&!c.hasClass("show")&&c.addClass("show")}}$d.on("scroll",d),d(),$.LocalStorage.remove("cookie_consent");var u=$.LocalStorage.get("cookie_consent_v2");$("#signin").off("click").on("click",function(){var e=$(this),o=function(o){if(o){$.Dialog.close(),$.LocalStorage.set("cookie_consent_v2",1),e.disable();var t=function(){$.Dialog.wait(!1,"Redirecting you to DeviantArt"),location.href="/da-auth/begin?return="+encodeURIComponent($.hrefToPath(location.href))};if(-1!==navigator.userAgent.indexOf("Trident"))return t();$.Dialog.wait("Sign-in process","Opening popup window");var i=!1,n=void 0,a=void 0;window.__authCallback=function(o,t){if(clearInterval(n),!0!==o)i=!0,$.Dialog.success(!1,"Signed in successfully"),a.close(),$.Navigation.reload(!0);else{if(t.jQuery){var l=t.$("#content").children("h1").html(),r=t.$("#content").children(".notice").html();$.Dialog.fail(!1,'<p class="align-center"><strong>'+l+"</strong></p><p>"+r+"</p>"),a.close()}else $.Dialog.fail(!1,"Sign in failed, check popup for details.");e.enable()}};try{a=$.PopupOpenCenter("/da-auth/begin","login","450","580")}catch(e){}n=setInterval(function(){try{a&&!a.closed||(clearInterval(n),function(){if(!i){if(-1!==document.cookie.indexOf("auth="))return window.__authCallback;$.Dialog.fail(!1,"Popup-based login failed"),t()}}())}catch(e){}},500),$w.on("beforeunload",function(){i=!0,a.close()}),$.Dialog.wait(!1,"Waiting for you to sign in")}};u?o(!0):$.Dialog.confirm("Privacy Notice",'<p>We must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.</p><p>If you would like to avoid these completly harmless pieces of text which are required to log in to this website, click "Decline" and continue browsing as a guest.</p><p><em>This warning will not appear again if you accept our use of persistent cookies.</em></p>',["Accept","Decline"],o)}),$("#signout").off("click").on("click",function(){var e="Sign out";$.Dialog.confirm(e,"Are you sure you want to sign out?",function(o){o&&($.Dialog.wait(e,"Signing out"),$.post("/da-auth/signout",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(e,this.message);$.Navigation.reload()})))})});var f=$("#session-update-indicator");if(f.length){var g="Session refresh issue";setTimeout(function e(){null!==f&&$.post("/da-auth/status",$.mkAjaxHandler(function(){if(null!==f){if(!this.status)return $.Dialog.fail(g,this.message);if(!0===this.updating)return setTimeout(e,2e3);!0===this.deleted&&$.Dialog.fail(g,"We couldn't verify your DeviantArt session automatically so you have been signed out. Due to elements on the page assuming you are signed in some actions will not work as expected until the page is reloaded."),$(".logged-in").replaceWith(this.loggedIn)}}))},2e3)}$body.swipe($.throttle(10,function(e,o){if(!window.sidebarForcedVisible()&&$body.hasClass("sidebar-open")){var t=Math.abs(o.x),i=Math.abs(o.y),n=Math.min($body.width()/2,200);"left"!==e.x||t<n||i>75||$sbToggle.trigger("click")}}))}),$w.on("load",function(){$body.removeClass("loading")});
//# sourceMappingURL=/js/min/global.js.map
