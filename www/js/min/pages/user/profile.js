"use strict";$(function(){function e(e,t,a){switch(e){case"p_vectorapp":if(0===a.length&&0!==t.length){var i="app-"+t;return $("."+i).removeClass(i),$(".title h1 .vectorapp-logo").remove(),void $.Dialog.close()}$.Navigation.reload(!0);break;case"p_hidediscord":var o=$sidebar.find(".welcome .discord-join");a?o.length&&o.remove():o.length||$sidebar.find(".welcome .buttons").append('<a class="btn typcn discord-join" href="http://fav.me/d9zt1wv" target="_blank">Join Discord</a>'),$.Dialog.close();break;case"p_avatarprov":var n={};$(".avatar-wrap.provider-"+t).each(function(){var e=$(this),t=e.attr("data-for");void 0===n[t]&&(n[t]=[]),n[t].push(e)}),$(".provider-"+t+":not(.avatar-wrap)").removeClass("provider-"+t).addClass("provider-"+a);var r=!1;$.each(n,function(e,t){$.post("/user/avatar-wrap/"+e,$.mkAjaxHandler(function(){var a=this;if(!this.status)return r=!0,$.Dialog.fail("Update avatar elements for "+e,!1);$.each(t,function(e,t){t.replaceWith(a.html)})}))}),r||$.Dialog.close();break;case"p_disable_ga":if(a)return $.Dialog.wait(!1,"Performing a hard reload to remove user ID from the tracking code"),window.location.reload();$.Dialog.close();break;case"p_hidepcg":$.Dialog.wait("Navigation","Reloading page"),$.Navigation.reload();break;default:$.Dialog.close()}}$(".personal-cg-say-what").on("click",function(e){e.preventDefault(),$.Dialog.info("About Personal Color Guides","<p>We are forever grateful to our members who help others out by fulfilling their requests on our website. As a means of giving back, we're introducing Personal Color Guides. This is a place where you can store and share colors for any of your OCs, similar to our <a href=\"/cg/\">Official Color Guide</a>.</p>\n\t\t\t<p><em>&ldquo;So where’s the catch?&rdquo;</em> &mdash; you might ask. Everyone starts with 0 slots*, which they can increase by fulfilling requests on our website, then submitting them to the club and getting them approved. You'll get your first slot after you've fulfilled 10 requests, all of which got approved by our staff to the club gallery. After that, you will be granted an additional slot for every 10 requests you finish and we approve.</p>\n\t\t\t<p><small>* Staff members get an honorary slot for free</small></p>\n\t\t\t<br>\n\t\t\t<p><strong>However</strong>, there are a few things to keep in mind:</p>\n\t\t\t<ul>\n\t\t\t\t<li>You may only add characters made by you, for you, or characters you've purchased to your Personal Color Guide. If we're asked to remove someone else’s character from your guide we'll certainly comply.</li>\n\t\t\t\t<li>Finished requests only count toward additional slots after they have been submitted to the group and have been accepted to the gallery. This is indicated by a tick symbol (<span class=\"color-green typcn typcn-tick\"></span>) on the post throughout the site.</li>\n\t\t\t\t<li>A finished request does not count towards additional slots if you were the one who request it in the first place. We're not against this behaviour generally, but allowing this would defeat the purpose of this feature: encouraging members to help others.</li>\n\t\t\t\t<li>Do not attempt to abuse the system in any way. Exploiting any bugs you may encounter instead of <a class=\"send-feedback\">reporting them</a> will be sanctioned.</li>\n\t\t\t</ul>")});var t=$(".pending-reservations");t.length&&(t.on("click","button.cancel",function(){var e=$(this),a=e.prev();$.Dialog.confirm("Cancel reservation","Are you sure you want to cancel this reservation?",function(i){if(i){$.Dialog.wait(!1,"Cancelling reservation");var o=a.prop("hash").substring(1).split("-");$.post("/post/unreserve/"+o.join("/"),{FROM_PROFILE:!0},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var a=this.pendingReservations;e.closest("li").fadeOut(1e3,function(){$(this).remove(),a&&(t.html($(a).children()),Time.Update())}),$.Dialog.close()}))}})}),t.on("click","button.fix",function(){var e=$(this).next().prop("hash").substring(1).split("-"),t=e[0],a=e[1],i=$.mk("form").attr("id","img-update-form").append($.mk("label").append($.mk("span").text("New image URL"),$.mk("input").attr({type:"text",maxlength:255,pattern:"^.{2,255}$",name:"image_url",required:!0,autocomplete:"off",spellcheck:"false"})));$.Dialog.request("Update image of "+t+" #"+a,i,"Update",function(e){e.on("submit",function(i){i.preventDefault();var o=e.mkData();$.Dialog.wait(!1,"Replacing image"),$.post("/post/set-image/"+t+"/"+a,o,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Dialog.success(!1,"Image has been updated"),$.Navigation.reload(!0)}))})})}));var a=$("#signout"),i=$(".session-list"),o=$content.children(".briefing").find(".username").text().trim(),n=o===$sidebar.children(".welcome").find(".un").text().trim();i.find("button.remove").off("click").on("click",function(e){e.preventDefault();var t="Deleting session",i=$(this).closest("li"),r=i.children(".browser").text().trim(),s=i.children(".platform"),l=s.length?" on <em>"+s.children("strong").text().trim()+"</em>":"";if(0===i.index()&&-1!==i.children().last().text().indexOf("Current"))return a.triggerHandler("click");var c=i.attr("id").replace(/\D/g,"");if(void 0===c||isNaN(c)||!isFinite(c))return $.Dialog.fail(t,"Could not locate Session ID, please reload the page and try again.");$.Dialog.confirm(t,(n?"You":o)+" will be signed out of <em>"+r+"</em>"+l+".<br>Continue?",function(e){e&&($.Dialog.wait(t,"Signing out of "+r+l),$.post("/user/sessiondel/"+c,$.mkAjaxHandler(function(){return this.status?0!==i.siblings().length?(i.remove(),$.Dialog.close()):void $.Navigation.reload(!0):$.Dialog.fail(t,this.message)})))})}),i.find("button.useragent").on("click",function(e){e.preventDefault();var t=$(this);$.Dialog.info("User Agent string for session #"+t.parents("li").attr("id").substring(8),"<code>"+t.data("agent")+"</code>")}),$("#signout-everywhere").on("click",function(){$.Dialog.confirm("Sign out from ALL sessions","This will invalidate ALL sessions. Continue?",function(e){e&&($.Dialog.wait(!1,"Signing out"),$.post("/da-auth/signout?everywhere",{username:o},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Navigation.reload(!0)})))})}),$("#unlink").on("click",function(e){e.preventDefault();var t="Unlink account & sign out";$.Dialog.confirm(t,"Are you sure you want to unlink your account?",function(e){e&&($.Dialog.wait(t,"Removing account link"),$.post("/da-auth/signout?unlink",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Navigation.reload(!0)})))})});var r=function(){$(".post-deviation-promise:not(.loading)").each(function(){var e=$(this);if(e.isInViewport()){var t=e.attr("data-post").replace("-","/"),a=e.attr("data-viewonly");e.addClass("loading"),$.get("/post/lazyload/"+t,{viewonly:a},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Cannot load "+t.replace("/"," #"),this.message);$.loadImages(this.html).then(function(t){var a=e.closest("li[id]");a.children(".image").replaceWith(t);var i=a.children(".image").find("img").attr("alt");i&&a.children(".label").removeClass("hidden").find("a").text(i)})}))}})};window._UserScroll=$.throttle(400,function(){r()}),$w.on("scroll mousewheel",window._UserScroll),window._UserScroll(),$(".awaiting-approval").on("click","button.check",function(e){e.preventDefault();var t=$(this).parents("li"),a=t.attr("id").split("-"),i=a[0],o=a[1];$.Dialog.wait("Deviation acceptance status","Checking"),$.post("/post/lock/"+i+"/"+o,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);t.remove(),$.Dialog.success(!1,this.message,!0)}))});var s=$("#settings"),l=s.find("form > label");s.on("submit","form",function(t){t.preventDefault();var a=$(this),i=a.attr("action"),o=a.mkData(),n=a.find('[name="value"]'),r=n.data("orig");$.Dialog.wait("Saving setting","Please wait"),$.post(i,o,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);n.is("[type=number]")?n.val(this.value):n.is("[type=checkbox]")&&(this.value=Boolean(this.value),n.prop("checked",this.value)),n.data("orig",this.value).triggerHandler("change"),e(i.split("/").pop(),r,this.value)}))}),l.children("input[type=number], select").each(function(){var e=$(this);e.data("orig",e.val().trim()).on("keydown keyup change",function(){var e=$(this);e.siblings(".save").attr("disabled",parseInt(e.val().trim(),10)===e.data("orig"))})}),l.children("input[type=checkbox]").each(function(){var e=$(this);e.data("orig",e.prop("checked")).on("keydown keyup change",function(){var e=$(this);e.siblings(".save").attr("disabled",e.prop("checked")===e.data("orig"))})}),l.children("select").each(function(){var e=$(this);e.data("orig",e.find("option:selected").val()).on("keydown keyup change",function(){var e=$(this),t=e.find("option:selected");e.siblings(".save").attr("disabled",t.val()===e.data("orig"))})})});
//# sourceMappingURL=/js/min/pages/user/profile.js.map