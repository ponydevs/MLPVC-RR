/* global DocReady */
DocReady.push(function ColorguideFull(){
	'use strict';
	var $sortBy = $('#sort-by'),
		$fullList = $('#full-list');
	$sortBy.on('change',function(){
		var baseurl = $sortBy.data('baseurl'),
			val = $sortBy.val(),
			url = (baseurl+'?ajax&'+val).replace(/\&$/,''),
			stateUrl = (baseurl+'?'+val).replace(/\?$/,'');

		$.Dialog.wait('Changing sort order');

		$.get(url, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$fullList.html(this.html);
			history.replaceState(history.state,'',stateUrl);
			$.Dialog.close();
		}));
	});
});
