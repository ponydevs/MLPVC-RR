/* global Key */
(function($, undefined){
	'use strict';
	var classStart = 'poly-',
		$doc = $(document),
		$w;

	$.fn.polyEditor = function(options){

		if (!options.image)
			throw new Error('Missing image');
		var $imageElement = $(new Image()).attr({
				src: options.image,
				'class': classStart+'image',
			}).attr('class',classStart+'image-element'),
			$wrap = this.first().addClass(classStart+'wrap'),
			zoomlevel = 0,
			zoomto = function(perc){
				var size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				var wide = size.width > size.height,
					resized = $.scaleResize(size.width, size.height, {scale: $.rangeLimit(perc, false, 1e-2, 2.5)});

				var imgoffset = {
						top: (wrapheight-resized.height)/2,
						left: (wrapwidth-resized.width)/2,
					},
					imgcenter = {
						top: (imgoffset.top-wrapoffset.top)+(resized.height/2),
						left: (imgoffset.left-wrapoffset.left)+(resized.width/2),
					},
					wrapcenter = {
						top: wrapheight/2,
						left: wrapwidth/2,
					},
					dist = {
						top: wrapcenter.top-imgcenter.top,
						left: wrapcenter.left-imgcenter.left,
					};

				$imageOverlay.add($imageElement).css({
					//top: imgoffset.top - dist.top,
					//left: imgoffset.left - dist.left,
					top: imgoffset.top,
					left: imgoffset.left,
					width: resized.width,
					height: resized.height,
				});
				zoomlevel = resized.scale;
				updateperc();
			},
			updateperc = function(){
				$zoomperc.text($.roundTo(zoomlevel*100,2)+'%');
				document.activeElement.blur();
			},
			$zoomperc = $.mk('span').attr({
				'class': classStart+'zoom-perc',
				contenteditable: true,
			}).text('100%').on('keydown',function(e){
				if (!$.isKey(Key.Enter, e))
					return;

				e.preventDefault();

				var perc = parseFloat($zoomperc.text(), 10);
				if (!isNaN(perc))
					zoomto(perc/100);

				updateperc();
			}).on('click',function(){
				$zoomperc.select();
			}).on('blur',function(){
				$.clearSelection();
			}),
			$zoomfit = $.mk('button').attr('class',classStart+'zoom-fit typcn typcn typcn-arrow-minimise').on('click', function(e){
				var size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				e.preventDefault();

				var wide = size.width > size.height,
					resized = $.scaleResize(size.width, size.height, wide ? {height:wrapheight} : {width:wrapwidth});
				$imageOverlay.add($imageElement).css({
					top: (wide ? 0 : (wrapheight-resized.height)/2),
					left: (wide ? (wrapwidth-resized.width)/2 : 0),
					width: resized.width,
					height: resized.height,
				});
				zoomlevel = resized.scale;
				updateperc();
			}),
			$actionsBottomLeft = $.mk('div').attr('class',classStart+'actions '+classStart+'actions-bl').append(
				$.mk('button').attr('class',classStart+'zoom-in typcn typcn-zoom-in').on('click',function(e){
					e.preventDefault();

					zoomto((Math.floor(zoomlevel*10)+1)/10);
				}),
				$.mk('button').attr('class',classStart+'zoom-out typcn typcn-zoom-out').on('click',function(e){
					e.preventDefault();

					zoomto((Math.floor(zoomlevel*10)-1)/10);
				}),
				$zoomfit,
				$zoomperc
			).on('mousedown',function(e){
				e.stopPropagation();
				$zoomperc.triggerHandler('blur');
			}),
			$imageOverlay = $.mk('div').attr('class',classStart+'image-overlay').appendTo($wrap),
			$loader = $.mk('div').attr('class',classStart+'loader').html('Loading&hellip;'),
			wrapheight,
			wrapwidth,
			wrapoffset,
			movehandler,
			resizehandler = $.throttle(250, function(e){
				wrapheight = $wrap.innerHeight();
				wrapwidth = $wrap.innerWidth();
				wrapoffset = $wrap.offset();
				wrapoffset.top -= wrapheight - $wrap.outerHeight();
				wrapoffset.left -= wrapwidth - $wrap.outerWidth();
				if (typeof zoomlevel === 'number')
					zoomto(zoomlevel);
			});

		$(window).on('resize', resizehandler);
		resizehandler();

		$wrap.append($actionsBottomLeft,$loader);
		$imageElement.on('load', function(){
			$imageOverlay.css('opacity',0);
			$imageElement.appendTo($wrap).data('size',{
				width: $imageElement.width(),
				height: $imageElement.height(),
			});
			$loader.detach();

			$zoomfit.triggerHandler('click');
			$imageOverlay.fadeTo(500, 1);
			var initial,
				initialmouse;
			movehandler = $.throttle(100, function(e){
				e.preventDefault();

				var mouse = {
					top: e.pageY,
					left: e.pageX,
				};
				$imageOverlay.add($imageElement).css({
					top: (initial.top+(mouse.top-initialmouse.top))-wrapoffset.top,
					left: (initial.left+(mouse.left-initialmouse.left))-wrapoffset.left,
				});
			});

			$doc.on('mousedown',function(e){
				if (!$(e.target).is($imageOverlay))
					return;

				e.preventDefault();
				initial = $imageOverlay.offset();
				initialmouse = {
					top: e.pageY,
					left: e.pageX,
				};
				$doc.on('mousemove',movehandler);
			});
			$doc.on('mouseup mouseleave blur',function(){
				$doc.off('mousemove',movehandler);
			});
		});

		return {
			destroy: function(){
				if (typeof movehandler === 'function')
					$doc.off('mousemove', movehandler);
				$(window).off('resize', resizehandler);
				$wrap.empty().removeClass(classStart+'wrap');
			}
		};
	};
})(jQuery);
