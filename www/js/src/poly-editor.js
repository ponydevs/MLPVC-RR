/* Basic Polygon editor | by @SeinopSys + Trildar & Masem | for gh:ponydevs/MLPVC-RR */
/* global Key,$w,console */
(function($){
	'use strict';
	let classStart = 'poly-',
		$doc = $(document);

	$.fn.polyEditor = function(options){
		if (!options.image)
			throw new Error('Missing image');
		//noinspection ES6ConvertVarToLetConst
		var $imageElement = $(new Image()).attr({
				src: options.image,
				'class': classStart+'image',
			}).attr('class',classStart+'image-element'),
			$svgElement = $(document.createElementNS("http://www.w3.org/2000/svg", "svg")).attr({
				'class': classStart+'svg-element',
				version: '1.1',
				xmlns: 'http://www.w3.org/2000/svg',
			}),
			$svgWrap = $.mk('div').attr('class',classStart+'svg-wrap').append($svgElement),
			$wrap = this.first().addClass(classStart+'wrap'),
			$mousepos = $.mk('span').attr('title', 'Mouse position').attr('class',classStart+'mousepos').text('?, ?'),
			$imgtl = $.mk('span').attr('title', 'Image top left').attr('class',classStart+'imgtl').text('?, ?'),
			$imgc = $.mk('span').attr('title', 'Image center').attr('class',classStart+'imgc').text('?, ?'),
			$wrapc = $.mk('span').attr('title', 'Wrapper center').attr('class',classStart+'wrapc').text('?, ?'),
			$imgcExpected = $.mk('cpan').attr('class', classStart+'imgc-expected'),
			topleft = (imgoffset, scalefactor) => {
				let framecenter = wrapcenterpos(),
					TX = imgoffset.left,
					TY = imgoffset.top,
					FX = framecenter.left,
					FY = framecenter.top,
					NTX = FX + scalefactor * ( TX - FX ),
					NTY = FY + scalefactor * ( TY - FY );
				return {
					top: NTY,
					left: NTX,
				};
			},
			imagepos = imgoffset => {
				let wrapoffset = wrappos();
				return {
					top: imgoffset.top-wrapoffset.top,
					left: imgoffset.left-wrapoffset.left,
				};
			},
			imagecenterpos = (imgoffset, resized) => {
				let wrapoffset = wrappos();
				return {
					top: (imgoffset.top-wrapoffset.top)+(resized.height/2),
					left: (imgoffset.left-wrapoffset.left)+(resized.width/2),
				};
			},
			wrapcenterpos = () => ({
				top: wrapheight/2,
				left: wrapwidth/2,
			}),
			wrappos = function(){
				let wrapoffset = $wrap.offset();
				wrapoffset.top -= (wrapheight - $wrap.outerHeight())/2;
				wrapoffset.left -= (wrapwidth - $wrap.outerWidth())/2;
				return wrapoffset;
			},
			updatezoomlevel = function(){
				$zoomperc.text($.roundTo(zoomlevel*100,2)+'%');
				document.activeElement.blur();

				$zoomout[zoomlevel <= 0.1?'disable':'enable']();
				$zoomin[zoomlevel >= 2.5?'disable':'enable']();
			},
			updatepositions = function(top,left,resized){
				updatezoomlevel();

				if (typeof top !== 'number')
					return;
				$imgtl.text($.roundTo(top,2)+','+$.roundTo(left,2));
				let imgcenter = imagecenterpos($imageElement.offset(), resized);
				$imgc.text($.roundTo(imgcenter.top,2)+','+$.roundTo(imgcenter.left,2));
				$imgcExpected.css({
					top: imgcenter.top-5,
					left: imgcenter.left-5,
				});
				let wrapcenter = wrapcenterpos();
				$wrapc.text($.roundTo(wrapcenter.top,2)+','+$.roundTo(wrapcenter.left,2));
			},
			zoomlevel = 1,
			zoomto = function(perc){
				let size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				let newzoomlevel = $.rangeLimit(perc, false, 0.1, 2.5),
					newsize,
					oldzoomlevel;
				if (newzoomlevel !== zoomlevel){
					newsize = $.scaleResize(size.width, size.height, {scale: newzoomlevel});
					oldzoomlevel = zoomlevel;
					zoomlevel = newsize.scale;
				}
				else {
					newsize = {
						width: $imageElement.width(),
						height: $imageElement.height(),
					};
					oldzoomlevel = zoomlevel;
				}
				let imgoffset = $imageElement.offset();
				let zoomed = topleft(imagepos(imgoffset), newzoomlevel/oldzoomlevel);

				$imageOverlay.add($imageElement)/*.add($svgWrap)*/.css({
					top: zoomed.top,
					left: zoomed.left,
					width: newsize.width,
					height: newsize.height,
				});
				//$svgElement.attr('viewBox','0 0 '+newsize.width+' '+newsize.height);

				updatepositions(zoomed.top,zoomed.left,newsize);
			},
			$zoomperc = $.mk('span').attr({
					'class': classStart+'zoom-perc',
					contenteditable: true,
				}).text('100%').on('keydown', function(e){
					if (!$.isKey(Key.Enter, e))
						return;

					e.preventDefault();

					let perc = parseFloat($zoomperc.text(), 10);
					if (!isNaN(perc))
						zoomto(perc/100);

					updatepositions();
				}).on('mousedown',function(){
					$zoomperc.data('mousedown', true);
				}).on('mouseup',function(){
					$zoomperc.data('mousedown', false);
				}).on('click',function(){
					if ($zoomperc.data('focused') !== true){
						$zoomperc.data('focused', true);
						$zoomperc.select();
					}
				}).on('dblclick', function(e){
					e.preventDefault();
					$zoomperc.select();
				}).on('blur', () => {
					if (!$zoomperc.data('mousedown'))
						$zoomperc.data('focused', false);
					if ($zoomperc.html().trim().length === 0)
						updatezoomlevel();
					$.clearSelection();
				}),
			$zoomfit = $.mk('button').attr('class',classStart+'zoom-fit typcn typcn typcn-arrow-minimise').on('click', function(e){
				let size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				e.preventDefault();

				let wide = size.width > size.height,
					newsize = $.scaleResize(size.width, size.height, wide ? {height:wrapheight} : {width:wrapwidth}),
					top = wide ? 0 : (wrapheight-newsize.height)/2,
					left = wide ? (wrapwidth-newsize.width)/2 : 0;
				$imageOverlay.add($imageElement)/*.add($svgWrap)*/.css({
					top: top,
					left: left,
					width: newsize.width,
					height: newsize.height,
				});
				//$svgElement.attr('viewBox','0 0 '+newsize.width+' '+newsize.height);
				zoomlevel = newsize.scale;
				zoomto(zoomlevel);
			}),
			$zoomin = $.mk('button').attr('class',classStart+'zoom-in typcn typcn-zoom-in').on('click', function(e){
				e.preventDefault();

				zoomto((Math.floor(zoomlevel*10)+1)/10);
			}),
			$zoomout = $.mk('button').attr('class',classStart+'zoom-out typcn typcn-zoom-out').on('click', function(e){
				e.preventDefault();

				zoomto((Math.floor(zoomlevel*10)-1)/10);
			}),
			$actionTopLeft = $.mk('div').attr('class',classStart+'actions '+classStart+'actions-tl').append(
					$zoomin,
					$zoomout,
					$zoomfit,
					$zoomperc
				).on('mousedown', function(e){
					e.stopPropagation();
					$zoomperc.triggerHandler('blur');
				}),
				$actionsBottomLeft = $.mk('div').attr('class',classStart+'actions '+classStart+'actions-bl').append(
					$imgtl,
					$imgc,
					$wrapc,
					$mousepos
				).on('mousedown', function(e){
					e.stopPropagation();
					$zoomperc.triggerHandler('blur');
				}),
			$imageOverlay = $.mk('div').attr('class',classStart+'image-overlay').appendTo($wrap),
			$loader = $.mk('div').attr('class',classStart+'loader').html('Loading&hellip;'),
			wrapwidth,
			wrapheight,
			movehandler,
			resizehandler = $.throttle(250, function(){
				wrapwidth = $wrap.innerWidth();
				wrapheight = $wrap.innerHeight();

				if (typeof zoomlevel === 'number')
					zoomto(zoomlevel);

				let wrapcenter = wrapcenterpos();
				$wrapc.text(wrapcenter.top+','+wrapcenter.left);
				$svgElement.attr('viewBox','0 0 '+wrapwidth+' '+wrapheight);
			});

		$w.on('resize', resizehandler);
		resizehandler();

		$wrap.append($actionTopLeft,$actionsBottomLeft,$loader,$imgcExpected,$svgWrap);

		$imageElement.on('load', function(){
			$imageOverlay.css('opacity',0);
			$imageElement.appendTo($wrap).data('size',{
				width: $imageElement.width(),
				height: $imageElement.height(),
			});
			$loader.detach();

			$zoomfit.triggerHandler('click');
			$imageOverlay.fadeTo(500, 1);
			let initial,
				initialmouse;
			movehandler = $.throttle(100, function(e){
				e.preventDefault();

				let mouse = {
						top: e.pageY,
						left: e.pageX,
					},
					wrapoffset = wrappos(),
					top = (initial.top+(mouse.top-initialmouse.top))-wrapoffset.top,
					left = (initial.left+(mouse.left-initialmouse.left))-wrapoffset.left;
				$imageOverlay.add($imageElement)/*.add($svgWrap)*/.css({
					top: top,
					left: left,
				});

				let size = {
					width: $imageElement.width(),
					height: $imageElement.height(),
				};

				updatepositions(top, left, size);
			});

			$doc.on('mousedown', function(e){
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
		$doc.on('mousemove', $.throttle(50, function(e){
			let wrapoffset = wrappos();
			$mousepos.text((e.pageX-wrapoffset.left)+','+(e.pageY-wrapoffset.top));
		}));

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
