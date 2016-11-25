"use strict";DocReady.push(function(){if("undefined"!=typeof window.ROLES){var e=$("#content").children(".briefing"),t=e.find(".username").text().trim(),n=e.find(".rolelabel"),a=n.children("span").text(),i=$.mk("form").attr("id","rolemod").html('<select name="newrole" required><optgroup label="Possible roles"></optgroup></select>'),o=i.find("optgroup"),s=$("#ban-toggle"),r=$("#change-role");$.each(window.ROLES,function(e,t){o.append("<option value="+e+">"+t+"</option>")}),r.on("click",function(){$.Dialog.request("Change group",i.clone(),"Change",function(e){var n=e.find("option").filter(function(){return this.innerHTML===a}).attr("selected",!0);e.on("submit",function(a){if(a.preventDefault(),e.children("select").val()===n.attr("value"))return $.Dialog.close();var i=e.mkData();$.Dialog.wait(!1,"Moving user to the new group"),$.post("/user/newgroup/"+t,i,$.mkAjaxHandler(function(){return this.already_in===!0?$.Dialog.close():this.status?($.Dialog.wait(!1,"Reloading page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)}))})})}),s.on("click",function(){var e=(s.hasClass("un-banish")?"Un-ban":"Ban")+"ish",n=e.toLowerCase(),a=e+"ing "+t+("banish"===n?" to the moon":"");$.Dialog.request(a,$.mk("form",n+"-form").html("<p>"+e+"ing "+t+" will "+("banish"===n?"immediately sign them out of every session and won't allow them to log in again. Please, only do this if it's absolutely necessary.":"allow them to sign in to the site again.")+"</p>\n\t\t\t\t<p>You must provide a reason (5-255 chars.) for the "+n.replace(/ish$/,"")+' which will be added to the log entry and appear in the user\'s banishment history.</p>\n\t\t\t\t<input type="text" name="reason" placeholder="Enter a reason" required pattern="^.{5,255}$" value="'+e+'ing because ">\n\t\t\t\t'+("banish"===n?'<img src="/img/pre-ban.svg" alt="Sad twilight">':"")),e,function(e){e.on("submit",function(e){e.preventDefault();var i=$(this).mkData();$.Dialog.wait(!1,"Gathering the Elements of Harmony"),$.post("/user/"+n+"/"+t,i,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(a,this.message);var e=this.message;$.Dialog.wait(!1,"Reloading page",!0),$.Navigation.reload(function(){"banish"===n?$.Dialog.success(a,'<p>What had to be done, has been done.</p><img src="/img/post-ban.svg">'):$.Dialog.success(a,e,!0)})}))})})})}});
//# sourceMappingURL=/js/min/user-manage.js.map
