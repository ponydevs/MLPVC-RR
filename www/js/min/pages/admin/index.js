"use strict";!function(){!function(){$("#bulk-how").on("click",function(){$.Dialog.info("How to approve posts in bulk",'<p>This tool is easier to use than you would think. Here\'s how it works:</p>\n\t\t\t\t<ol>\n\t\t\t\t\t<li>\n\t\t\t\t\t\tIf you have the group watched, visit <a href="http://www.deviantart.com/notifications/#view=groupdeviations%3A17450764" target="_blank" rel=\'noopener\'>this link</a><br>\n\t\t\t\t\t\tIf not, go to the <a href="http://mlp-vectorclub.deviantart.com/messages/?log_type=1&instigator_module_type=0&instigator_roleid=1276365&instigator_username=&bpp_status=4&display_order=desc" target="_blank" rel=\'noopener\'>Processed Deviations queue</a>\n\t\t\t\t\t</li>\n\t\t\t\t\t<li>Once there, press <kbd>Ctrl</kbd><kbd>A</kbd> (which will select the entire page)</li>\n\t\t\t\t\t<li>Now press <kbd>Ctrl</kbd><kbd>C</kbd> (copying the selected content)</li>\n\t\t\t\t\t<li>Return to this page and click into the box below (you should see a blinking cursor afterwards)</li>\n\t\t\t\t\t<li>Hit <kbd>Ctrl</kbd><kbd>V</kbd></li> (to paste what you just copied)\n\t\t\t\t\t<li>Repeat these steps if there are multiple pages of results.</li>\n\t\t\t\t</ol>\n\t\t\t\t<p>The script will look for any deviation links in the HTML code of the page, which it then sends over to the server to mark them as approved if they were used to finish posts on the site.</p>')}),$(".mass-approve").children(".textarea").on("paste",function(t){var e=void 0,a=void 0,i=this;if(t.originalEvent.clipboardData&&t.originalEvent.clipboardData.types&&t.originalEvent.clipboardData.getData&&((e=t.originalEvent.clipboardData.types)instanceof DOMStringList&&e.contains("text/html")||e.indexOf&&-1!==e.indexOf("text/html")))return n(t.originalEvent.clipboardData.getData("text/html")),t.stopPropagation(),t.preventDefault(),!1;for(a=document.createDocumentFragment();0<i.childNodes.length;)a.appendChild(i.childNodes[0]);return function t(e,a){if(e.childNodes&&0<e.childNodes.length){var i=e.innerHTML;e.innerHTML="",e.appendChild(a),n(i)}else setTimeout(function(){t(e,a)},20)}(i,a),!0});var i=$(".recent-posts ul"),o=/(?:[A-Za-z\-\d]+\.)?deviantart\.com\/art\/(?:[A-Za-z\-\d]+-)?(\d+)|fav\.me\/d([a-z\d]{6,})/g,s=/\/(?:[A-Za-z\-\d]+-)?(\d+)$/,r=/fav\.me\/d([a-z\d]{6,})/;function n(t){t=t.replace(/<img[^>]+>/g,"").match(o);var n={};$.each(t,function(t,e){var a=e.match(s);if(a&&void 0===n[a[1]])n[a[1]]=!0;else if(a=e.match(r)){var i=parseInt(a[1],36);void 0===n[i]&&(n[i]=!0)}});var e=Object.keys(n);if(!e)return $.Dialog.fail("No deviations found on the pasted page.");$.Dialog.wait("Bulk approve posts","Attempting to approve "+e.length+" post"+(1!==e.length?"s":""));var a=e.join(",");$.API.post("/admin/mass-approve",{ids:a},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail(!1,this.message);this.html&&(i.html(this.html),l()),this.message?$.Dialog.success(!1,this.message,!0):$.Dialog.close()}))}}();var n=new IntersectionObserver(function(t){t.forEach(function(t){if(t.isIntersecting){var e=t.target;n.unobserve(e);var a=e.dataset.post.replace("-","/"),i=e.dataset.viewonly;$.get("/post/lazyload/"+a,{viewonly:i},$.mkAjaxHandler(function(){if(!this.status)return $.Dialog.fail("Cannot load "+a.replace("/"," #"),this.message);$.loadImages(this.html).then(function(t){$(e).closest(".image").replaceWith(t.$el)})}))}})}),a=new IntersectionObserver(function(t){t.forEach(function(t){if(t.isIntersecting){var e=t.target;n.unobserve(e);var a=$.mk("a"),i=new Image;i.src=e.dataset.src,a.attr("href",e.dataset.href).append(i),$(i).on("load",function(){$(e).closest(".image").html(a)})}})}),i=new IntersectionObserver(function(t){t.forEach(function(t){if(t.isIntersecting){var e=t.target;i.unobserve(e);var a=new Image;a.src=e.dataset.src,a.classList="avatar",$(a).on("load",function(){$(e).replaceWith(a)})}})});function l(){$(".post-deviation-promise").each(function(t,e){return n.observe(e)}),$(".post-image-promise").each(function(t,e){return a.observe(e)}),$(".user-avatar-promise").each(function(t,e){return i.observe(e)})}l()}();
//# sourceMappingURL=/js/min/pages/admin/index.js.map
