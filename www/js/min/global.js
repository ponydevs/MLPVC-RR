"use strict";$(function(){console.log("[HTTP-Nav] > $(document).ready()"),console.group("[HTTP-Nav] GET "+window.location.pathname+window.location.search+window.location.hash);var e=function(e){e.fluidbox({immediateOpen:!0,loader:!0}).on("openstart.fluidbox",function(){$body.addClass("fluidbox-open"),$(this).parents("#dialogContent").length&&$body.addClass("fluidbox-in-dialog")}).on("openend.fluidbox",function(){var e=$(this),o=e.attr("href");e.data("href",o),e.removeAttr("href"),0===e.find(".fluidbox__ghost").children().length&&e.find(".fluidbox__ghost").append($.mk("img").attr("src",o).css({opacity:0,width:"100%",height:"100%"}))}).on("closestart.fluidbox",function(){$body.removeClass("fluidbox-open");var e=$(this);e.attr("href",e.data("href")),e.removeData("href")}).on("closeend.fluidbox",function(){$body.removeClass("fluidbox-in-dialog")})};$.fn.fluidboxThis=function(o){var t=this;return"function"==typeof $.fn.fluidbox?(e(this),$.callCallback(o)):$.getScript("/js/min/jquery.ba-throttle-debounce.js",function(){$.getScript("/js/min/jquery.fluidbox.js",function(){e(t),$.callCallback(o)}).fail(function(){$.Dialog.fail(!1,"Loading Fluidbox plugin failed")})}).fail(function(){$.Dialog.fail(!1,"Loading Debounce/throttle plugin failed")}),this};var o=function(e,o){var t=void 0!==window.screenLeft?window.screenLeft:screen.left,n=void 0!==window.screenTop?window.screenTop:screen.top,i=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:screen.width;return{top:(window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:screen.height)/2-o/2+n,left:i/2-e/2+t}};$.PopupOpenCenter=function(e,t,n,i){var a=o(n,i),s=window.open(e,t,"scrollbars=yes,width="+n+",height="+i+",top="+a.top+",left="+a.left);return window.focus&&s.focus(),s},$.PopupMoveCenter=function(e,t,n){var i=o(t,n);e.resizeTo(t,n),e.moveTo(i.left,i.top)};var t,n,i,a,s=window.OAUTH_URL,r=function(){return(~~(99999999*Math.random())).toString(36)};$d.on("click","#turbo-sign-in",function(e){e.preventDefault();var o=$(this),t=o.parent().html();o.disable(),s=o.attr("data-url");var n=r(),i=!1,a=void 0,c=void 0;window[" "+n]=function(){i=!0,"request"===$.Dialog._open.type?$.Dialog.clearNotice(/Redirecting you to DeviantArt/):$.Dialog.close(),c.close()};try{c=window.open(s+"&state="+n)}catch(e){return $.Dialog.fail(!1,"Could not open login pop-up. Please open another page")}$.Dialog.wait(!1,"Redirecting you to DeviantArt"),a=setInterval(function(){try{if(!c||c.closed){if(clearInterval(a),i)return;$.Dialog.fail(!1,t)}}catch(e){}},500)}),$.Navigation={visit:function(e){window.location.href=e},reload:function(){arguments.length>0&&void 0!==arguments[0]&&arguments[0]&&$.Dialog.wait(!1,"Reloading page",!0),window.location.reload()}},window.DocReady={push:function(e,o){"function"==typeof o&&(e.flush=o),$.Navigation._DocReadyHandlers.push(e)}},!0!==window.ServiceUnavailableError&&$.get("/footer-git",$.mkAjaxHandler(function(){this.footer&&$footer.prepend(this.footer)})),$sbToggle.off("click sb-open sb-close").on("click",function(e){e.preventDefault(),window.sidebarForcedVisible()||$sbToggle.trigger("sb-"+($body.hasClass("sidebar-open")?"close":"open"))}).on("sb-open sb-close",function(e){var o="close"===e.type.substring(3);$body[o?"removeClass":"addClass"]("sidebar-open");try{$.LocalStorage[o?"set":"remove"]("sidebar-closed","true")}catch(e){}setTimeout(function(){$w.trigger("resize")},510)}),t=void 0,n=void 0,i=function(){void 0!==n&&(clearInterval(n),n=void 0)},a=function e(){var o="function"==typeof t.parent&&0!==t.parent().length,n={},a=void 0,s=void 0;if(o&&(a=new Date,s=new Date(t.attr("datetime")),n=Time.Difference(a,s)),!o||n.past)return i(),void $.post("/about/upcoming",$.mkAjaxHandler(function(){if(!this.status)return console.error("Failed to load upcoming event list: "+this.message);var e=$("#upcoming");e.find("ul").html(this.html),this.html?e.removeClass("hidden"):e.addClass("hidden"),window.setUpcomingCountdown()}));var r=void 0;n.time<Time.InSeconds.month&&0===n.month?(n.week>0&&(n.day+=7*n.week),r="in ",n.day>0&&(r+=n.day+" day"+(1!==n.day?"s":"")+" & "),n.hour>0&&(r+=n.hour+":"),r+=$.pad(n.minute)+":"+$.pad(n.second)):(i(),setTimeout(e,1e4),r=moment(s).from(a)),t.text(r)},window.setUpcomingCountdown=function(){var e=$("#upcoming");if(e.length){var o=e.children("ul").children();if(!o.length)return e.addClass("hidden");e.removeClass("hidden"),t=o.first().find("time").addClass("nodt"),i(),n=setInterval(a,1e3),a(),e.find("li").each(function(){var e=$(this),o=e.children(".calendar"),t=moment(e.find(".countdown").data("airs")||e.find("time").attr("datetime"));o.children(".top").text(t.format("MMM")),o.children(".bottom").text(t.format("D"))}),Time.Update();var s=function(){o.find(".title").simplemarquee({speed:25,cycles:1/0,space:25,handleHover:!1,delayBetweenCycles:0}).addClass("marquee")};"function"!=typeof jQuery.fn.simplemarquee?$.ajax({url:"/js/min/jquery.simplemarquee.js",dataType:"script",cache:!0,success:s}):s()}},window.setUpcomingCountdown(),$(document).off("click",".send-feedback").on("click",".send-feedback",function(e){e.preventDefault(),e.stopPropagation(),$("#ctxmenu").hide();var o=["seinopsys","gmail.com"].join("@");$.Dialog.info($.Dialog.isOpen()?void 0:"Send feedback","<h3>How to send feedback</h3>\n\t\t\t<p>If you're having an issue with the site and would like to let us know or have an idea/feature request you’d like to share, here’s how:</p>\n\t\t\t<ul>\n\t\t\t\t<li><a href='https://discord.gg/0vv70fepSILbdJOD'>Join our Discord server</a> and describe your issue/idea in the <strong>#support</strong> channel</li>\n\t\t\t\t<li><a href='http://mlp-vectorclub.deviantart.com/notes/'>Send a note </a>to the group on DeviantArt</li>\n\t\t\t\t<li><a href='mailto:"+o+"'>Send an e-mail</a> to "+o+'</li>\n\t\t\t\t<li>If you have a GitHub account, you can also  <a href="'+$footer.find("a.issues").attr("href")+'">create an issue</a> on the project’s GitHub page.\n\t\t\t</ul>')}),$(document).off("click",".action--color-avg").on("click",".action--color-avg",function(e){e.preventDefault(),e.stopPropagation();var o="Color Average Calculator",t=function(){$.Dialog.close();var e=window.$ColorAvgFormTemplate.clone(!0,!0);$.Dialog.request(o,e,!1,function(){e.triggerHandler("added")})};if(void 0===window.$ColorAvgFormTemplate){$.Dialog.wait(o,"Loading form, please wait");var n="/js/min/global-color_avg_form.js";$.getScript(n,t).fail(function(){setTimeout(function(){$.Dialog.close(function(){$.Dialog.wait(o,"Loading script (attempt #2)"),$.getScript(n.replace(/min\./,""),t).fail(function(){$.Dialog.fail(o,"Form could not be loaded")})})},1)})}else t()});var c=$("html");if($.isRunningStandalone()){var l=c.scrollTop(),d=function(){if(window.withinMobileBreakpoint()&&!c.is(":animated")){var e=c.scrollTop(),o=$header.outerHeight(),t=parseInt($header.css("top"),10);$header.css("top",e>l?Math.max(-o,t-(e-l)):Math.min(0,t+(l-e))),l=e}};$d.on("scroll",d),d()}var u=$("#to-the-top").on("click",function(e){e.preventDefault(),c.stop().animate({scrollTop:0},200),u.removeClass("show")});function f(){if(window.withinMobileBreakpoint()&&!c.is(":animated")){var e=0!==c.scrollTop();!e&&u.hasClass("show")?u.removeClass("show"):e&&!u.hasClass("show")&&u.addClass("show")}}$d.on("scroll",f),f(),function(){var e,o,t=void 0,n="https://ws."+location.hostname+":8667/",i=function(e){return function(o){if("string"==typeof o)try{o=JSON.parse(o)}catch(e){}e(o)}},a=void 0,s=void 0,r=void 0,c=!1,l=function(){r.off("click",".mark-read").on("click",".mark-read",function(e){e.preventDefault(),e.stopPropagation();var o=$(this);if(!o.hasClass("disabled")){var t=o.attr("data-id"),n={read_action:o.attr("data-value")},i=o.attr("data-action")||"Mark notification as read",a=function(){o.siblings(".mark-read").addBack().addClass("disabled"),$.post("/notifications/mark-read/"+t,n,$.mkAjaxHandler(function(){return this.status?this.message?$.Dialog.success(i,this.message,!0):void $.Dialog.close():$.Dialog.fail(i,this.message)})).always(function(){o.siblings(".mark-read").addBack().removeClass("disabled")})};n.read_action&&o.hasAttr("data-confirm")?$.Dialog.confirm("Actionable notification",'Please confirm your choice: <strong class="color-'+o.attr("class").replace(/^.*variant-(\w+)\b.*$/,"$1")+'">'+o.attr("title")+"</strong>",["Confirm","Cancel"],function(e){e&&($.Dialog.wait(i),a())}):a()}})},d=function(){0===(a=$sbToggle.children(".notif-cnt")).length&&(a=$.mk("span").attr({class:"notif-cnt",title:"New notifications"}).prependTo($sbToggle)),s=$sidebar.children(".notifications"),r=s.children(".notif-list"),l()};function u(){var e=function(){d(),t||((t=io(n,{reconnectionDelay:1e4})).on("connect",function(){console.log("[WS] %cConnected","color:green"),$.WS.recvPostUpdates(void 0!==window.EpisodePage),$.WS.navigate()}),t.on("auth",i(function(e){c=!0,console.log("[WS] %cAuthenticated as "+e.name,"color:teal")})),t.on("auth-guest",i(function(){console.log("[WS] %cReceiving events as a guest","color:teal")})),t.on("notif-cnt",i(function(e){var o=e.cnt?parseInt(e.cnt,10):0;console.log("[WS] Unread notification count: %d",o),d(),0===o?s.stop().slideUp("fast",function(){r.empty(),a.empty()}):$.post("/notifications/get",$.mkAjaxHandler(function(){a.text(o),r.html(this.list),Time.Update(),l(),s.stop().slideDown()}))})),t.on("post-delete",i(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.deleting)");console.log("[WS] Post deleted (postid=%s)",o),t.length&&(t.find(".fluidbox--opened").fluidbox("close"),t.find(".fluidbox--initialized").fluidbox("destroy"),t.attr({class:"deleted",title:"This post has been deleted; click here to hide"}).on("click",function(){var e=$(this);e[window.withinMobileBreakpoint()?"slideUp":"fadeOut"](500,function(){e.remove()})}))}})),t.on("post-break",i(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.admin-break)");console.log("[WS] Post broken (postid=%s)",o),t.length&&(t.find(".fluidbox--opened").fluidbox("close"),t.find(".fluidbox--initialized").fluidbox("destroy"),t.reloadLi())}})),t.on("post-add",i(function(e){e.type&&e.id&&window.EPISODE===e.episode&&window.SEASON===e.season&&($(".posts #"+e.type+"-"+e.id).length>0||$.post("/post/reload/"+e.type+"/"+e.id,$.mkAjaxHandler(function(){if(this.status&&!($(".posts #"+e.type+"-"+e.id).length>0)){var o=$(this.li);$(this.section).append(o),o.rebindFluidbox(),Time.Update(),o.rebindHandlers(!0).parent().reorderPosts(),console.log("[WS] Post added (postid="+e.type+"-#"+e.id+") to container "+this.section)}})))})),t.on("post-update",i(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.deleting)");console.log("[WS] Post updated (postid=%s)",o),t.length&&t.reloadLi(!1)}})),t.on("entry-score",i(function(e){if(void 0!==e.entryid){var o=$("#entry-"+e.entryid);console.log("[WS] Entry score updated (entryid=%s, score=%s)",e.entryid,e.score),o.length&&o.refreshVoting()}})),t.on("devaction",i(function(e){if(console.log("[WS] DevAction",e),"string"==typeof e.remoteAction)switch(e.remoteAction){case"reload":window.location.reload();break;case"message":$.Dialog.info("Message from the developer",e.data.html)}})),t.on("disconnect",function(){c=!1,console.log("[WS] %cDisconnected","color:red")}))};window.io?e():$.ajax({url:n+"socket.io/socket.io.js",cache:"true",dataType:"script",success:e,statusCode:{404:function(){console.log("%c[WS] Server down!","color:red"),$.WS.down=!0,$sidebar.find(".notif-list").on("click",".mark-read",function(e){e.preventDefault(),$.Dialog.fail("Mark notification read",'The notification server appears to be down. Please <a class="send-feedback">let us know</a>, and sorry for the inconvenience.')})}}})}u(),$.WS=(o={postUpdates:!1,entryUpdates:!1},(e=function(){return u()}).down=!1,e.navigate=function(){if(void 0!==t){var e=location.pathname+location.search+location.hash;t.emit("navigate",{page:e})}},e.recvPostUpdates=function(n){if(void 0===t)return setTimeout(function(){e.recvPostUpdates(n)},2e3);"boolean"==typeof n&&o.postUpdates!==n&&t.emit("post-updates",String(n),i(function(e){if(!e.status)return console.log("[WS] %cpost-updates subscription status change failed (subscribe=%s)","color:red",n);o.postUpdates=n,$("#episode-live-update")[o.postUpdates?"removeClass":"addClass"]("hidden"),console.log("[WS] %c%s","color:green",e.message)}))},e.recvEntryUpdates=function(n){if(void 0===t)return setTimeout(function(){e.recvEntryUpdates(n)},2e3);"boolean"==typeof n&&o.entryUpdates!==n&&t.emit("entry-updates",String(n),i(function(e){if(!e.status)return console.log("[WS] %centry-updates subscription status change failed (subscribe=%s)","color:red",n);o.entryUpdates=n,$("#entry-live-update")[o.entryUpdates&&"contest"===window.EventType?"removeClass":"addClass"]("hidden"),console.log("[WS] %c%s","color:green",e.message)}))},e.authme=function(){void 0!==t&&!0!==c&&(console.log("[WS] %cReconnection needed for identity change","color:teal"),t.disconnect(0),setTimeout(function(){t.connect()},100))},e.unauth=function(){void 0!==t&&!0===c&&t.emit("unauth",null,function(e){if(!e.status)return console.log("[WS] %cUnauth failed","color:red");c=!1,console.log("[WS] %cAuthentication dropped","color:brown")})},e.disconnect=function(e){void 0!==t&&(console.log("[WS] Forced disconnect (reason="+e+")"),t.disconnect(0))},e.status=function(){if(void 0===t)return setTimeout(function(){e.status()},2e3);t.emit("status",null,i(function(e){console.log("[WS] Status: ID=%s; Name=%s; Rooms=%s",e.User.id,e.User.name,e.rooms.join(","))}))},e.devquery=function(o){var n=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:void 0;if(void 0===t)return setTimeout(function(){e.devquery(o,n,a)},2e3);t.emit("devquery",{what:o,data:n},i(function(e){if("function"==typeof a)return a(e);console.log("[WS] DevQuery "+(e.status?"Success":"Fail"),e)}))},e.devaction=function(o,n){var a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:{};if(void 0===t)return setTimeout(function(){e.devaction(o,n,a)},2e3);t.emit("devaction",{clientId:o,remoteAction:n,data:a},i(function(e){console.log("[WS] DevAction "+(e.status?"Success":"Fail"),e)}))},e.essentialElements=function(){d()},e)}(),function(){console.log("> docReadyAlwaysRun()"),$d.triggerHandler("paginate-refresh"),$.LocalStorage.remove("cookie_consent");var e=$.LocalStorage.get("cookie_consent_v2");s=window.OAUTH_URL,$("#signin").off("click").on("click",function(){var o=$(this),t=function(e){if(e){$.Dialog.close(),$.LocalStorage.set("cookie_consent_v2",1),o.disable();var t=function(){$.Dialog.wait(!1,"Redirecting you to DeviantArt"),location.href=s+"&state="+encodeURIComponent(location.href.replace(location.origin,""))};if(-1!==navigator.userAgent.indexOf("Trident"))return t();$.Dialog.wait("Sign-in process","Opening popup window");var n=r(),i=!1,a=void 0,c=void 0;window[" "+n]=function(e,t){if(clearInterval(a),!0!==e)i=!0,$.Dialog.success(!1,"Signed in successfully"),c.close(),$.Navigation.reload(!0);else{if(t.jQuery){var n=t.$("#content").children("h1").text(),s=t.$("#content").children(".notice").html();$.Dialog.fail(!1,'<p class="align-center"><strong>'+n+"</strong></p><p>"+s+"</p>"),c.close()}else $.Dialog.fail(!1,"Sign in failed, check popup for details.");o.enable()}};try{c=$.PopupOpenCenter(s+"&state="+n,"login","450","580")}catch(e){}a=setInterval(function(){try{c&&!c.closed||(clearInterval(a),function(){if(!i){if(-1!==document.cookie.indexOf("auth="))return window[" "+n];$.Dialog.fail(!1,"Popup-based login unsuccessful"),t()}}())}catch(e){}},500),$w.on("beforeunload",function(){i=!0,c.close()}),$.Dialog.wait(!1,"Waiting for you to sign in")}};e?t(!0):$.Dialog.confirm("Privacy Notice",'<p>We must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.</p><p>If you would like to avoid these completly harmless pieces of text which are required to log in to this website, click "Decline" and continue browsing as a guest.</p><p><em>This warning will not appear again if you accept our use of persistent cookies.</em></p>',["Accept","Decline"],t)}),$("#signout").off("click").on("click",function(){$.Dialog.confirm("Sign out","Are you sure you want to sign out?",function(e){e&&($.Dialog.wait("Sign out","Signing out"),$.post("/da-auth/signout",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Sign out",this.message);$.Navigation.reload()})))})}),$body.swipe($.throttle(10,function(e,o){if(!window.sidebarForcedVisible()&&$body.hasClass("sidebar-open")){var t=Math.abs(o.x),n=Math.abs(o.y),i=Math.min($body.width()/2,200);"left"!==e.x||t<i||n>75||$sbToggle.trigger("click")}}))}(),console.log("%cDocument ready handlers called","color:green"),console.groupEnd()}),$w.on("load",function(){$body.removeClass("loading")});
//# sourceMappingURL=/js/min/global.js.map
