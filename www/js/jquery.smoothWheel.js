(function($){
	$.extend($.easing, {
	    easeOutQuint: function(x, t, b, c, d) {
	        return c * ((t = t / d - 1) * t * t * t * t + 1) + b;
	    }
	});
	var $w = $(window),
		$doc = $(document),
		$header = $('header'),
		$topbar = $('#topbar'),
		tbh = $topbar.outerHeight()
		$el = void 0;

	function fixedHeader(newScrollTop){
		if (newScrollTop > tbh)
			$header.addClass('fixed');
		else $header.removeClass('fixed');
	}

	function AlreadyInitilaizedError(msg){
		this.message = msg;
		this.element = $el;
		return this;
	}
	AlreadyInitilaizedError.prototype = Error;
	
	function smoothWheel(){
		if ($el instanceof jQuery) throw new AlreadyInitilaizedError('smoothWheel already initialized on another element');
		var wheel = false,
			el = this,
		    scrollTop = el.scrollTop;
			
		$el = $(el);

		$el.on('DOMMouseScroll mousewheel', function(e, delta) {
			if (e.ctrlKey === true || typeof window.matchMedia !== 'undefined' && window.matchMedia("(max-width: 650px)").matches)
				return true;
			else if ($w.width() < 650) return true;
			if ($el.hasClass('no-distractions') || $el.hasClass('locked')) return true;
			e.preventDefault();
			e.stopPropagation();

		    delta = delta || -e.originalEvent.detail / 3 || e.originalEvent.wheelDelta / 120;
		    delta *= 40;
		    wheel = true;

			scrollTop = Math.min(el.scrollHeight-$w.height(), Math.max(0, parseInt(scrollTop - delta)));
			
		    $el.stop().animate({
		        scrollTop: scrollTop + 'px'
		    }, {
				duration:1000,
				easing: 'easeOutQuint',
				step: function() {
					fixedHeader(el.scrollTop);
				},
				complete: function() {
					wheel = false;
				}
				
			});
		});
		$doc.on('scroll',function(){
			if (wheel) return true;
			
			scrollTop = el.scrollTop;
			fixedHeader(scrollTop);
		});
		$el.unSmoothWheel = function(){
			$el.off('DOMMouseScroll mousewheel');
			$doc.off('scroll');
			$el = void 0;
		};
		fixedHeader(scrollTop);
	};

	smoothWheel.call(document.body);
})(jQuery);