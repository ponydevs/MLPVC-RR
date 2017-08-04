/* global DocReady,$w,IntersectionObserver */
$(function(){
	'use strict';

	const io = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			io.unobserve(el);

			const favme = el.dataset.favme;

			$.get('/user/contrib/lazyload/'+favme,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail('Cannot load deviation '+favme, this.message);

				$.loadImages(this.html).then(function($el){
					$(el).replaceWith($el.css('opacity',0));
					$el.animate({opacity:1},300);
				});
			}));
		});
	});

	function reobserve(){
		$('.deviation-promise').each((_, el) => io.observe(el));
	}
	reobserve();

	$('#contribs').on('page-switch',function(){
		reobserve();
	});
});
