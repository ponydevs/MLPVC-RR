(function($){
	$.extend($.easing, {
	    easeOutQuint: function(x, t, b, c, d) {
	        return c * ((t = t / d - 1) * t * t * t * t + 1) + b;
	    }
	});
	
	var wheel = false,
		$main = $('main'),
		main = $main.get(0),
		$w = $(window),
	    scrollTop = main.scrollTop;

	$main.on('DOMMouseScroll mousewheel', function(e, delta) {
		e.preventDefault();
	    delta = delta || -e.originalEvent.detail / 3 || e.originalEvent.wheelDelta / 120;
	    wheel = true;

		var origScrollTop = main.scrollTop;
		scrollTop = Math.min(main.scrollHeight-$main.outerHeight(), Math.max(0, parseInt(scrollTop - delta * 30)));
		var scrollTopDiff = Math.abs(origScrollTop - scrollTop);

	    $main.stop().animate({
	        scrollTop: scrollTop + 'px'
	    }, 2000 * (scrollTopDiff < 90 ? scrollTopDiff/90 : 1), 'easeOutQuint', function() {
	        wheel = false;
	    });
	});
})(jQuery);