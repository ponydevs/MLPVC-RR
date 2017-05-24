/* Color Picker | by @SeinopSys + Trildar & Masem | for gh:ponydevs/MLPVC-RR */
/* global $w,$body */
(function($, undefined){
	'use strict';
	const pluginScope = {
		menubar: undefined,
		statusbar: undefined,
		picker: undefined,
	};

	const
		Key = window.parent.Key,
/*		Tools = {
			cursor: 0,
			pipette: 1,
			move: 2,
		},*/
		Zoom = {
			min: 0.004,
			max: 32,
			step: 1.1,
		};

	class Menubar {
		constructor(){
			this._$menubar = $('#menubar');
			this._$menubar.children().children('a').on('click',e => {
				e.preventDefault();
				e.stopPropagation();

				this._$menubar.addClass('open');
				$(e.target).trigger('mouseenter');
			}).on('mouseenter',e => {
				if (!this._$menubar.hasClass('open'))
					return;

				this._$menubar.find('a.active').removeClass('active');
				$(e.target).addClass('active').next().removeClass('hidden');
			});
			this._$filein = $.mk('input','screenshotin').attr({type:'file',accept:'image/png',tabindex:-1,'class':'fileinput'}).appendTo($body);
			this._$openImage = $('#open-image').on('click',e => {
				e.preventDefault();

				this._$filein.trigger('click');
			});
			this._$filein.on('change',() => {
				const val = this._$filein.val();

				if (!val)
					return;

				this._$openImage.addClass('disabled');
				this.setFile(this._$filein[0].files[0],() => {
					this._$openImage.removeClass('disabled');
					this._$clearImage.removeClass('disabled');
				});
			});
			this._$clearImage = $('#clear-image').on('click',e => {
				e.preventDefault();

				$.Dialog.confirm('Clear image','Are you sure you want to clear the current image?',sure => {
					if (!sure) return;

					ColorPicker.getInstance().clearImage();
					this._$clearImage.addClass('disabled');
				});
			});

			$body.on('click',() => {
				this._$menubar.removeClass('open');
				this._$menubar.find('a.active').removeClass('active');
				this._$menubar.children('li').children('ul').addClass('hidden');
			});
		}
		/** @return {Menubar} */
		static getInstance(){
			if (typeof pluginScope.menubar === 'undefined')
				pluginScope.menubar = new Menubar();
			return pluginScope.menubar;
		}
		setFile(file, callback){
			if (file.type !== 'image/png'){
				$.Dialog.fail('Invalid file', 'You may only use PNG images with this tool');
				return;
			}
	        const reader = new FileReader();
	        reader.onload = () => {
				ColorPicker.getInstance().setImage(reader.result, callback);
	        };
	        reader.readAsDataURL(file);
		}
	}

	class Statusbar {
		constructor(){
			this._$el = $('#statusbar');
			this._$info = this._$el.children('.info');
			this._$pos = this._$el.children('.pos');
			this._$colorat = this._$el.children('.colorat').children();
			this.infoLocked = false;
			this.Pos = {
				mouse: 'mousepos',
				imageTopLeft: 'imagetl',
				imageCenter: 'imagec',
				pickerCenter: 'pickerc',
			};

			this[`_$${this.Pos.mouse}`]        = this._$pos.children('.mouse');
			this[`_$${this.Pos.imageTopLeft}`] = this._$pos.children('.image-top-left');
			this[`_$${this.Pos.imageCenter}`]  = this._$pos.children('.image-center');
			this[`_$${this.Pos.pickerCenter}`] = this._$pos.children('.picker-center');
			$.each(this.Pos, k => {
				this.setPosition(k);
			});
		}
		/** @return {Statusbar} */
		static getInstance(){
			if (typeof pluginScope.statusbar === 'undefined')
				pluginScope.statusbar = new Statusbar();
			return pluginScope.statusbar;
		}
		lockInfo(){
			this.infoLocked = true;
		}
		unlockInfo(){
			this.infoLocked = false;
		}
		setInfo(text = ''){
			if (this.infoLocked)
				return;

			this._$info.text(text);
		}
		setPosition(which, tl = {top:'?',left:'?'}, zoomlevel = 1){
			const elkey = this.Pos[which];
			if (typeof elkey !== 'string')
				throw new Error('[Statusbar.setPosition] Invalid position display key: '+which);

			if (zoomlevel !== 1){
				tl.left *= zoomlevel;
				tl.top *= zoomlevel;
			}

			this[`_$${elkey}`].text(`${isNaN(tl.left)?'?':$.roundTo(tl.left,2)},${isNaN(tl.top)?'?':$.roundTo(tl.top,2)}`);
		}
		setColorAt(hex = ''){
			if (hex.length){
				this._$colorat.css({
					backgroundColor: hex,
					color: $.yiq(hex) > 127 ? 'black' : 'white',
				});
			}
			else this._$colorat.css({
					backgroundColor: '',
					color: '',
				});
			this._$colorat.text(hex||'UNKNOWN');
		}
		debug(enable){
			this._$pos[enable?'addClass':'removeClass']('debug');
		}
	}

	class ColorPicker {
		constructor(){
			this._mousepos = {
				top: NaN,
				left: NaN,
			};
			this._zoomlevel = 1;
			this._movehandler = null;
			this._hasImage = false;

			this._$picker = $('#picker');
			this._wrapheight = this._$picker.height();
			this._wrapwidth = this._$picker.width();
			this._$imageOverlay = $.mk('div').attr('class','image-overlay').appendTo(this._$picker);
			this._$imageCanvas = $.mk('canvas').attr('class','image-element');
			this._$imgcExpected = $.mk('span').attr('class', 'imgc-expected');
			this._$svgElement = $(document.createElementNS("http://www.w3.org/2000/svg", "svg")).attr({
				'class': 'svg-element',
				version: '1.1',
				xmlns: 'http://www.w3.org/2000/svg',
			});
			this._$svgWrap = $.mk('div').attr('class','svg-wrap').append(this._$svgElement);
			this._$zoomperc = $.mk('span').attr({
				'class': 'zoom-perc',
				'data-info': 'Current zoom level (Click to enter a custom value)',
				contenteditable: true,
			}).text('100%').on('keydown',e => {
				if (!$.isKey(Key.Enter, e))
					return;

				e.preventDefault();

				let perc = parseFloat(this._$zoomperc.text());
				if (!isNaN(perc))
					this.setZoomLevel(perc/100);

				this.updatePositions();
			}).on('mousedown',() => {
				this._$zoomperc.data('mousedown', true);
			}).on('mouseup',() => {
				this._$zoomperc.data('mousedown', false);
			}).on('click',() => {
				if (this._$zoomperc.data('focused') !== true){
					this._$zoomperc.data('focused', true);
					this._$zoomperc.select();
				}
			}).on('dblclick',e => {
				e.preventDefault();
				this._$zoomperc.select();
			}).on('blur', () => {
				if (!this._$zoomperc.data('mousedown'))
					this._$zoomperc.data('focused', false);
				if (this._$zoomperc.html().trim().length === 0)
					this.updateZoomLevel();
				$.clearSelection();
			});
			this._$zoomfit = $.mk('button').attr({'class':'zoom-fit typcn typcn typcn-arrow-minimise','data-info':'Fit in view (Ctrl+0)'}).on('click',e => {
				e.preventDefault();

				this.setZoomFit();
			});
			this._$zoomorig = $.mk('button').attr({'class':'zoom-orig typcn typcn typcn-zoom','data-info':'Original size (Ctrl+1)'}).on('click',e => {
				e.preventDefault();

				this.setZoomOriginal();
			});
			this._$zoomin = $.mk('button').attr({'class':'zoom-in typcn typcn-zoom-in','data-info':'Zoom in (Ctrl/Alt+Scroll Up)'}).on('click',(e, mousepos) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel*Zoom.step, mousepos);
			});
			this._$zoomout = $.mk('button').attr({'class':'zoom-out typcn typcn-zoom-out','data-info':'Zoom out (Ctrl/Alt+Scroll Down)'}).on('click',(e, mousepos) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel/Zoom.step, mousepos);
			});
			this._$actionTopLeft = $.mk('div').attr('class','actions actions-tl').append(
				this._$zoomin,
				this._$zoomout,
				this._$zoomfit,
				this._$zoomorig,
				this._$zoomperc
			).on('mousedown',e => {
				e.stopPropagation();
				this._$zoomperc.triggerHandler('blur');
			});
			this._$actionsBottomLeft = $.mk('div').attr('class','actions actions-bl').append(
				'Unused panel'
			).on('mousedown',e => {
				e.stopPropagation();
				this._$zoomperc.triggerHandler('blur');
			});
			this._$loader = $.mk('div').attr('class','loader');

			$w.on('resize', this.resizeHandler);
			this.resizeHandler();
			this._$picker.append(
				this._$actionTopLeft,
				this._$actionsBottomLeft,
				this._$loader,
				this._$imgcExpected,
				this._$svgWrap
			);

			$body.on('mousemove', $.throttle(50,e => {
				if (!this._hasImage)
					return;

				const
					wrapoffset = this.getWrapPosition(),
					imgpos = this.getImagePosition(),
					imgsize = this.getImageSize();

				this._mousepos.top = e.pageY-wrapoffset.top;
				this._mousepos.left = e.pageX-wrapoffset.left;
				if (
					this._mousepos.top < imgpos.top ||
					this._mousepos.top > imgpos.top+imgsize.height ||
					this._mousepos.left < imgpos.left ||
					this._mousepos.left > imgpos.left+imgsize.width
				){
					this._mousepos.top = NaN;
					this._mousepos.left = NaN;
					Statusbar.getInstance().setColorAt();
				}
				else {
					this._mousepos.top = Math.floor((this._mousepos.top-Math.floor(imgpos.top))/this._zoomlevel);
					this._mousepos.left = Math.floor((this._mousepos.left-Math.floor(imgpos.left))/this._zoomlevel);
					let p = this._$imageCanvas[0].getContext('2d').getImageData(this._mousepos.left, this._mousepos.top, 1, 1).data;
					Statusbar.getInstance().setColorAt($.rgb2hex({r:p[0], g:p[1], b:p[2]}));
				}
				Statusbar.getInstance().setPosition('mouse', this._mousepos);
			}));
			$w.on('mousewheel',e => {
				if (!e.ctrlKey && !e.altKey)
					return;

				e.preventDefault();

				const
					wrapoffset = this.getWrapPosition(),
					pos = {
						top: e.pageY-wrapoffset.top,
						left: e.pageX-wrapoffset.left,
					};
				if (e.originalEvent.deltaY > 0)
					this._$zoomout.trigger('click', [pos]);
				else this._$zoomin.trigger('click', [pos]);
			});
		}
		/** @return {ColorPicker} */
		static getInstance(){
			if (typeof pluginScope.picker === 'undefined')
				pluginScope.picker = new ColorPicker();
			return pluginScope.picker;
		}
		getTopLeft(imgoffset, scalefactor, center = this.getWrapCenterPosition()){
			let TX = imgoffset.left,
				TY = imgoffset.top,
				FX = center.left,
				FY = center.top,
				NTX = FX + scalefactor * ( TX - FX ),
				NTY = FY + scalefactor * ( TY - FY );
			return {
				top: NTY,
				left: NTX,
			};
		}
		getImageSize(){
			return {
				width: this._$imageCanvas.width(),
				height: this._$imageCanvas.height(),
			};
		}
		getImagePosition(imgoffset = this._$imageCanvas.offset()){
			let wrapoffset = this.getWrapPosition();
			return {
				top: imgoffset.top-wrapoffset.top,
				left: imgoffset.left-wrapoffset.left,
			};
		}
		getImageCenterPosition(imgoffset, resized){
			let wrapoffset = this.getWrapPosition();
			return {
				top: ((imgoffset.top-wrapoffset.top)+(resized.height/2)),
				left: ((imgoffset.left-wrapoffset.left)+(resized.width/2)),
			};
		}
		getWrapCenterPosition(){
			return {
				top: this._wrapheight/2,
				left: this._wrapwidth/2,
			};
		}
		getWrapPosition(){
			let wrapoffset = this._$picker.offset();
			wrapoffset.top -= (this._wrapheight - this._$picker.outerHeight())/2;
			wrapoffset.left -= (this._wrapwidth - this._$picker.outerWidth())/2;
			return wrapoffset;
		}
		updateZoomLevel(){
			this._$zoomperc.text($.roundTo(this._zoomlevel*100,2)+'%');
			document.activeElement.blur();

			this._$zoomout[this._zoomlevel <= Zoom.min?'disable':'enable']();
			this._$zoomin[this._zoomlevel >= Zoom.max?'disable':'enable']();
		}
		updatePositions(top,left,resized){
			this.updateZoomLevel();

			if (typeof top !== 'number')
				return;

			Statusbar.getInstance().setPosition('imageTopLeft', { top, left });
			let imgcenter = this.getImageCenterPosition(this._$imageCanvas.offset(), resized);
			Statusbar.getInstance().setPosition('imageCenter', imgcenter);
			this._$imgcExpected.css({
				top: imgcenter.top-5,
				left: imgcenter.left-5,
			});
			Statusbar.getInstance().setPosition('pickerCenter', this.getWrapCenterPosition());
		}
		setZoomLevel(perc, center){
			let size = this._$imageCanvas.data('size');
			if (typeof size !== 'object')
				return;

			let newzoomlevel = $.rangeLimit(perc, false, Zoom.min, Zoom.max),
				newsize,
				oldzoomlevel;
			if (newzoomlevel !== this._zoomlevel){
				newsize = $.scaleResize(size.width, size.height, {scale: newzoomlevel});
				oldzoomlevel = this._zoomlevel;
				this._zoomlevel = newsize.scale;
			}
			else {
				newsize = {
					width: this._$imageCanvas.width(),
					height: this._$imageCanvas.height(),
				};
				oldzoomlevel = this._zoomlevel;
			}

			let zoomed = this.getTopLeft(this.getImagePosition(), newzoomlevel/oldzoomlevel, center);
			this._$imageOverlay.add(this._$imageCanvas).add(this._$svgWrap).css({
				top: zoomed.top,
				left: zoomed.left,
				width: newsize.width,
				height: newsize.height,
			});
			this._$svgElement.attr('viewBox','0 0 '+newsize.width+' '+newsize.height);

			this.updatePositions(zoomed.top,zoomed.left,newsize);
		}
		setZoomFit(){
			this._fitImageHandler((size, wide) => $.scaleResize(size.width, size.height, wide ? {height:this._wrapheight} : {width:this._wrapwidth}));
		}
		setZoomOriginal(){
			this._fitImageHandler((size, wide) => ({
				width: size.width,
				height: size.height,
				scale: 1,
			}));
		}
		_fitImageHandler(nscalc){
			let size = this._$imageCanvas.data('size');
			if (typeof size !== 'object')
				return;

			let wrapwide = this._wrapwidth > this._wrapheight,
				square = size.width === size.height,
				wide = square ? wrapwide : size.width > size.height,
				newsize = nscalc(size, wide),
				top = wide && !square ? 0 : (this._wrapheight-newsize.height)/2,
				left = wide || square ? (this._wrapwidth-newsize.width)/2 : 0;
			this._$imageOverlay.add(this._$imageCanvas).add(this._$svgWrap).css({
				top: top,
				left: left,
				width: newsize.width,
				height: newsize.height,
			});
			this._$svgElement.attr('viewBox','0 0 '+newsize.width+' '+newsize.height);
			this._zoomlevel = newsize.scale;
			this.setZoomLevel(this._zoomlevel);
		}
		resizeHandler(){
			$.throttle(250, () => {
				this._wrapwidth = this._$picker.innerWidth();
				this._wrapheight = this._$picker.innerHeight();

				if (typeof this._zoomlevel === 'number')
					this.setZoomLevel(this._zoomlevel);

				Statusbar.getInstance().setPosition('pickerCenter', this.getWrapCenterPosition());
				this._$svgElement.attr('viewBox','0 0 '+this._wrapwidth+' '+this._wrapheight);
			});
		}
		setImage(src, callback){
			if (this._$picker.hasClass('loading'))
				throw new Error('The picker is already loading another image');

			this._$picker.addClass('loading');
			const image = new Image();
			$(image).attr('src', src).on('load',() => {
				this._$picker.removeClass('loading');
				this._$imageCanvas.css('opacity',0);
				this._$imageCanvas.appendTo(this._$picker).data('size',{
					width: image.width,
					height: image.height,
				});
				this._$loader.detach();

				this._$imageCanvas[0].width = image.width;
				this._$imageCanvas[0].height = image.height;
				this._$imageCanvas[0].getContext('2d').drawImage(image, 0, 0, image.width, image.height, 0, 0, image.width, image.height);

				this._$zoomfit.triggerHandler('click');
				this._$imageCanvas.fadeTo(500, 1);
				let initial,
					initialmouse;
				this._movehandler = $.throttle(50,e => {
					e.preventDefault();

					let mouse = {
							top: e.pageY,
							left: e.pageX,
						},
						wrapoffset = this.getWrapPosition(),
						top = Math.round(Math.round(((initial.top+(mouse.top-initialmouse.top))-wrapoffset.top)/this._zoomlevel)*this._zoomlevel),
						left = Math.round(Math.round(((initial.left+(mouse.left-initialmouse.left))-wrapoffset.left)/this._zoomlevel)*this._zoomlevel);
					this._$imageOverlay.add(this._$imageCanvas).add(this._$svgWrap).css({
						top, left,
					});

					this.updatePositions(top, left, this.getImageSize());
				});

				$body.on('mousedown',e => {
					if (!$(e.target).is(this._$imageOverlay))
						return;

					e.preventDefault();
					initial = this._$imageOverlay.offset();
					initialmouse = {
						top: e.pageY,
						left: e.pageX,
					};
					$body.on('mousemove',this._movehandler);
				});
				$body.on('mouseup mouseleave blur',() => {
					$body.off('mousemove',this._movehandler);
				});

				this._hasImage = true;
				$.callCallback(callback);
			});
		}
		clearImage(){
			if (!this._hasImage)
				return;

			if (typeof this._movehandler === 'function')
				$body.off('mousemove', this._movehandler);
			this._$imageCanvas.detach();
			this._$imageCanvas.removeData('size');
			this._$imageCanvas[0].getContext('2d').clearRect(0, 0, this._$imageCanvas[0].width, this._$imageCanvas[0].height);
			this._hasImage = false;
			Statusbar.getInstance().setColorAt();
			Statusbar.getInstance().setPosition('mouse');
			this._zoomlevel = 1;
			this.updateZoomLevel();
			$.Dialog.close();
		}
	}

	// Create instances
	Menubar.getInstance();
	Statusbar.getInstance();
	ColorPicker.getInstance();

	$(document).on('keydown',$.throttle(200,function(e){
		const tagname = e.target.tagName.toLowerCase();
		console.log(e.target);
		if ((tagname === 'input' && e.target.type !== 'file') || tagname === 'textarea' || e.target.getAttribute('contenteditable') !== null)
			return;

		switch (e.keyCode){
			case Key['0']:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomFit().trigger('click');
			break;
			case Key['1']:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomOriginal().trigger('click');
			break;
			default:
				return;
		}

		e.preventDefault();
	}));
	// http://stackoverflow.com/a/17545260/1344955
	$(document).on('paste', '[contenteditable]', function(e){
		let text = '';
		let $this = $(this);

		if (e.clipboardData)
			text = e.clipboardData.getData('text/plain');
		else if (window.clipboardData)
			text = window.clipboardData.getData('Text');
		else if (e.originalEvent.clipboardData)
			text = $.mk('div').text(e.originalEvent.clipboardData.getData('text'));

		if (document.queryCommandSupported('insertText')){
			document.execCommand('insertHTML', false, $(text).html());
			return false;
		}
		else {
			$this.find('*').each(function(){
				$(this).addClass('within');
			});

			setTimeout(function(){
				$this.find('*').each(function(){
					$(this).not('.within').contents().unwrap();
				});
			}, 1);
		}
	});
	$body.on('mouseenter','[data-info]',function(){
		Statusbar.getInstance().setInfo($(this).attr('data-info'));
	}).on('mouseleave','[data-info]',function(){
		Statusbar.getInstance().setInfo();
	});

	window.Plugin = pluginScope;
})(jQuery);
