"use strict";$(function(){function t(){(s=$("#toggle-copy-hash")).length&&s.off("display-update").on("display-update",function(){l=!$.LocalStorage.get("leavehash"),s.attr("class","blue typcn typcn-"+(l?"tick":"times")).text("Copy # with color codes: "+(l?"En":"Dis")+"abled")}).trigger("display-update").off("click").on("click",function(t){t.preventDefault(),l?$.LocalStorage.set("leavehash",1):$.LocalStorage.remove("leavehash"),s.triggerHandler("display-update")})}function e(){var t=$(".tags").children("span.tag");t.each(function(){var t=$(this),e=t.attr("title"),o=t.attr("class").match(/typ-([a-z]+)(?:\s|$)/);if(o=o?" qtip-tag-"+o[1]:"",!e){var i=t.text().trim();e=/^s\d+e\d+(-\d+)?$/i.test(i)?i.toUpperCase():$.capitalize(t.text().trim(),!0)}e&&t.qtip({content:d?{text:"Click to quick search",title:e}:{text:"",title:e},position:{my:"bottom center",at:"top center",viewport:!0},style:{classes:"qtip-tag"+o}})}),t.css("cursor","pointer").off("click").on("click",function(t){t.preventDefault();var e=this.innerHTML.trim();c.length?(c.find('input[name="q"]').val(e),c.triggerHandler("submit")):$.Navigation.visit("/cg"+(n?"/eqg":"")+"/1?q="+encodeURIComponent(e))}),$("ul.colors").children("li").find(".valid-color").each(function(){var t=$(this);t.hasAttr("data-hasqtip")&&t.data("qtip").destroy();var e="Click to copy HEX color code to clipboard<br>Shift+Click to view RGB values",o=t.attr("title");return t.is(":empty")&&(e="No color to copy"),t.qtip({content:{text:e,title:o},position:{my:"bottom center",at:"top center",viewport:!0},style:{classes:"qtip-see-thru"}}),!0}).off("mousedown touchstart click").on("click",function(t){t.preventDefault();var e=$(this),o=e.html().trim();if(t.shiftKey){var i=$.hex2rgb(o),a=e.closest("li"),n=[r?$content.children("h1").text():a.parents("li").children().last().children("strong").text().trim(),a.children().first().text().replace(/:\s+$/,""),e.attr("oldtitle")];return $.Dialog.info("RGB values for color "+o,'<div class="align-center">'+n.join(" &rsaquo; ")+'<br><span style="font-size:1.2em">rgb(<code class="color-red">'+i.r+'</code>, <code class="color-green">'+i.g+'</code>, <code class="color-darkblue">'+i.b+"</code>)</span></div>")}l||(o=o.replace("#","")),$.copy(o)}).filter(":not(.ctxmenu-bound)").ctxmenu([{text:"Copy HEX color code",icon:"clipboard",default:!0,click:function(){$(this).triggerHandler("click")}},{text:"View RGB values",icon:"brush",click:function(){$(this).triggerHandler({type:"click",shiftKey:!0})}}],function(t){return"Color: "+t.attr("oldtitle")}).on("mousedown",function(t){t.shiftKey&&t.preventDefault()}),$(".cm-direction:not(.tipped)").each(function(){var t=$(this),e=t.closest("li").attr("id").substring(1),o=new Image,i=new Image,a="/cg/v/"+e+"d.svg?t="+parseInt((new Date).getTime()/1e3),n=t.attr("data-cm-preview");setTimeout(function(){o.src=a,i.src=n},1),t.addClass("tipped").qtip({content:{text:$.mk("span").attr("class","cm-dir-image").backgroundImageUrl(a).append($.mk("div").attr("class","img cm-dir-"+t.attr("data-cm-dir")).backgroundImageUrl(n))},position:{my:"bottom center",at:"top center",viewport:!0},style:{classes:"qtip-link"}})})}function o(){$(".getswatch").off("click").on("click",i),e(),t()}function i(t){t.preventDefault();var e=$(this).closest("[id^=p]"),o=e.attr("id").substring(1),i=(r?$content.children("h1"):e.find("strong").first()).text().trim(),a=navigator&&navigator.userAgent&&/Macintosh/i.test(navigator.userAgent)?"<kbd>⌘</kbd><kbd>F12</kbd>":"<kbd>Ctrl</kbd><kbd>F12</kbd>",n=$.mk("div").html("<div class='hidden section ai'>\n\t\t\t\t\t<h4>How to import swatches to Adobe Illustrator</h4>\n\t\t\t\t\t<ul>\n\t\t\t\t\t\t<li>Because Illustator uses a proprietary format for swatch files, you must download a script <a href='/dist/Import Swatches from JSON.jsx?v=1.4' download='Import Swatches from JSON.jsx' class='btn typcn typcn-download'>by clicking here</a> to be able to import them from our site. Once you downloaded it, place it in an easy to find location, because you'll need to use it every time you want to import colors.<br><small>If you place it in <code>&hellip;\\Adobe\\Adobe Illustrator *\\Presets\\*\\Scripts</code> it'll be available as one of the options in the Scripts submenu.</small></li>\n\t\t\t\t\t\t<li>Once you have the script, <a href=\"/cg/v/"+o+'s.json" class="btn blue typcn typcn-download">click here</a> to download the <code>.json</code> file that you\'ll need to use for the import.</li>\n\t\t\t\t\t\t<li>Now that you have the 2 files, open Illustrator, create/open a document, then go to <strong>File &rsaquo; Scripts &rsaquo; Other Script</strong> (or press '+a+') then find the file with the <code>.jsx</code> extension (the one you first downloaded). A dialog will appear telling you what to do next.</li>\n\t\t\t\t\t</ul>\n\t\t\t\t\t<div class="responsive-embed">\n\t\t\t\t\t\t<iframe src="https://www.youtube.com/embed/oobQZ2xiDB8" allowfullscreen async defer></iframe>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t\t<div class=\'hidden section inkscape\'>\n\t\t\t\t\t<h4>How to import swatches to Inkscape</h4>\n\t\t\t\t\t<p>Download <a href=\'/cg/v/'+o+'s.gpl\' class=\'btn blue typcn typcn-download\'>this file</a> and place it in the <code>&hellip;\\Inkscape<wbr>\\<wbr>share<wbr>\\<wbr>palettes</code> folder. If you don’t plan on using the other swatches, deleting them should make your newly imported swatch easier to find.</p>\n\t\t\t\t\t<p>You will most likely have to restart Inkscape for the swatch to show up in the <em>Swatches</em> (<kbd>F6</kbd>) tool window’s menu.</p>\n\t\t\t\t\t<div class="responsive-embed">\n\t\t\t\t\t\t<iframe src="https://www.youtube.com/embed/zmaJhbIKQqM" allowfullscreen async defer></iframe>\n\t\t\t\t\t</div>\n\t\t\t\t</div>'),l=$.mk("select").attr("required",!0).html('<option value="" selected style="display:none">Choose one</option><option value="inkscape">Inkscape</option><option value="ai">Adobe Illustrator</option>').on("change",function(){var t=$(this),e=t.val(),o=t.parent().next().children().addClass("hidden");console.log(e),e&&o.filter("."+e).removeClass("hidden")}),s=$.mk("form").attr("id","swatch-save").append($.mk("label").attr("class","align-center").append("<span>Choose your drawing program:</span>",l),n);$.Dialog.info("Download swatch file for "+i,s)}var a=$(".appearance-list"),n=window.EQG,r=!!window.AppearancePage,l=!$.LocalStorage.get("leavehash"),s=void 0;window.copyHashToggler=function(){t()},window.copyHashEnabled=function(){return l};var c=$("#search-form"),d=c.length;window.tooltips=function(){e()},a.filter("#list").on("page-switch",o),$d.on("paginate-refresh",o),o(),c.on("submit",function(t,e){t.preventDefault();var o=$(this),i=o.find("input[name=q]"),a=i.val().replace(/[^\w\s*?]/g,""),n=a.trim().length>0,r=o.serialize();o.find("button[type=reset]").attr("disabled",!n),i.val(a),e?$.Dialog.wait("Navigation","Loading appearance page"):n?$.Dialog.wait("Navigation","Searching for <code>"+a.replace(/</g,"&lt;")+"</code>"):$.Dialog.success("Navigation","Search terms cleared"),$.toPage.call({query:r,btnl:e},window.location.pathname.replace(/\d+($|\?)/,"1$1"),!0,!0,!1,function(){return $(".qtip").each(function(){var t=$(this);t.data("qtip").destroy(),t.remove()}),n?/^Page \d+/.test(document.title)?a+" - "+document.title:document.title.replace(/^.*( - Page \d+)/,a+"$1"):document.title.replace(/^.* - (Page \d+)/,"$1")}).then(function(){$.Dialog.close()}).catch(function(){$.Dialog.close()})}).on("reset",function(t){t.preventDefault();var e=$(this);e.find("input[name=q]").val(""),e.triggerHandler("submit")}).on("click",".sanic-button",function(){c.triggerHandler("submit",[!0])})},function(){$(".qtip").each(function(){var t=$(this);t.data("qtip").destroy(),t.remove()})});
//# sourceMappingURL=/js/min/colorguide.js.map
