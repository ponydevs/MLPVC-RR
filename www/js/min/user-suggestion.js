"use strict";DocReady.push(function(){var t=$(".pending-reservations");t.on("click","#suggestion",function(e){e.preventDefault(),$.Dialog.info("Suggest a reservation",'<p>Feeel like making a vector but don\'t have any screencap in mind? Why not fulfill a request?</p>\n\t\t\t<p>With this tool you can get a random request from the site, and you can choose to reserve it or get a different suggestion. It\'s all up to you.</p>\n\t\t\t<div class="align-center"><button id="suggestion-press" class="btn large orange typcn typcn-lightbulb">Give me a suggestion</button></button>',function(){var e=$("#dialogContent").find("#suggestion-press"),n=$.mk("ul","suggestion-output").insertAfter(e),i=$.mk("div").addClass("notice fail").hide().text("The image apparently failed to load - just click the button again to get a different suggestion.").insertAfter(n),s=void 0;e.on("click",function(o){o.preventDefault(),i.hide(),s&&(s.close(),s=void 0),$.post("/user/suggestion",$.mkAjaxHandler(function(){if(!this.status)return this.limithit&&(e.disable(),console.log(n),n.remove()),$.Dialog.fail(!1,this.message);var o=$(this.suggestion),a=o.attr("id");o.find("img").on("error",function(){i.show()}),o.find(".screencap > a").on("click",function(t){var e=$(this);s=$.PopupOpenCenter(e.attr("href"),"suggestion_popup",800,450),t.preventDefault()}),o.find(".reserve-request").on("click",function(){var e=$(this);$.post("/post/reserve-"+a.replace("-","/"),{SUGGESTED:!0},$.mkAjaxHandler(function(){return this.status?(e.replaceWith(this.button),void t.html($(this.pendingReservations).children())):$.Dialog.fail(!1,this.message)}))}),n.html(o)}))})})})});
//# sourceMappingURL=/js/min/user-suggestion.js.map
