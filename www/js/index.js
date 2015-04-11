$(function(){
	var $body = $(document.body);
	$('section .unfinished .screencap').parent()
		.fluidbox({
			viewportFill: .8,
		})
		.on('openstart',function(){
		    $body.addClass('no-distractions');
		})
		.on('closestart', function() {
		    $body.removeClass('no-distractions');
		});
});