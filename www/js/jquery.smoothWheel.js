(function($){
	$.extend($.easing, {
	    easeOutQuint: function(x, t, b, c, d) {
	        return c * ((t = t / d - 1) * t * t * t * t + 1) + b;
	    }
	});
	var $w = $(window),
		$header = $('header');

	function fixedHeader(newScrollTop){
		if (newScrollTop > 10)
			$header.addClass('fixed');
		else
			$header.removeClass('fixed');
	}

	function smoothWheel(){
		var wheel = false,
			$el = $(this),
			el = $el.get(0),
		    scrollTop = el.scrollTop;

		$el.on('DOMMouseScroll mousewheel', function(e, delta) {
			console.log('step 1');
			if (e.ctrlKey === true || typeof window.matchMedia !== 'undefined' && window.matchMedia("(max-width: 650px)").matches)
				return true;
			else if ($w.width() < 650) return true;
			if ($el.hasClass('no-distractions') || $el.hasClass('locked')) return true;
			e.preventDefault();

		    delta = delta || -e.originalEvent.detail / 3 || e.originalEvent.wheelDelta / 120;
		    delta *= 40;
		    wheel = true;

			scrollTop = Math.min(el.scrollHeight-$el.outerHeight(), Math.max(0, parseInt(scrollTop - delta)));

		    $el.stop().animate({
		        scrollTop: scrollTop + 'px'
		    }, 1000, 'easeOutQuint', function() {
		        wheel = false;
		    });

		    fixedHeader(scrollTop);
		});
		fixedHeader(scrollTop);
	};

	smoothWheel.call(document.body);
})(jQuery);