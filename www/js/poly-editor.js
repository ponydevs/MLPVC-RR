/* global Key,$w */
(function($){
	'use strict';
	var classStart = 'poly-',
		$doc = $(document);

	$.fn.polyEditor = function(options){

		if (!options.image)
			throw new Error('Missing image');
		var $imageElement = $(new Image()).attr({
				src: options.image,
				'class': classStart+'image',
			}).attr('class',classStart+'image-element'),
			$wrap = this.first().addClass(classStart+'wrap'),
			$imgtl = $.mk('span').attr('title', 'Image top left').attr('class',classStart+'imgtl').text('?, ?'),
			$imgc = $.mk('span').attr('title', 'Image center').attr('class',classStart+'imgc').text('?, ?'),
			$wrapc = $.mk('span').attr('title', 'Wrapper center').attr('class',classStart+'wrapc').text('?, ?'),
			$imgcExpected = $.mk('cpan').attr('class', classStart+'imgc-expected'),
			imagecenterpos = function(imgoffset, resized){
				return {
					top: (imgoffset.top-wrapoffset.top)+(resized.height/2),
					left: (imgoffset.left-wrapoffset.left)+(resized.width/2),
				};
			},
			wrapcenterpos = function(){
				return {
					top: wrapheight/2,
					left: wrapwidth/2,
				};
			},
			updateperc = function(top,left,resized){
				$zoomperc.text($.roundTo(zoomlevel*100,2)+'%');
				document.activeElement.blur();

				if (typeof top !== 'number')
					return;
				$imgtl.text($.roundTo(top,2)+','+$.roundTo(left,2));
				var imgoffset = $imageElement.offset(),
					imgcenter = imagecenterpos(imgoffset, resized);
				$imgc.text($.roundTo(imgcenter.top,2)+','+$.roundTo(imgcenter.left,2));
				$imgcExpected.css({
					top: imgcenter.top-5,
					left: imgcenter.left-5,
				});
				var wrapcenter = wrapcenterpos();
				$wrapc.text($.roundTo(wrapcenter.top,2)+','+$.roundTo(wrapcenter.left,2));
			},
			zoomlevel = 0,
			zoomto = function(perc){
				var size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				var resized = $.scaleResize(size.width, size.height, {scale: $.rangeLimit(perc, false, 1e-2, 2.5)});

				var imgoffset = {
						top: (wrapheight-resized.height)/2,
						left: (wrapwidth-resized.width)/2,
					},
					imgcenter = imagecenterpos(imgoffset, resized),
					wrapcenter = wrapcenterpos(),
					dist = {
						top: wrapcenter.top-imgcenter.top,
						left: wrapcenter.left-imgcenter.left,
					};

				/*var top = imgoffset.top - dist.top,
					left = imgoffset.left - dist.left;    * /
				var scaledelta = zoomlevel/resized.scale,
					top = imgoffset.top - (dist.top*scaledelta),
					left = imgoffset.left - (dist.left*scaledelta);
				console.log(scaledelta); */

				console.log(wrapcenter, imgcenter);

				zoomlevel = resized.scale;
				var top = imgcenter.top+(dist.top * zoomlevel),
					left =  imgcenter.left+(dist.left * zoomlevel);
				$imageOverlay.add($imageElement).css({
					top: top,
					left: left,
					width: resized.width,
					height: resized.height,
				});

				updateperc(top,left,resized);
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
					resized = $.scaleResize(size.width, size.height, wide ? {height:wrapheight} : {width:wrapwidth}),
					top = wide ? 0 : (wrapheight-resized.height)/2,
					left = wide ? (wrapwidth-resized.width)/2 : 0;
				$imageOverlay.add($imageElement).css({
					top: top,
					left: left,
					width: resized.width,
					height: resized.height,
				});
				zoomlevel = resized.scale;
				updateperc(top,left,resized);
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
				$zoomperc,
				$imgtl,
				$imgc,
				$wrapc
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
				wrapoffset.top -= (wrapheight - $wrap.outerHeight())/2;
				wrapoffset.left -= (wrapwidth - $wrap.outerWidth())/2;

				if (typeof zoomlevel === 'number')
					zoomto(zoomlevel);

				var wrapcenter = wrapcenterpos();
				$wrapc.text(wrapcenter.top+','+wrapcenter.left);
			});

		$w.on('resize', resizehandler);
		resizehandler();

		$wrap.append($actionsBottomLeft,$loader,$imgcExpected);
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
				},
				top = (initial.top+(mouse.top-initialmouse.top))-wrapoffset.top,
				left = (initial.left+(mouse.left-initialmouse.left))-wrapoffset.left;
				$imageOverlay.add($imageElement).css({
					top: top,
					left: left,
				});

				updateperc(top, left, {
					width: $imageElement.width(),
					height: $imageElement.height(),
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
				$w.off('resize', resizehandler);
				$wrap.empty().removeClass(classStart+'wrap');
			}
		};
	};
})(jQuery);
