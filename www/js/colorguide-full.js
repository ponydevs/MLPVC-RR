/* global DocReady */
DocReady.push(function ColorguideFull(){
	'use strict';
	var $sortBy = $('#sort-by');
	$sortBy.on('change',function(){
		window.location.href = ($sortBy.data('baseurl')+'?'+$sortBy.val()).replace('/\?$/','');
	});
});
