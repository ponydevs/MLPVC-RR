"use strict";$(function(){console.log("[HTTP-Nav] > $(document).ready()"),console.group("[HTTP-Nav] GET "+window.location.pathname+window.location.search+window.location.hash);var e=function(e){e.fluidbox({immediateOpen:!0,loader:!0}).on("openstart.fluidbox",function(){$body.addClass("fluidbox-open"),$(this).parents("#dialogContent").length&&$body.addClass("fluidbox-in-dialog")}).on("openend.fluidbox",function(){var e=$(this),o=e.attr("href");e.data("href",o),e.removeAttr("href"),0===e.find(".fluidbox__ghost").children().length&&e.find(".fluidbox__ghost").append($.mk("img").attr("src",o).css({opacity:0,width:"100%",height:"100%"}))}).on("closestart.fluidbox",function(){$body.removeClass("fluidbox-open");var e=$(this);e.attr("href",e.data("href")),e.removeData("href")}).on("closeend.fluidbox",function(){$body.removeClass("fluidbox-in-dialog")})};$.fn.fluidboxThis=function(o){var t=this;return"function"==typeof $.fn.fluidbox?(e(this),$.callCallback(o)):$.getScript("/js/min/jquery.ba-throttle-debounce.js",function(){$.getScript("/js/min/jquery.fluidbox.js",function(){e(t),$.callCallback(o)}).fail(function(){$.Dialog.fail(!1,"Loading Fluidbox plugin failed")})}).fail(function(){$.Dialog.fail(!1,"Loading Debounce/throttle plugin failed")}),this};var o=function(e,o){var t=void 0!==window.screenLeft?window.screenLeft:screen.left,n=void 0!==window.screenTop?window.screenTop:screen.top,i=window.innerWidth?window.innerWidth:document.documentElement.clientWidth?document.documentElement.clientWidth:screen.width;return{top:(window.innerHeight?window.innerHeight:document.documentElement.clientHeight?document.documentElement.clientHeight:screen.height)/2-o/2+n,left:i/2-e/2+t}};$.PopupOpenCenter=function(e,t,n,i){var a=o(n,i),s=window.open(e,t,"scrollbars=yes,width="+n+",height="+i+",top="+a.top+",left="+a.left);return window.focus&&s.focus(),s},$.PopupMoveCenter=function(e,t,n){var i=o(t,n);e.resizeTo(t,n),e.moveTo(i.left,i.top)};var t=window.OAUTH_URL,n=function(){return(~~(99999999*Math.random())).toString(36)};if($d.on("click","#turbo-sign-in",function(e){e.preventDefault();var o=$(this),i=o.parent().html();o.disable(),t=o.attr("data-url");var a=n(),s=!1,r=void 0,c=void 0;window[" "+a]=function(){s=!0,"request"===$.Dialog._open.type?$.Dialog.clearNotice(/Redirecting you to DeviantArt/):$.Dialog.close(),c.close()};try{c=window.open(t+"&state="+a)}catch(e){return $.Dialog.fail(!1,"Could not open login pop-up. Please open another page")}$.Dialog.wait(!1,"Redirecting you to DeviantArt"),r=setInterval(function(){try{if(!c||c.closed){if(clearInterval(r),s)return;$.Dialog.fail(!1,i)}}catch(e){}},500)}),$.Navigation={visit:function(e){window.location.href=e},reload:function(){arguments.length>0&&void 0!==arguments[0]&&arguments[0]&&$.Dialog.wait(!1,"Reloading page",!0),window.location.reload()}},window.DocReady={push:function(e,o){"function"==typeof o&&(e.flush=o),$.Navigation._DocReadyHandlers.push(e)}},"serviceWorker"in navigator&&window.addEventListener("load",function(){navigator.serviceWorker.register("/sw.js").then(function(){}).catch(function(){})}),!0!==window.ServiceUnavailableError&&$.get("/footer-git",$.mkAjaxHandler(function(){this.footer&&$footer.prepend(this.footer)})),function(){var e=function(){setTimeout(function(){$w.trigger("resize")},510)};$sbToggle.off("click sb-open sb-close").on("click",function(e){e.preventDefault(),window.sidebarForcedVisible()||$sbToggle.trigger("sb-"+($body.hasClass("sidebar-open")?"close":"open"))}).on("sb-open sb-close",function(o){var t="close"===o.type.substring(3);$body[t?"removeClass":"addClass"]("sidebar-open");try{$.LocalStorage[t?"set":"remove"]("sidebar-closed","true")}catch(e){}e()})}(),function(){var e=void 0,o=void 0,t=function(){void 0!==o&&(clearInterval(o),o=void 0)},n=function o(){var n="function"==typeof e.parent&&0!==e.parent().length,i={},a=void 0,s=void 0;if(n&&(a=new Date,s=new Date(e.attr("datetime")),i=Time.Difference(a,s)),!n||i.past)return n&&(e.find(".marquee").trigger("destroy.simplemarquee"),e.parents("li").remove()),t(),window.setUpcomingCountdown();var r=void 0;i.time<Time.InSeconds.month&&0===i.month?(i.week>0&&(i.day+=7*i.week),r="in ",i.day>0&&(r+=i.day+" day"+(1!==i.day?"s":"")+" & "),i.hour>0&&(r+=i.hour+":"),r+=$.pad(i.minute)+":"+$.pad(i.second)):(t(),setTimeout(o,1e4),r=moment(s).from(a)),e.text(r)};window.setUpcomingCountdown=function(){var i=$("#upcoming");if(i.length){var a=i.children("ul").children();if(!a.length)return i.remove();e=a.first().find("time").addClass("nodt"),t(),o=setInterval(n,1e3),n(),i.find("li").each(function(){var e=$(this),o=e.children(".calendar"),t=moment(e.find(".countdown").data("airs")||e.find("time").attr("datetime"));o.children(".top").text(t.format("MMM")),o.children(".bottom").text(t.format("D"))}),Time.Update();var s=function(){a.find(".title").simplemarquee({speed:25,cycles:1/0,space:25,handleHover:!1,delayBetweenCycles:0}).addClass("marquee")};"function"!=typeof jQuery.fn.simplemarquee?$.ajax({url:"/js/min/jquery.simplemarquee.js",dataType:"script",cache:!0,success:s}):s()}},window.setUpcomingCountdown()}(),$(document).off("click",".send-feedback").on("click",".send-feedback",function(e){e.preventDefault(),e.stopPropagation(),$("#ctxmenu").hide();var o=["seinopsys","gmail.com"].join("@");$.Dialog.info($.Dialog.isOpen()?void 0:"Send feedback","<h3>How to send feedback</h3>\n\t\t\t<p>If you're having an issue with the site and would like to let us know or have an idea/feature request you’d like to share, here’s how:</p>\n\t\t\t<ul>\n\t\t\t\t<li><a href='https://discord.gg/0vv70fepSILbdJOD'>Join our Discord server</a> and describe your issue/idea in the <strong>#support</strong> channel</li>\n\t\t\t\t<li><a href='http://mlp-vectorclub.deviantart.com/notes/'>Send a note </a>to the group on DeviantArt</li>\n\t\t\t\t<li><a href='mailto:"+o+"'>Send an e-mail</a> to "+o+'</li>\n\t\t\t\t<li>If you have a GitHub account, you can also  <a href="'+$footer.find("a.issues").attr("href")+'">create an issue</a> on the project’s GitHub page.\n\t\t\t</ul>')}),$(document).off("click",".action--color-avg").on("click",".action--color-avg",function(e){e.preventDefault(),e.stopPropagation();var o="Color Average Calculator",t=function(){$.Dialog.close();var e=window.$ColorAvgFormTemplate.clone(!0,!0);$.Dialog.request(o,e,!1,function(){e.triggerHandler("added")})};if(void 0===window.$ColorAvgFormTemplate){$.Dialog.wait(o,"Loading form, please wait");var n="/js/min/global-color_avg_form.js";$.getScript(n,t).fail(function(){setTimeout(function(){$.Dialog.close(function(){$.Dialog.wait(o,"Loading script (attempt #2)"),$.getScript(n.replace(/min\./,""),t).fail(function(){$.Dialog.fail(o,"Form could not be loaded")})})},1)})}else t()}),$.isRunningStandalone()){var i=$body.scrollTop(),a=function(){if(window.withinMobileBreakpoint()){var e=$body.scrollTop(),o=$header.outerHeight(),t=parseInt($header.css("top"),10);$header.css("top",e>i?Math.max(-o,t-(e-i)):Math.min(0,t+(i-e))),i=e}};$w.on("scroll",a),a()}!function(){function e(){var e=function(){l(),o||((o=io(t,{reconnectionDelay:1e4})).on("connect",function(){console.log("[WS] %cConnected","color:green"),$.WS.recvPostUpdates(void 0!==window.EpisodePage),$.WS.navigate()}),o.on("auth",n(function(e){r=!0,console.log("[WS] %cAuthenticated as "+e.name,"color:teal")})),o.on("auth-guest",n(function(){console.log("[WS] %cReceiving events as a guest","color:teal")})),o.on("notif-cnt",n(function(e){var o=e.cnt?parseInt(e.cnt,10):0;console.log("[WS] Unread notification count: %d",o),l(),0===o?a.stop().slideUp("fast",function(){s.empty(),i.empty()}):$.post("/notifications/get",$.mkAjaxHandler(function(){i.text(o),s.html(this.list),Time.Update(),c(),a.stop().slideDown()}))})),o.on("post-delete",n(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.deleting)");console.log("[WS] Post deleted (postid=%s)",o),t.length&&(t.find(".fluidbox--opened").fluidbox("close"),t.find(".fluidbox--initialized").fluidbox("destroy"),t.attr({class:"deleted",title:"This post has been deleted; click here to hide"}).on("click",function(){var e=$(this);e[window.withinMobileBreakpoint()?"slideUp":"fadeOut"](500,function(){e.remove()})}))}})),o.on("post-break",n(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.admin-break)");console.log("[WS] Post broken (postid=%s)",o),t.length&&(t.find(".fluidbox--opened").fluidbox("close"),t.find(".fluidbox--initialized").fluidbox("destroy"),t.reloadLi())}})),o.on("post-add",n(function(e){e.type&&e.id&&window.EPISODE===e.episode&&window.SEASON===e.season&&($(".posts #"+e.type+"-"+e.id).length>0||$.post("/post/reload/"+e.type+"/"+e.id,$.mkAjaxHandler(function(){if(this.status&&!($(".posts #"+e.type+"-"+e.id).length>0)){var o=$(this.li);$(this.section).append(o),o.rebindFluidbox(),Time.Update(),o.rebindHandlers(!0).parent().reorderPosts(),console.log("[WS] Post added (postid="+e.type+"-#"+e.id+") to container "+this.section)}})))})),o.on("post-update",n(function(e){if(e.type&&e.id){var o=e.type+"-"+e.id,t=$("#"+o+":not(.deleting)");console.log("[WS] Post updated (postid=%s)",o),t.length&&t.reloadLi(!1)}})),o.on("entry-score",n(function(e){if(void 0!==e.entryid){var o=$("#entry-"+e.entryid);console.log("[WS] Entry score updated (entryid=%s, score=%s)",e.entryid,e.score),o.length&&o.refreshVoting()}})),o.on("disconnect",function(){r=!1,console.log("[WS] %cDisconnected","color:red")}))};window.io?e():$.ajax({url:t+"socket.io/socket.io.js",cache:"true",dataType:"script",success:e,statusCode:{404:function(){console.log("%c[WS] Server down!","color:red"),$.WS.down=!0,$sidebar.find(".notif-list").on("click",".mark-read",function(e){e.preventDefault(),$.Dialog.fail("Mark notification read",'The notification server appears to be down. Please <a class="send-feedback">let us know</a>, and sorry for the inconvenience.')})}}})}var o=void 0,t="https://ws."+location.hostname+":8667/",n=function(e){return function(o){if("string"==typeof o)try{o=JSON.parse(o)}catch(e){}e(o)}},i=void 0,a=void 0,s=void 0,r=!1,c=function(){s.off("click",".mark-read").on("click",".mark-read",function(e){e.preventDefault(),e.stopPropagation();var o=$(this);if(!o.is(":disabled")){var t=o.attr("data-id"),n={read_action:o.attr("data-value")},i=o.attr("data-action")||"Mark notification as read",a=function(){o.siblings().addBack().css("opacity",".5").disable(),$.post("/notifications/mark-read/"+t,n,$.mkAjaxHandler(function(){return this.status?this.message?$.Dialog.success(i,this.message,!0):void $.Dialog.close():$.Dialog.fail(i,this.message)})).always(function(){o.siblings().addBack().css("opacity","").enable()})};n.read_action&&o.hasAttr("data-confirm")?$.Dialog.confirm("Actionable notification",'Please confirm your choice: <strong class="color-'+o.attr("class").replace(/^.*variant-(\w+)\b.*$/,"$1")+'">'+o.attr("title")+"</strong>",["Confirm","Cancel"],function(e){e&&($.Dialog.wait(i),a())}):a()}})},l=function(){0===(i=$sbToggle.children(".notif-cnt")).length&&(i=$.mk("span").attr({class:"notif-cnt",title:"New notifications"}).prependTo($sbToggle)),a=$sidebar.children(".notifications"),s=a.children(".notif-list"),c()};e(),$.WS=function(){var t=function(){return e()},i={postUpdates:!1,entryUpdates:!1};return t.down=!1,t.navigate=function(){if(void 0!==o){var e=location.pathname+location.search+location.hash;o.emit("navigate",{page:e})}},t.recvPostUpdates=function(e){if(void 0===o)return setTimeout(function(){t.recvPostUpdates(e)},2e3);"boolean"==typeof e&&i.postUpdates!==e&&o.emit("post-updates",String(e),n(function(o){if(!o.status)return console.log("[WS] %cpost-updates subscription status change failed (subscribe=%s)","color:red",e);i.postUpdates=e,$("#episode-live-update")[i.postUpdates?"removeClass":"addClass"]("hidden"),console.log("[WS] %c%s","color:green",o.message)}))},t.recvEntryUpdates=function(e){if(void 0===o)return setTimeout(function(){t.recvEntryUpdates(e)},2e3);"boolean"==typeof e&&i.entryUpdates!==e&&o.emit("entry-updates",String(e),n(function(o){if(!o.status)return console.log("[WS] %centry-updates subscription status change failed (subscribe=%s)","color:red",e);i.entryUpdates=e,$("#entry-live-update")[i.entryUpdates&&"contest"===window.EventType?"removeClass":"addClass"]("hidden"),console.log("[WS] %c%s","color:green",o.message)}))},t.authme=function(){void 0!==o&&!0!==r&&(console.log("[WS] %cReconnection needed for identity change","color:teal"),o.disconnect(0),setTimeout(function(){o.connect()},100))},t.unauth=function(){void 0!==o&&!0===r&&o.emit("unauth",null,function(e){if(!e.status)return console.log("[WS] %cUnauth failed","color:red");r=!1,console.log("[WS] %cAuthentication dropped","color:brown")})},t.disconnect=function(e){void 0!==o&&(console.log("[WS] Forced disconnect (reason="+e+")"),o.disconnect(0))},t.status=function(){if(void 0===o)return setTimeout(function(){t.status()},2e3);o.emit("status",null,n(function(e){console.log("[WS] Status: ID=%s; Name=%s; Rooms=%s",e.User.id,e.User.name,e.rooms.join(","))}))},t.devquery=function(e){var i=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},a=arguments.length>2&&void 0!==arguments[2]?arguments[2]:void 0;if(void 0===o)return setTimeout(function(){t.devquery(e,i,a)},2e3);o.emit("devquery",{what:e,data:i},n(function(e){if("function"==typeof a)return a(e);console.log("[WS] DevQuery "+(e.status?"Success":"Fail"),e)}))},t.essentialElements=function(){l()},t}()}(),function(){console.log("> docReadyAlwaysRun()"),$d.triggerHandler("paginate-refresh"),$.LocalStorage.remove("cookie_consent");var e=$.LocalStorage.get("cookie_consent_v2");t=window.OAUTH_URL,$("#signin").off("click").on("click",function(){var o=$(this),i=function(e){if(e){$.Dialog.close(),$.LocalStorage.set("cookie_consent_v2",1),o.disable();var i=function(){$.Dialog.wait(!1,"Redirecting you to DeviantArt"),location.href=t+"&state="+encodeURIComponent(location.href.replace(location.origin,""))};if(-1!==navigator.userAgent.indexOf("Trident"))return i();$.Dialog.wait("Sign-in process","Opening popup window");var a=n(),s=!1,r=void 0,c=void 0;window[" "+a]=function(e,t){if(clearInterval(r),!0!==e)s=!0,$.Dialog.success(!1,"Signed in successfully"),c.close(),$.Navigation.reload(!0);else{if(t.jQuery){var n=t.$("#content").children("h1").text(),i=t.$("#content").children(".notice").html();$.Dialog.fail(!1,'<p class="align-center"><strong>'+n+"</strong></p><p>"+i+"</p>"),c.close()}else $.Dialog.fail(!1,"Sign in failed, check popup for details.");o.enable()}};try{c=$.PopupOpenCenter(t+"&state="+a,"login","450","580")}catch(e){}var l=function(){if(!s){if(-1!==document.cookie.indexOf("auth="))return window[" "+a];$.Dialog.fail(!1,"Popup-based login unsuccessful"),i()}};r=setInterval(function(){try{c&&!c.closed||(clearInterval(r),l())}catch(e){}},500),$w.on("beforeunload",function(){s=!0,c.close()}),$.Dialog.wait(!1,"Waiting for you to sign in")}};e?i(!0):$.Dialog.confirm("Privacy Notice",'<p>We must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.</p><p>If you would like to avoid these completly harmless pieces of text which are required to log in to this website, click "Decline" and continue browsing as a guest.</p><p><em>This warning will not appear again if you accept our use of persistent cookies.</em></p>',["Accept","Decline"],i)}),$("#signout").off("click").on("click",function(){$.Dialog.confirm("Sign out","Are you sure you want to sign out?",function(e){e&&($.Dialog.wait("Sign out","Signing out"),$.post("/da-auth/signout",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Sign out",this.message);$.Navigation.reload()})))})})}(),console.log("%cDocument ready handlers called","color:green"),console.groupEnd()}),$w.on("load",function(){$body.removeClass("loading")});
//# sourceMappingURL=/js/min/global.js.map
