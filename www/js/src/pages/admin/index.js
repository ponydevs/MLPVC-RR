/* global DocReady,HandleNav,Sortable,DOMStringList,$w,IntersectionObserver */
$(function(){
	'use strict';

	// Mass-aprove posts
	(function(){
		$('#bulk-how').on('click',function(){
			$.Dialog.info('How to approve posts in bulk',
				`<p>This tool is easier to use than you would think. Here's how it works:</p>
				<ol>
					<li>
						If you have the group watched, visit <a href="http://www.deviantart.com/notifications/#view=groupdeviations%3A17450764" target="_blank" rel='noopener'>this link</a><br>
						If not, go to the <a href="http://mlp-vectorclub.deviantart.com/messages/?log_type=1&instigator_module_type=0&instigator_roleid=1276365&instigator_username=&bpp_status=4&display_order=desc" target="_blank" rel='noopener'>Processed Deviations queue</a>
					</li>
					<li>Once there, press <kbd>Ctrl</kbd><kbd>A</kbd> (which will select the entire page)</li>
					<li>Now press <kbd>Ctrl</kbd><kbd>C</kbd> (copying the selected content)</li>
					<li>Return to this page and click into the box below (you should see a blinking cursor afterwards)</li>
					<li>Hit <kbd>Ctrl</kbd><kbd>V</kbd></li> (to paste what you just copied)
					<li>Repeat these steps if there are multiple pages of results.</li>
				</ol>
				<p>The script will look for any deviation links in the HTML code of the page, which it then sends over to the server to mark them as approved if they were used to finish posts on the site.</p>`);
		});
		// Paste Handling Code based on http://stackoverflow.com/a/6804718/1344955
		$('.mass-approve').children('.textarea').on('paste', function(e){
			let types, pastedData, savedContent, editableDiv = this;
			if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.types && e.originalEvent.clipboardData.getData){
				types = e.originalEvent.clipboardData.types;
				if (((types instanceof DOMStringList) && types.contains("text/html")) || (types.indexOf && types.indexOf('text/html') !== -1)){
					pastedData = e.originalEvent.clipboardData.getData('text/html');
					processPaste(pastedData);
					e.stopPropagation();
					e.preventDefault();
					return false;
				}
			}
			savedContent = document.createDocumentFragment();
			while (editableDiv.childNodes.length > 0){
				savedContent.appendChild(editableDiv.childNodes[0]);
			}
			(function waitForPastedData(elem, savedContent){
				if (elem.childNodes && elem.childNodes.length > 0){
					let pastedData = elem.innerHTML;
					elem.innerHTML = "";
					elem.appendChild(savedContent);
					processPaste(pastedData);
				}
				else setTimeout(function(){ waitForPastedData(elem, savedContent) }, 20);
			})(editableDiv, savedContent);
			return true;
		});

		const $recentPostsUL = $('.recent-posts ul');
		let deviationRegex = /(?:[A-Za-z\-\d]+\.)?deviantart\.com\/art\/(?:[A-Za-z\-\d]+-)?(\d+)|fav\.me\/d([a-z\d]{6,})/g,
			deviationRegexLocal = /\/(?:[A-Za-z\-\d]+-)?(\d+)$/,
			favmeRegexLocal = /fav\.me\/d([a-z\d]{6,})/;
		function processPaste(pastedData){
			pastedData = pastedData.replace(/<img[^>]+>/g,'').match(deviationRegex);
			let deviationIDs = {};

			$.each(pastedData, (_, el) => {
				let match = el.match(deviationRegexLocal);
				if (match && typeof deviationIDs[match[1]] === 'undefined'){
					deviationIDs[match[1]] = true;
					return;
				}
				match = el.match(favmeRegexLocal);
				if (match){
					let id = parseInt(match[1], 36);
					if (typeof deviationIDs[id] === 'undefined')
						deviationIDs[id] = true;
				}
			});

			let deviationIDArray = Object.keys(deviationIDs);
			if (!deviationIDArray)
				return $.Dialog.fail('No deviations found on the pasted page.');

			$.Dialog.wait('Bulk approve posts', `Attempting to approve ${deviationIDArray.length} post${deviationIDArray.length!==1?'s':''}`);

			$.post('/admin/mass-approve',{ids:deviationIDArray.join(',')},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				if (this.html){
					$recentPostsUL.html(this.html);
					reobserve();
				}

				if (this.message)
					$.Dialog.success(false, this.message, true);
				else $.Dialog.close();
			}));
		}
	})();

	const deviationIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			deviationIO.unobserve(el);

			const
				postid = el.dataset.post.replace('-','/'),
				viewonly = el.dataset.viewonly;

			$.get(`/post/lazyload/${postid}`,{viewonly},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail('Cannot load '+postid.replace('/',' #'), this.message);

				$.loadImages(this.html).then(function($el){
					$(el).closest('.image').replaceWith($el);
				});
			}));
		});
	});
	const imageIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			deviationIO.unobserve(el);

			const
				$link = $.mk('a'),
				image = new Image();
			image.src = el.dataset.src;
			$link.attr('href', el.dataset.href).append(image);

			$(image).on('load', function(){
				$(el).closest('.image').html($link);
			});
		});
	});
	const avatarIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			avatarIO.unobserve(el);

			const image = new Image();
			image.src = el.dataset.src;
			image.classList = 'avatar';
			$(image).on('load', function(){
				$(el).replaceWith(image);
			});
		});
	});

	function reobserve(){
		$('.post-deviation-promise').each((_, el) => deviationIO.observe(el));
		$('.post-image-promise').each((_, el) => imageIO.observe(el));
		$('.user-avatar-promise').each((_, el) => avatarIO.observe(el));
	}
	reobserve();
});
