"use strict";DocReady.push(function(){!function(){function t(t){t=t.replace(/<img[^>]+>/g,"").match(e);var o={};$.each(t,function(t,e){var n=e.match(i);n&&"undefined"==typeof o[n[1]]&&(o[n[1]]=!0)});var n=Object.keys(o);return n?($.Dialog.wait("Bulk approve posts","Attempting to approve "+n.length+" post"+(1!==n.length?"s":"")),void $.post("/admin/mass-approve",{ids:n.join(",")},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);var t=this.message,e=function(){t?$.Dialog.success(!1,t,!0):$.Dialog.close()};this.reload?($.Dialog.wait(!1,"Reloading page"),$.Navigation.reload(e)):e()}))):$.Dialog.fail("No deviations found on the pasted page.")}$("#bulk-how").on("click",function(){$.Dialog.info("How to approve posts in bulk",'<p>This tool is easier to use than you would think. Here\'s how it works:</p>\n\t\t\t\t<ol>\n\t\t\t\t\t<li>\n\t\t\t\t\t\tIf you have the group watched, visit <a href="http://www.deviantart.com/notifications/#view=groupdeviations%3A17450764" target="_blank" rel=\'noopener\'>this link</a><br>\n\t\t\t\t\t\tIf not, go to the <a href="http://mlp-vectorclub.deviantart.com/messages/?log_type=1&instigator_module_type=0&instigator_roleid=1276365&instigator_username=&bpp_status=4&display_order=desc" target="_blank" rel=\'noopener\'>Processed Deviations queue</a>\n\t\t\t\t\t</li>\n\t\t\t\t\t<li>Once there, press <kbd>Ctrl</kbd><kbd>A</kbd> (which will select the entire page)</li>\n\t\t\t\t\t<li>Now press <kbd>Ctrl</kbd><kbd>C</kbd> (copying the selected content)</li>\n\t\t\t\t\t<li>Return to this page and click into the box below (you should see a blinking cursor afterwards)</li>\n\t\t\t\t\t<li>Hit <kbd>Ctrl</kbd><kbd>V</kbd></li> (to paste what yo ujust copied)\n\t\t\t\t\t<li>Repeat these steps if there are multiple pages of results.</li>\n\t\t\t\t</ol>\n\t\t\t\t<p>The script will look for any deviation links in the HTML code of the page, which it then sends over to the server to mark them as approved if they were used to finish posts on the site.</p>')}),$(".mass-approve").children(".textarea").on("paste",function(e){var i=void 0,o=void 0,n=void 0,a=this;if(e.originalEvent.clipboardData&&e.originalEvent.clipboardData.types&&e.originalEvent.clipboardData.getData&&(i=e.originalEvent.clipboardData.types,i instanceof DOMStringList&&i.contains("text/html")||i.indexOf&&i.indexOf("text/html")!==-1))return o=e.originalEvent.clipboardData.getData("text/html"),t(o),e.stopPropagation(),e.preventDefault(),!1;for(n=document.createDocumentFragment();a.childNodes.length>0;)n.appendChild(a.childNodes[0]);return function e(i,o){if(i.childNodes&&i.childNodes.length>0){var n=i.innerHTML;i.innerHTML="",i.appendChild(o),t(n)}else setTimeout(function(){e(i,o)},20)}(a,n),!0});var e=/(?:[A-Za-z\-\d]+\.)?deviantart\.com\/art\/(?:[A-Za-z\-\d]+-)?(\d+)/g,i=/\/(?:[A-Za-z\-\d]+-)?(\d+)$/}();var t=$(".recent-posts"),e=!1;window._AdminRecentScroll=function(){if(!e&&t.isInViewport()){var i=t.children("div");i.is(":empty")&&(e=!0,$.post("/admin/recent-posts",$.mkAjaxHandler(function(){return this.status?void i.html(this.html):i.append('<div class="notice fail align-center">This section failed to load.</div>')})))}},$w.on("scroll",window._AdminRecentScroll),window._AdminRecentScroll()},function(){$w.off("scroll",window._AdminRecentScroll)});
//# sourceMappingURL=/js/min/admin.js.map
