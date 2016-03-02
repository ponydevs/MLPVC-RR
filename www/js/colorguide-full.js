/* global DocReady,Sortable */
DocReady.push(function ColorguideFull(){
	'use strict';
	var $sortBy = $('#sort-by'),
		$fullList = $('#full-list'),
		$ReorderBtn = $('#guide-reorder'),
		color = window.color;
	$sortBy.on('change',function(){
		var baseurl = $sortBy.data('baseurl'),
			val = $sortBy.val(),
			url = (baseurl+'?ajax&'+val).replace(/&$/,''),
			stateUrl = (baseurl+'?'+val).replace(/\?$/,'');

		$.Dialog.wait('Changing sort order');

		$.get(url, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$fullList.html(this.html);
			$ReorderBtn.attr('disabled', !val.length);
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
				var $names = $(this).children('div');
				$names.children().moveAttr('href','data-href');
				new Sortable($names.get(0), {
				    ghostClass: "moving",
				    scroll: false,
				    animation: 150,
				});
			});
		}
		else {
			$.Dialog.wait('Re-ordering appearances');

			var list = [];
			$fullList.children().children('div').children().each(function(){
				list.push($(this).attr('data-href').split('/').pop());
			});

			$.post('/'+color+'guide/full?reorder', {list:list.join(',')}, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$fullList.removeClass('sorting').html(this.html);
				$ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
				$.Dialog.close();
			}));
		}
	});
});
