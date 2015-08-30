DocReady.push(function Episodes(){
	function pad(n){return n<10?'0'+n:n}
	var $eptableBody = $('#episodes').children('tbody'),
		monthNames = 'January|February|March|April|May|June|July|August|September|October|November|December'.split('|');
	$eptableBody.on('updatetimes',function(){
		$eptableBody.children().children(':last-child').children('time.nodt').each(function(){
			var d = new Date($(this).attr('datetime')),
				str =
					[pad(d.getDate()), monthNames[d.getMonth()], d.getFullYear()].join('-')+' '+
					[pad(d.getHours()), pad(d.getMinutes())].join(':'),
				secs = d.getSeconds();
			if (secs > 0) str += ':'+pad(secs);
			this.innerHTML = str;
		});
	}).trigger('updatetimes');
});
