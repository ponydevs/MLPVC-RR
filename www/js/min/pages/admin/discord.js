"use strict";$(function(){var e=window.USERNAME_REGEX,t=$("#member-search").children("input"),i=$(".discord-members"),n=$("#manage-area"),a=$("#linkof-member"),s=$.mk("div").append($.mk("div").attr("class","bind-status").html('<h3>This member is bound to:</h3><div class="boundto"></div>'),$.mk("div").attr("class","do-bind").append("<h3>Change binding:</h3>",$.mk("form").append($.mk("input").attr({type:"text",placeholder:"Username",required:!0}).patternAttr(e),"<br>",$.mk("button").attr("class","green typcn typcn-refresh").text("Change"),$.mk("button").attr("class","red typcn typcn-user-delete").text("Remove").on("click",function(e){e.preventDefault();var t=i.find(".selected").attr("id").split("-")[1];if(isNaN(t))return $.Dialog.fail(!1,"Cannot find Discord user ID");$.Dialog.confirm("Remove binding","Are you sure you want to remove this binding?",function(e){e&&($.Dialog.wait(!1),$.post("/admin/discord/member-link/del/"+t,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$("#member-"+t).removeClass("bound"),r(t),$.Dialog.close()})))})})).on("submit",function(e){e.preventDefault();var t=$(this).find("input").val();$.Dialog.confirm("Change binding","Are you sure you want to bind this member to <strong>"+t+"</strong>?",function(e){if(e){var n=i.find(".selected").attr("id").split("-")[1];$.Dialog.wait(!1),$.post("/admin/discord/member-link/set/"+n,{to:t},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$("#member-"+n).addClass("bound"),r(n),$.Dialog.close()}))}})}))).children();function r(e){n.addClass("loading"),$.post("/admin/discord/member-link/get/"+e,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Loading binding data",this.message);var e=s.clone(!0,!0);void 0!==this.boundto&&e.filter(".bind-status").children(".boundto").append(this.boundto),n.empty().append(e).removeClass("loading")}))}t.on("keyup change",function(){var e=$(this).val().trim().toLowerCase();0===e.length?i.children().removeClass("hidden").each(function(){$(this).find(".user-data").children().each(function(){var e=$(this);e.html(e.text())})}):i.children().addClass("hidden").filter(function(){var t=$(this),i=t.find(".user-data").children(),n=e.split("#"),a=-1!==t.text().toLowerCase().replace(/\s+#/g,"#").indexOf(e),s=i.eq(0),r=i.eq(1);return s.html(s.text().replace(new RegExp("("+$.escapeRegex(n[0])+")","i"),"<mark>$1</mark>")),r.html(r.text().replace(new RegExp("("+$.escapeRegex(n[0])+("string"==typeof n[1]?$.escapeRegex("#"+n[1]):"")+")","i"),"<mark>$1</mark>")),a}).removeClass("hidden"),0===i.children(":not(.hidden)").length?i.addClass("empty"):i.removeClass("empty")}),i.on("click","li",function(e){e.preventDefault();var t=$(this),i=t.attr("id").split("-")[1],n=t.find(".user-data").children().first().text();t.addClass("selected").siblings().removeClass("selected"),a.empty().append(" of ",$.mk("span").attr("class","color-blue").text(n)),r(i)}),$("#rerequest-members").on("click",function(e){e.preventDefault(),$.Dialog.confirm("Re-request member list","You are about to update the member list. This will update the all locally stored data about the members except for the bindings. Continue?",function(e){e&&($.Dialog.wait(!1),$.post("/admin/discord/member-list",{update:!0},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);i.html(this.list),$.Dialog.close()})))})}),$.post("/admin/discord/member-list",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Loading member list",this.message);i.html(this.list).removeClass("loading")}))});
//# sourceMappingURL=/js/min/pages/admin/discord.js.map
