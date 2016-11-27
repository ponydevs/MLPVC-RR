"use strict";DocReady.push(function(){function i(i,e,t){switch(i){case"p_vectorapp":if(0===t.length&&0!==e.length){var n="app-"+e;$("."+n).removeClass(n),$(".title h1 .vectorapp-logo").remove(),$.Dialog.close()}else $.Dialog.wait(!1,"Reloading page"),$.Navigation.reload(function(){$.Dialog.close()});break;case"p_hidediscord":var a=$sidebar.find(".welcome .discord-join");t?a.length&&a.remove():a.length||$sidebar.find(".welcome .buttons").append('<a class="btn typcn discord-join" href="http://fav.me/d9zt1wv" target="_blank">Join Discord</a>'),$.Dialog.close();break;case"p_disable_ga":if(t)return $.Dialog.wait(!1,"Performing a hard reload to remove user ID from the tracking code"),window.location.reload();$.Dialog.close();break;default:$.Dialog.close()}}!function i(){var e=$(".pending-reservations");e.length&&e.on("click","button.cancel",function(){var t=$(this),n=t.prev();$.Dialog.confirm("Cancel reservation","Are you sure you want to cancel this reservation?",function(a){if(a){$.Dialog.wait(!1,"Cancelling reservation");var o=n.prop("hash").substring(1).split("-");$.post("/post/unreserve-"+o.join("/"),{FROM_PROFILE:!0},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var n=this.pendingReservations;t.closest("li").fadeOut(1e3,function(){$(this).remove(),n&&(e.replaceWith(n),Time.Update(),i())}),$.Dialog.close()}))}})})}();var e=$("#signout"),t=$(".session-list"),n=$content.find(".title .username").text().trim(),a=n===$sidebar.children(".welcome").find(".un").text().trim();t.find("button.remove").off("click").on("click",function(i){i.preventDefault();var t="Deleting session",o=$(this),s=o.closest("li"),l=s.children(".browser").text().trim(),r=s.children(".platform"),c=r.length?" on <em>"+r.children("strong").text().trim()+"</em>":"";if(0===s.index()&&s.children().last().text().indexOf("Current")!==-1)return e.triggerHandler("click");var u=s.attr("id").replace(/\D/g,"");return"undefined"==typeof u||isNaN(u)||!isFinite(u)?$.Dialog.fail(t,"Could not locate Session ID, please reload the page and try again."):void $.Dialog.confirm(t,(a?"You":n)+" will be signed out of <em>"+l+"</em>"+c+".<br>Continue?",function(i){i&&($.Dialog.wait(t,"Signing out of "+l+c),$.post("/user/sessiondel/"+u,$.mkAjaxHandler(function(){return this.status?0!==s.siblings().length?(s.remove(),$.Dialog.close()):($.Dialog.wait(!1,"Reloading page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(t,this.message)})))})}),t.find("button.useragent").on("click",function(i){i.preventDefault();var e=$(this);$.Dialog.info("User Agent string for session #"+e.parents("li").attr("id").substring(8),"<code>"+e.data("agent")+"</code>")}),$("#signout-everywhere").on("click",function(){$.Dialog.confirm("Sign out from ALL sessions","This will invalidate ALL sessions. Continue?",function(i){i&&($.Dialog.wait(!1,"Signing out"),$.post("/signout?everywhere",{username:n},$.mkAjaxHandler(function(){return this.status?($.Dialog.wait(!1,"Reloading page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)})))})}),$("#discord-verify").on("click",function(i){i.preventDefault(),$.Dialog.wait("Verify identity on Discord","Getting your token"),$.post("/user/discord-verify",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var i="/verify "+this.token,e=$.mk("div").attr("class","align-center").append("Run the following command in any of the channels:",$.mk("div").attr("class","disc-verify-code").html("<code>"+i+"</code>").on("mousedown",function(i){i.preventDefault(),$(this).select()}),$.mk("button").attr("class","darkblue typcn typcn-clipboard").text("Copy command to clipboard").on("click",function(e){e.preventDefault(),$.copy(i,e)}));$.Dialog.info(!1,e)}))}),$("#unlink").on("click",function(i){i.preventDefault();var e="Unlink account & sign out";$.Dialog.confirm(e,"Are you sure you want to unlink your account?",function(i){i&&($.Dialog.wait(e,"Removing account link"),$.post("/signout?unlink",$.mkAjaxHandler(function(){return this.status?($.Dialog.wait(!1,"Reloading page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)})))})}),$("#awaiting-deviations").children("li").children(":last-child").children("button.check").on("click",function(i){i.preventDefault();var e=$(this).parents("li"),t=e.attr("id").split("-"),n=t[0],a=t[1];$.Dialog.wait("Deviation acceptance status","Checking"),$.post("/post/lock-"+n+"/"+a,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var i=this.message;$.Dialog.wait(!1,"Reloading page"),$.Navigation.reload(function(){$.Dialog.success(!1,i,!0)})}))});var o=$("#settings").find("form").on("submit",function(e){e.preventDefault();var t=$(this),n=t.attr("action"),a=t.mkData(),o=t.find('[name="value"]'),s=o.data("orig");$.Dialog.wait("Saving setting","Please wait"),$.post(n,a,$.mkAjaxHandler(function(){return this.status?(o.is("[type=number]")?o.val(this.value):o.is("[type=checkbox]")&&(this.value=Boolean(this.value),o.prop("checked",this.value)),o.data("orig",this.value).triggerHandler("change"),void i(n.split("/").pop(),s,this.value)):$.Dialog.fail(!1,this.message)}))}).children("label");o.children("input[type=number], select").each(function(){var i=$(this);i.data("orig",i.val().trim()).on("keydown keyup change",function(){var i=$(this);i.siblings(".save").attr("disabled",i.val().trim()===i.data("orig"))})}),o.children("input[type=checkbox]").each(function(){var i=$(this);i.data("orig",i.prop("checked")).on("keydown keyup change",function(){var i=$(this);i.siblings(".save").attr("disabled",i.prop("checked")===i.data("orig"))})})});
//# sourceMappingURL=/js/min/user.js.map
