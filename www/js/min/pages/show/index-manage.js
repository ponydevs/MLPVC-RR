"use strict";!function(){var t=$("#content").find("table"),a=window.SEASON,o=window.EPISODE;
/*!
  * Timezone data string taken from:
  * http://momentjs.com/downloads/moment-timezone-with-data.js
  * version 0.4.1 by Tim Wood, licensed MIT
  */moment.tz.add("America/Los_Angeles|PST PDT PWT PPT|80 70 70 70|010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261q0 1nX0 11B0 1nX0 SgN0 8x10 iy0 5Wp0 1Vb0 3dB0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0");var n=function(t){return moment.tz(t,"America/Los_Angeles").set({day:"Saturday",h:8,m:30,s:0}).local()},e=n(new Date),i=$.momentToYMD(e),s=$.momentToHM(e),l=e.format("dddd"),r=window.EP_TITLE_REGEX;function d(t){var e=$.mk("form").attr("id",t).append('<div class="label episode-only">\n\t\t\t\t<span>Season, Episode & Overall #</span>\n\t\t\t\t<div class=input-group-3>\n\t\t\t\t\t<input type="number" min="1" max="9" name="season" placeholder="Season #" required>\n\t\t\t\t\t<input type="number" min="1" max="26" name="episode" placeholder="Episode #" required>\n\t\t\t\t\t<input type="number" min="1" max="255" name="no" placeholder="Overall #" required>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<label class="episode-only"><input type="checkbox" name="twoparter"> Has two parts</label>\n\t\t\t<div class="notice info align-center episode-only">\n\t\t\t\t<p>If this is checked, enter the episode number of the first part</p>\n\t\t\t</div>\n\t\t\t<div class="label movie-only">\n\t\t\t\t<span>Overall movie number</span>\n\t\t\t\t<input type="number" min="1" max="26" name="episode" placeholder="Overall #" required>\n\t\t\t</div>\n\t\t\t<input class="movie-only" type="hidden" name="season" value="0">',$.mk("label").append("<span>Title (5-35 chars.)</span>",$.mk("input").attr({type:"text",minlength:5,name:"title",placeholder:"Title",autocomplete:"off",required:!0}).patternAttr(r)),'<div class="notice info align-center movie-only">\n\t\t\t\t<p>Include "Equestria Girls: " if applicable. Prefixes don\'t count towards the character limit.</p>\n\t\t\t</div>\n\t\t\t<div class="label">\n\t\t\t\t<span>Air date & time</span>\n\t\t\t\t<div class="input-group-2">\n\t\t\t\t\t<input type="date" name="airdate" placeholder="YYYY-MM-DD" required>\n\t\t\t\t\t<input type="time" name="airtime" placeholder="HH:MM" required>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class="notice info align-center button-here">\n\t\t\t\t<p>Specify the <span class="episode-only">episode</span><span class="movie-only">movie</span>\'s air date and time in <strong>your computer\'s timezone</strong>.</p>\n\t\t\t</div>\n\t\t\t<div class="label">\n\t\t\t\t<span>Notes (optional, 1000 chars. max)</span>\n\t\t\t\t<div class="ace_editor"></div>\n\t\t\t</div>');return $.mk("button").attr("class","episode-only").text("Set time to "+s+" this "+l).on("click",function(t){t.preventDefault(),$(this).parents("form").find('input[name="airdate"]').val(i).next().val(s)}).appendTo(e.children(".button-here")),e}var p=new d("addep"),c=new d("editep");function m(t){t.preventDefault();var e=$(this),i="edit-ep"===e.attr("id")?a?"S"+a+"E"+o:"Movie#"+o:e.closest("tr").attr("data-epid"),n=/^Movie/.test(i);$.Dialog.wait("Editing "+i,"Getting "+(n?"movie":"episode")+" details from server"),n&&(i="S0E"+i.split("#")[1]),$.API.get("/episode/"+i,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var i=c.clone(!0,!0);i.find(n?".episode-only":".movie-only").remove(),n||i.find("input[name=twoparter]").prop("checked",!!this.ep.twoparter),delete this.ep.twoparter;var t=moment(this.ep.airs);this.ep.airdate=$.momentToYMD(t),this.ep.airtime=$.momentToHM(t);var o=this.epid;delete this.epid;var s=this.ep.notes;delete this.ep.notes,$.each(this.ep,function(t,e){i.find("input[name="+t+"]").val(e)}),$.Dialog.request(!1,i,"Save",function(t){var n=void 0;try{var e="html",i=t.find(".ace_editor").get(0),a=ace.edit(i);(n=$.aceInit(a,e)).setMode(e),n.setUseWrapMode(!0),s&&n.setValue(s)}catch(t){console.error(t)}t.on("submit",function(t){t.preventDefault();var e=$(this).mkData(),i=$.mkMoment(e.airdate,e.airtime);delete e.airdate,delete e.airtime,e.airs=i.toISOString(),e.notes=n.getValue(),$.Dialog.wait(!1,"Saving changes"),$.API.put("/episode/"+o,e,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Dialog.wait(!1,"Updating page",!0),$.Navigation.reload()}))})})}))}$("#add-episode, #add-movie").on("click",function(t){t.preventDefault();var l=/movie/.test(this.id),e=p.clone(!0,!0);e.find(l?".episode-only":".movie-only").remove(),l||e.prepend($.mk("div").attr("class","align-center").html($.mk("button").attr("class","typcn typcn-flash blue").text("Pre-fill based on last added").on("click",function(t){var e=$(t.target),i=e.closest("form");e.disable(),$.API.get("/episode/prefill",$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var t=n(this.airday);$.each({airdate:$.momentToYMD(t),airtime:$.momentToHM(t),episode:this.episode,season:this.season,no:this.no},function(t,e){i.find("[name="+t+"]").val(e)})})).always(function(){e.enable()})}))),$.Dialog.request("Add "+(l?"Movie":"Episode"),e,"Add",function(o){var s=void 0;try{var t="html",e=o.find(".ace_editor").get(0),i=ace.edit(e);(s=$.aceInit(i,t)).setMode(t),s.setUseWrapMode(!0)}catch(t){console.error(t)}o.on("submit",function(t){t.preventDefault();var e=o.find("input[name=airdate]").attr("disabled",!0).val(),i=o.find("input[name=airtime]").attr("disabled",!0).val(),n=$.mkMoment(e,i).toISOString(),a=$(this).mkData({airs:n});a.notes=s.getValue(),$.Dialog.wait(!1,"Adding "+(l?"movie":"episode")+" to database"),$.API.post("/episode",a,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Dialog.wait(!1,"Opening "+(l?"movie":"episode")+" page",!0),$.Navigation.visit(this.url)}))})})}),$content.on("click","#edit-ep",m),t.on("click",".edit-episode",m).on("click",".delete-episode",function(t){t.preventDefault();var e=$(this).closest("tr").data("epid"),i=/^Movie/.test(e);$.Dialog.confirm("Deleting "+e,"<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the "+(i?"movie":"episode")+", too.</p><p>Are you sure you want to delete it?</p>",function(t){t&&($.Dialog.wait(!1,"Removing episode"),$.API.delete("/episode/"+e,$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);$.Navigation.reload(!0)})))})})}();
//# sourceMappingURL=/js/min/pages/show/index-manage.js.map
