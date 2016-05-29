/* global Key,$w,console */
(function($){
	'use strict';
	var classStart = 'poly-',
		$doc = $(document);

	$.fn.polyEditor = function(options){
		function topleft(center, Z, size){
			console.group('topleft');
			var framecenter = wrapcenterpos(),
				CX = center.left,
				CY = center.top,
				FX = framecenter.left,
				FY = framecenter.top,
				W = size.width,
				H = size.height;
			console.log('CX=%f CY=%f FX=%f FY=%f Z=%f',CX,CY,FX,FY,Z);
			var NCX = FX + Z * ( CX - FX ),
				NCY = FY + Z * ( CY - FY );
			console.log('NCX=%f NCY=%f',NCX,NCY);
			var NTX = NCX -  W / 2,
				NTY = NCY -  H / 2;
			console.log('NTX=%f NTY=%f',NTX,NTY);
			console.groupEnd();
			return {
				top: NTY,
				left: NTX,
			};
		}

		if (!options.image)
			throw new Error('Missing image');
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
			imagecenterpos = function(imgoffset, resized){
				return {
					top: wrapoffset.top+(imgoffset.top-wrapoffset.top)+(resized.height/2),
					left: wrapoffset.left+(imgoffset.left-wrapoffset.left)+(resized.width/2),
				};
			},
			wrapcenterpos = function(){
				return {
					top: wrapheight/2,
					left: wrapwidth/2,
				};
			},
			renderdebuglines = function(end, end2){
				var wrapcenter = wrapcenterpos();
				$svgElement.empty().append(
					$(document.createElementNS("http://www.w3.org/2000/svg", "line")).attr({
						x1:end.left,
						y1:end.top,
						x2:wrapcenter.left,
						y2:wrapcenter.top,
						fill:"none",
						stroke:"blue",
						'stroke-width':"5",
						'stroke-miterlimit':"10",
					}),
					$(document.createElementNS("http://www.w3.org/2000/svg", "line")).attr({
						x1:end2.left,
						y1:end2.top,
						x2:wrapcenter.left,
						y2:wrapcenter.top,
						fill:"none",
						stroke:"red",
						'stroke-width':"5",
						'stroke-miterlimit':"10",
					})/*,
					$(document.createElementNS("http://www.w3.org/2000/svg", "line")).attr({
						x1:end.left * (scalefactor + 0.1),
						y1:end.top * (scalefactor + 0.1),
						x2:wrapcenter.left,
						y2:wrapcenter.top,
						fill:"none",
						stroke:"lime",
						'stroke-width':"5",
						'stroke-miterlimit':"10",
					})*/
				);
			},
			updateperc = function(top,left,resized){
				$zoomperc.text($.roundTo(zoomlevel*100,2)+'%');
				document.activeElement.blur();

				$zoomout[zoomlevel <= 0.1?'disable':'enable']();
				$zoomin[zoomlevel >= 2.5?'disable':'enable']();

				if (typeof top !== 'number')
					return;
				$imgtl.text($.roundTo(top,2)+','+$.roundTo(left,2));
				var imgcenter = imagecenterpos($imageElement.offset(), resized);
				$imgc.text($.roundTo(imgcenter.top,2)+','+$.roundTo(imgcenter.left,2));
				$imgcExpected.css({
					top: imgcenter.top-wrapoffset.top-5,
					left: imgcenter.left-wrapoffset.left-5,
				});
				var wrapcenter = wrapcenterpos();
				$wrapc.text($.roundTo(wrapcenter.top,2)+','+$.roundTo(wrapcenter.left,2));
			},
			zoomlevel = 1,
			zoomto = function(perc){
				var size = $imageElement.data('size');
				if (typeof size !== 'object')
					return;

				var newzoomlevel = $.rangeLimit(perc, false, 0.1, 2.5),
					newsize;
				if (newzoomlevel !== zoomlevel){
					newsize = $.scaleResize(size.width, size.height, {scale: newzoomlevel});
					zoomlevel = newsize.scale;
				}
				else newsize = {
					width: $imageElement.width(),
					height: $imageElement.height(),
				};
				var imgoffset = $imageElement.offset();
				var zoomed = topleft(imagecenterpos(imgoffset, newsize), zoomlevel, newsize);
				renderdebuglines(imgoffset, zoomed);

				$imageOverlay.add($imageElement)/*.add($svgWrap)*/.css({
					top: zoomed.top,
					left: zoomed.left,
					width: newsize.width,
					height: newsize.height,
				});
				//$svgElement.attr('viewBox','0 0 '+newsize.width+' '+newsize.height);

				updateperc(zoomed.top,zoomed.left,newsize);
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
			$zoomin = $.mk('button').attr('class',classStart+'zoom-in typcn typcn-zoom-in').on('click',function(e){
				e.preventDefault();

				zoomto((Math.floor(zoomlevel*10)+1)/10);
			}),
			$zoomout = $.mk('button').attr('class',classStart+'zoom-out typcn typcn-zoom-out').on('click',function(e){
				e.preventDefault();

				zoomto((Math.floor(zoomlevel*10)-1)/10);
			}),
			$actionsBottomLeft = $.mk('div').attr('class',classStart+'actions '+classStart+'actions-bl').append(
				$zoomin,
				$zoomout,
				$zoomfit,
				$zoomperc,
				$imgtl,
				$imgc,
				$wrapc,
				$mousepos
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
				wrapwidth = $wrap.innerWidth();
				wrapheight = $wrap.innerHeight();
				wrapoffset = $wrap.offset();
				wrapoffset.top -= (wrapheight - $wrap.outerHeight())/2;
				wrapoffset.left -= (wrapwidth - $wrap.outerWidth())/2;

				if (typeof zoomlevel === 'number')
					zoomto(zoomlevel);

				var wrapcenter = wrapcenterpos();
				$wrapc.text(wrapcenter.top+','+wrapcenter.left);
				$svgElement.attr('viewBox','0 0 '+wrapwidth+' '+wrapheight);
			});

		$w.on('resize', resizehandler);
		resizehandler();

		$wrap.append($actionsBottomLeft,$loader,$imgcExpected,$svgWrap);
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
				$imageOverlay.add($imageElement)/*.add($svgWrap)*/.css({
					top: top,
					left: left,
				});

				var size = {
						width: $imageElement.width(),
						height: $imageElement.height(),
					},
					imgoffset = $imageElement.offset();

				renderdebuglines(imgoffset, topleft(imagecenterpos(imgoffset, size), zoomlevel, size));
				updateperc(top, left, size);
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
		$doc.on('mousemove', $.throttle(50,function(e){
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
