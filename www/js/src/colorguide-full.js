/* global DocReady,Sortable */
DocReady.push(function ColorguideFull(){
	'use strict';
	let $sortBy = $('#sort-by'),
		$fullList = $('#full-list'),
		$ReorderBtn = $('#guide-reorder');
	$sortBy.on('change',function(){
		let baseurl = $sortBy.data('baseurl'),
			val = $sortBy.val(),
			url = `${baseurl}?ajax&${val}`.replace(/&$/,''),
			stateUrl = `${baseurl}?${val}`.replace(/\?$/,'');

		$.Dialog.wait('Changing sort order');

		$.get(url, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$fullList.html(this.html);
			$ReorderBtn.attr('disabled', Boolean(val.length));
			history.replaceState(history.state,'',stateUrl);
			$.Dialog.close();
		}));
	});

	if (typeof window.Sortable !== 'function')
		return;

	$ReorderBtn.on('click',function(){
		if (!$ReorderBtn.hasClass('typcn-tick')){
			$ReorderBtn.removeClass('typcn-arrow-unsorted darkblue').addClass('typcn-tick green').html('Save');
			$fullList.addClass('sorting').children().each(function(){
				let $names = $(this).children('ul');
				$names.children().children().moveAttr('href','data-href');
				Sortable.create($names.get(0), {
				    ghostClass: "moving",
				    scroll: true,
				    animation: 150,
				});
			});
		}
		else {
			$.Dialog.wait('Re-ordering appearances');

			let list = [];
			$fullList.children().children('ul').children().each(function(){
				list.push($(this).children().attr('data-href').split('/').pop().replace(/^(\d+)\D.*$/,'$1'));
			});

			$.post('/cg/full?reorder', {list:list.join(',')}, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$fullList.removeClass('sorting').html(this.html);
				$ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
				$.Dialog.close();
			}));
		}
	});
});
