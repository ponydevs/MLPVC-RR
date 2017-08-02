"use strict";$(function(){var e=!1,t=$("#filter-form");t.on("submit",function(e){e.preventDefault();var n=t.find('[name="type"] option:selected'),s=n.val(),i=t.find('[name="by"]').val().trim(),a=(s.length?n.text().replace("of type ","")+" entries":"")+(i.length?(s.length?"":"entries")+" by "+i:""),l=!!a.length&&t.serialize();t.find("button[type=reset]").attr("disabled",!1===l),!1!==l?$.Dialog.wait("Navigation","Looking for "+a.replace(/</g,"&lt;")):$.Dialog.success("Navigation","Search terms cleared"),$.toPage.call({query:l},window.location.pathname.replace(/\d+($|\?)/,"1$1"),!0,!0,!1,function(){return!1!==l?/^Page \d+/.test(document.title)?a+" - "+document.title:document.title.replace(/^.*( - Page \d+)/,a+"$1"):document.title.replace(/^.* - (Page \d+)/,"$1")})}).on("reset",function(e){e.preventDefault(),t.find('[name="type"]').val(""),t.find('[name="by"]').val(""),t.triggerHandler("submit")});var n=$("#logs");n.find("tbody").off("page-switch").on("page-switch",function(){$(this).children().each(function(){var n=$(this);n.find(".expand-section").off("click").on("click",function(){var t=$(this);if(t.hasClass("typcn-minus"))t.toggleClass("typcn-minus typcn-plus").next().stop().slideUp();else if(1===t.next().length)t.toggleClass("typcn-minus typcn-plus").next().stop().slideDown();else{if(e)return!1;e=!0,t.removeClass("typcn-minus typcn-plus").addClass("typcn-refresh");var s=parseInt(n.children().first().text()),i=function(){t.addClass("typcn-times color-red").css("cursor","not-allowed").off("click")};$.post("/admin/logs/details/"+s,$.mkAjaxHandler(function(){if(!this.status)return!0===this.unlickable&&t.replaceWith(t.text().trim()),$.Dialog.fail("Log entry details",this.message),i();var e=$.mk("div").attr("class","expandable-section").css("display","none");$.each(this.details,function(t,n){var s=void 0,i=$.mk("strong").html(n[0]+": ");"string"==typeof n[2]&&i.addClass("color-"+n[2]),null===n[1]?s=$.mk("em").addClass("color-darkblue").text("empty"):"boolean"==typeof n[1]?s=$.mk("span").addClass("color-"+(n[1]?"green":"red")).text(n[1]?"yes":"no"):$.isArray(n[1])?(s=void 0,i.html(i.html().replace(/:\s$/,""))):s=n[1],e.append($.mk("div").append(i,s))}),e.insertAfter(t).slideDown(),Time.Update(),t.addClass("typcn-minus color-darkblue")})).always(function(){e=!1,t.removeClass("typcn-refresh")}).fail(i)}}),n.find(".server-init").off("click").on("click",function(){t.find('[name="by"]').val($(this).text().trim()),t.triggerHandler("submit")})}),$.Dialog.close()}).trigger("page-switch").on("click",".dynt-el",function(){if($w.width()>=650)return!0;var e=$(this).parent(),t=e.parent(),n=t.children(".ip");n.children("a").length&&(n=n.clone(!0,!0)).children(".self").html(function(){return $(this).text()});var s=n.contents(),i=$.mk("span").attr("class","modal-ip").append("<br><b>Initiator:</b> ",s.eq(0));s.length>1&&i.append("<br><b>IP Address:</b> "+s.get(2).textContent),$.Dialog.info("Hidden details of entry #"+t.children(".entryid").text(),$.mk("div").append("<b>Timestamp:</b> "+e.children("time").html().trim().replace(/<br>/," "),i))});var s=[{className:"darkblue",showins:!0,showdel:!0,title:"diff"},{className:"green",showins:!0,showdel:!1,title:"new"},{className:"red",showins:!1,showdel:!0,title:"old"}];n.on("click contextmenu",".btn.view-switch",function(e){var t="contextmenu"===e.type;if(t&&e.shiftKey)return!0;e.preventDefault();for(var n=$(e.target),i=n.next(),a=n.attr("class").match(/\b(darkblue|green|red)\b/)[1],l=void 0,r=0;r<s.length;r++)s[r].className===a&&(l=s[r+(t?-1:1)]);void 0===l&&(l=s[t?s.length-1:0]),i.find("ins")[l.showins?"show":"hide"](),i.find("del")[l.showdel?"show":"hide"](),i[l.showins&&l.showdel?"removeClass":"addClass"]("no-colors"),i[0===i.contents().filter(function(){return!/^(del|ins)$/.test(this.nodeName.toLowerCase())||"none"!==this.style.display}).length?"addClass":"removeClass"]("empty"),n.removeClass(a).addClass(l.className).text(l.title)})});
//# sourceMappingURL=/js/min/admin-logs.js.map
