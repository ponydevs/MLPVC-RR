$(function(){
	var $eptableBody = $('#episodes').children('tbody');
	$eptableBody.on('updatetimes',function(){
		$eptableBody.children().children(':last-child').children('time.nodt').each(function(){
			this.innerHTML = new Date($(this).attr('datetime')).toLocaleString();
		});
	}).trigger('updatetimes');
});
