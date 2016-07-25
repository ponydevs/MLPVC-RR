"use strict";DocReady.push(function(){function e(e){return $.each(e,function(t,i){e[t]=parseInt(i,10)}),e}function t(t){var i=e(t.split("-"));return i[1]--,i}function i(t){return e(t.split(":"))}function a(e,a,n){var o=t(e),p=i(a),r=new Date(o[0],o[1],o[2],10);return r["set"+(n?"UTC":"")+"Hours"](p[0]),r["set"+(n?"UTC":"")+"Minutes"](p[1]),r}function n(e){var t=$.mk("form").attr("id",e).append('<div class="label"><span>Season, Episode & Overall #</span><div class=input-group-3><input type="number" min="1" max="8" name="season" placeholder="Season #" required><input type="number" min="1" max="26" name="episode" placeholder="Episode #" required><input type="number" min="1" max="255" name="no" placeholder="Overall #" required></div></label>',$.mk("label").append("<span>Title (5-35 chars.)</span>",$.mk("input").attr({type:"text",maxlength:35,name:"title",placeholder:"Title",autocomplete:"off",required:!0}).patternAttr(u)),'<div class="label"><span>Air Date</span><div class="input-group-2"><input type="date" name="airdate" placeholder="YYYY-MM-DD" required><input type="time" name="airtime" placeholder="HH:MM" required></div></div><div class="notice info align-center button-here"><p>Specify when the episode will air, in <strong>your computer\'s timezone</strong>.</p></div><label><input type="checkbox" name="twoparter"> Has two parts</label><div class="notice info align-center"><p>If this is checked, only specify the episode number of the first part</p></div>');return $.mk("button").text("Set time to "+c+" this Saturday").on("click",function(e){e.preventDefault(),$(this).parents("form").find('input[name="airdate"]').val(l).next().val(c)}).appendTo(t.children(".button-here")),t}function o(e){"string"==typeof e&&(p.html(e),p.children(".empty").length?f.html(f.data("none")).next().show():f.html(f.data("list")).next().hide(),p.trigger("updatetimes")),p.find("tr[data-epid]").each(function(){var e=$(this),t=e.attr("data-epid");e.removeAttr("data-epid").data("epid",t)}),p.find(".edit-episode").add("#edit-ep").off("click").on("click",function(e){e.preventDefault();var t=$(this),i="edit-ep"===t.attr("id"),n=i?"S"+r+"E"+s:t.closest("tr").data("epid");$.Dialog.wait("Editing "+n,"Getting episode details from server"),$.post("/episode/"+n,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var e=h.clone(!0,!0);e.find("input[name=twoparter]").prop("checked",!!this.ep.twoparter),delete this.ep.twoparter,(!this.caneditid||i&&$("#reservations, #requests").find("li").length)&&e.find("input").filter('[name="season"],[name="episode"]').disable();var t=a(this.ep.airdate,this.ep.airtime,!0);this.ep.airdate=t.toAirDate(),this.ep.airtime=t.toAirTime();var n=this.epid;delete this.epid,$.each(this.ep,function(t,i){e.find("input[name="+t+"]").val(i)}),$.Dialog.request(!1,e,"Save",function(e){e.on("submit",function(e){e.preventDefault();var t=$(this).mkData(),i=a(t.airdate,t.airtime);delete t.airdate,delete t.airtime,t.airs=i.toISOString(),$.Dialog.wait(!1,"Saving changes"),$.post("/episode/edit/"+n,t,$.mkAjaxHandler(function(){return this.status?($.Dialog.wait(!1,"Updating page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)}))})})}))}),p.find(".delete-episode").off("click").on("click",function(e){e.preventDefault();var t=$(this),i=t.closest("tr").data("epid");$.Dialog.confirm("Deleting "+i,"<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>",function(e){e&&($.Dialog.wait(!1,"Removing episode"),$.post("/episode/delete/"+i,$.mkAjaxHandler(function(){return this.status?($.Dialog.wait(!1,"Reloading page",!0),void $.Navigation.reload(function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)})))})})}var p=$("#episodes").children("tbody"),r=window.SEASON,s=window.EPISODE;o.call({init:!0}),/*!
  * Timezone data string taken from:
  * http://momentjs.com/downloads/moment-timezone-with-data.js
  * version 0.4.1 by Tim Wood, licensed MIT
  */
moment.tz.add("America/Los_Angeles|PST PDT PWT PPT|80 70 70 70|010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261q0 1nX0 11B0 1nX0 SgN0 8x10 iy0 5Wp0 1Vb0 3dB0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0");var d=moment.tz(new Date,"America/Los_Angeles").set({day:"Saturday",h:8,m:30,s:0}).toDate();Date.prototype.toAirDate=function(){return this.getFullYear()+"-"+$.pad(this.getMonth()+1)+"-"+$.pad(this.getDate())},Date.prototype.toAirTime=function(){return $.pad(this.getHours())+":"+$.pad(this.getMinutes())};var l=d.toAirDate(),c=d.toAirTime(),u=window.EP_TITLE_REGEX,f=$content.children("h1").first(),m=new n("addep"),h=new n("editep");$("#add-episode").on("click",function(e){e.preventDefault(),$.Dialog.request("Add Episode",m.clone(!0,!0),"Add",function(e){e.on("submit",function(t){t.preventDefault();var i=e.find("input[name=airdate]").attr("disabled",!0).val(),n=e.find("input[name=airtime]").attr("disabled",!0).val(),o=a(i,n).toISOString(),p=$(this).mkData({airs:o});$.Dialog.wait(!1,"Adding episode to database"),$.post("/episode/add",p,$.mkAjaxHandler(function(){return this.status?($.Dialog.wait(!1,"Opening episode page",!0),void $.Navigation.visit("/episode/"+this.epid,function(){$.Dialog.close()})):$.Dialog.fail(!1,this.message)}))})})}),p.on("page-switch",function(){o()})});
//# sourceMappingURL=/js/min/episodes-manage.js.map