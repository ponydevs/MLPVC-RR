/* global $w,DocReady,Sortable,$content */
DocReady.push(function(){
	'use strict';

	let $sortBy = $('#sort-by'),
		$fullList = $('#full-list'),
		$ReorderBtn = $('#guide-reorder'),
		$ReorderCancelBtn = $('#guide-reorder-cancel');
	$sortBy.on('change',function(){
		let baseurl = $sortBy.data('baseurl'),
			val = $sortBy.val(),
			url = `${baseurl}?ajax&${val}`.replace(/&$/,''),
			stateUrl = `${baseurl}?${val}`.replace(/\?$/,'');

		$.Dialog.wait('Changing sort order');

		$.get(url, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$fullList.html(this.html);
			$w.triggerHandler('scroll');
			$ReorderBtn.attr('disabled', Boolean(val.length));
			history.replaceState(history.state,'',stateUrl);
			$.Dialog.close();
		}));
	});

	const $unloadedSectionULs = $fullList.find('section > ul');
	window._cgFullListOnScroll = $.throttle(100,function(){
		if ($unloadedSectionULs.length === 0){
			$w.off('scroll',window._cgFullListOnScroll);
			return;
		}

		$unloadedSectionULs.each(function(){
			const $this = $(this);
			if (!$this.isInViewport())
				return;

			loadImages($this.find('img[data-src]'), 0, function(){
				$this.addClass('loaded');
				$fullList.find('section > ul:not(.loaded)');
			});
		});
	});
	$w.on('scroll',window._cgFullListOnScroll);
	$w.triggerHandler('scroll');

	function loadImages($imgs = $content.find('img[data-src]'), ix = 0, done = undefined){
		const $this = $imgs.eq(ix);
		if ($this.length === 0){
			$.callCallback(done);
			return;
		}

		const
			src = $this.attr('data-src'),
			img = new Image();

		img.src = src;
		$(img).on('load',function(){
			$this.css('opacity',0).attr('src',src).removeAttr('data-src').animate({opacity:1},300);
			loadImages($imgs, ix+1);
		});
	}

	if (typeof window.Sortable === 'function'){
		$fullList.on('click','.sort-alpha',function(){
			let $section = $(this).closest('section'),
				$ul = $section.children('ul');
			$ul.children().sort(function(a,b){
				return $(a).text().trim().localeCompare($(b).text().trim());
			}).appendTo($ul);
		});

		$ReorderBtn.on('click',function(){
			if (!$ReorderBtn.hasClass('typcn-tick')){
				$ReorderBtn.removeClass('typcn-arrow-unsorted darkblue').addClass('typcn-tick green').html('Save');
				$fullList.addClass('sorting').children().each(function(){
					let $names = $(this).children('ul');
					$names.children().each(function(){
						let $li = $(this);
						$li.data('orig-index', $li.index());
					}).children().moveAttr('href','data-href');
					$names.data('sortable-instance', new Sortable($names.get(0), {
					    ghostClass: "moving",
					    animation: 300,
					}));
				});
				$('.sort-alpha').show();
				$ReorderCancelBtn.removeClass('hidden');
			}
			else {
				$.Dialog.wait('Re-ordering appearances');

				let list = [];
				$fullList.children().children('ul').children().each(function(){
					list.push($(this).children().attr('data-href').split('/').pop().replace(/^(\d+)\D.*$/,'$1'));
				});

				$.post('/cg/full/reorder', {list:list.join(',')}, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$fullList.removeClass('sorting').html(this.html);
					$w.triggerHandler('scroll');
					$ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
					$.Dialog.close();
				}));
			}
		});

		$ReorderCancelBtn.on('click',function(){
			$ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
			$fullList.removeClass('sorting').children().each(function(){
				let $names = $(this).children('ul');
				$names.children().sort(function(a, b){
					a = $(a).data('orig-index');
					b = $(b).data('orig-index');
					return a > b ? 1 : (a < b ? -1 : 0);
				}).appendTo($names).removeData('orig-index').children().moveAttr('data-href', 'href');
				$names.data('sortable-instance').destroy();
				$names.removeData('sortable-instance');
			});
			$('.sort-alpha').hide();
			$ReorderCancelBtn.addClass('hidden');
		});
	}
},function(){
	'use strict';

	$w.off('scroll',window._cgFullListOnScroll);
});
