/* Color Picker | by @SeinopSys + Trildar & Masem | for gh:ponydevs/MLPVC-RR */
/* global $w,$body,CryptoJS */
(function($, undefined){
	'use strict';
	const pluginScope = {
		menubar: undefined,
		statusbar: undefined,
		tabbar: undefined,
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
		},
		clearCanvas = canvas => { canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height) };

	class PickingArea {
		constructor(){

		}
		getAverageOf(pixelArray){

		}
	}

	class SquarePickingArea extends PickingArea {
		constructor(boundingRect){
			super();
			this.boundingRect =  boundingRect;
		}
		getPixels(){

		}
		getAverageOf(){

		}
	}

	class CirclePickingArea extends PickingArea {
		constructor(boundingRect, slices){
			super();
			this.boundingRect =  boundingRect;
			this.slices = slices;
		}
		getPixels(){

		}
		getAverageOf(){

		}
	}

	class Geometry {
		static calcRectanglePoints(cx, cy, side){
			const halfside = Math.floor(side/2);
			return {
				sideLength: side,
				topLeft: {
					x: cx-halfside,
					y: cy-halfside,
				},
			};
		}
		static distance(x, y, x0 = 0, y0 = 0){
			return Math.sqrt((Math.pow(y0-y, 2)) + Math.pow(x0-x, 2));
		}
		static calcCircleSlices(diamataer){
			const radius = diamataer/2;
			let slices = new Array(diamataer);
			$.each(slices,i => {
				slices[i] = new Array(diamataer);
			});
			for (let x = 0; x < slices.length; x++){
				for (let y = 0; y < slices[x].length; y++)
					slices[x][y] = Geometry.distance(x, y, radius-0.5, radius-0.5) <= radius ? 1:0;
			}
			$.each(slices,(i, slice) => {
				const tmp = slice.join('').replace(/(^|0)1/g,'$1|1').replace(/1(0|$)/g,'1|$1').split('|');
				slices[i] = {
					skip: tmp[0].length,
					length: tmp[1].length,
				};
			});

			return slices;
		}
		static snapPointToPixelGrid(value, grid){
			return Math.round(Math.round(value/grid)*grid);
		}
	}

	class ColorFormatter {
		constructor(value){
			const
				rgbaTest = /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([01]|(?:0?\.\d+)))?\)$/i,
				hexTest = /^#([a-f0-9]{3}|[a-f0-9]{6})$/i;
			value = value.trim();
			let rgba = value.match(rgbaTest);
			if (rgba && rgba[1] <= 255 && rgba[2] <= 255 && rgba[3] <= 255 && (!rgba[4] || rgba[4] <= 1)){
				this.red = parseInt(rgba[1], 10);
				this.green = parseInt(rgba[2], 10);
				this.blue = parseInt(rgba[3], 10);
				this.alpha = rgba[4] ? parseFloat(rgba[4]) : 1;
			}
			else {
				let hexmatch = value.match(hexTest);
				if (hexmatch){
					let hex = hexmatch[1];
					if (hex.length === 3)
						hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
					const rgb = $.hex2rgb('#'+hex);
					this.red = rgb.r;
					this.green = rgb.g;
					this.blue = rgb.b;
					this.alpha = 1;
				}
				else throw new Error('Unrecognized color format: '+value);
			}
			this.opacity = Math.round(this.alpha*100);
		}
		toString(){
			if (this.alpha === 1)
				return $.rgb2hex({ r: this.red, g: this.green, b: this.blue });

			return `rgba(${this.red},${this.green},${this.blue},${this.alpha})`;
		}
	}

	window.ColorFormatter = ColorFormatter;

	class Menubar {
		constructor(){
			this._$menubar = $('#menubar');
			this._$menubar.children().children('a.dropdown').on('click',e => {
				e.preventDefault();
				e.stopPropagation();

				this._$menubar.addClass('open');
				$(e.target).trigger('mouseenter');
			}).on('mouseenter',e => {
				if (!this._$menubar.hasClass('open'))
					return;
				const $this = $(e.target);
				if (!$this.hasClass('dropdown'))
					return;

				this._$menubar.find('a.active').removeClass('active');
				$this.addClass('active').next().removeClass('hidden');
			});
			this._$filein = $.mk('input','screenshotin').attr({
				type: 'file',
				accept: 'image/png,image/jpeg',
				tabindex: -1,
				'class': 'fileinput',
			}).prop('multiple',true).appendTo($body);
			this._$openImage = $('#open-image').on('click',e => {
				e.preventDefault();

				this._$filein.trigger('click');
			});
			this._$closeActiveTab = $('#close-active-tab').on('click', e => {
				e.preventDefault();

				const activeTab = Tabbar.getInstance().getActiveTab();
				if (!activeTab)
					return;

				activeTab.getElement().find('.close').trigger('click');
			});
			this._$filein.on('change',() => {
				const files = this._$filein[0].files;
				if (files.length === 0)
					return;

				const s = files.length !== 1 ? 's' : '';
				$.Dialog.wait('Opening file'+s,'Reading opened file'+s+', please wait');

				let ptr = 0;
				const next = () => {
					if (typeof files[ptr] === 'undefined'){
						// All files read, we're done
						this._$openImage.removeClass('disabled');
						this._$filein.val('');
						this.updateCloseActiveTab();
						$.Dialog.close();
						return;
					}
					this.handleFileOpen(files[ptr],success => {
						if (success){
							ptr++;
							return next();
						}

						this._$openImage.removeClass('disabled');
						$.Dialog.fail('Drag and drop',`Failed to read file #${ptr}, aborting`);
					});
				};
				next();
			});
			const $aboutTemplate = $('#about-dialog-template').children();
			this._$aboutDialog = $('#about-dialog').on('click',function(){
				$.Dialog.info('About',$aboutTemplate.clone());
			});

			$body.on('click',() => {
				this._$menubar.removeClass('open');
				this._$menubar.find('a.active').removeClass('active');
				this._$menubar.children('li').children('ul').addClass('hidden');
			});
		}
		updateCloseActiveTab(){
			this._$closeActiveTab[Tabbar.getInstance().hasTabs()?'removeClass':'addClass']('disabled');
		}
		/** @return {Menubar} */
		static getInstance(){
			if (typeof pluginScope.menubar === 'undefined')
				pluginScope.menubar = new Menubar();
			return pluginScope.menubar;
		}
		handleFileOpen(file, callback){
			if (!/^image\/(png|jpeg)$/.test(file.type)){
				$.Dialog.fail('Invalid file', 'You may only use PNG or JPG images with this tool');
				callback(false);
				return;
			}
	        const reader = new FileReader();
	        reader.onload = () => {
				ColorPicker.getInstance().openImage(reader.result, file.name, callback);
	        };
	        reader.readAsDataURL(file);
		}
	}

	class Statusbar {
		constructor(){
			this._$el = $('#statusbar');
			this._$info = this._$el.children('.info');
			this._$pos = this._$el.children('.pos');
			this._$colorat = this._$el.children('.colorat');
			this._$color = this._$colorat.children('.color');
			this._$opacity = this._$colorat.children('.opacity');
			this.infoLocked = false;
			this.Pos = {
				mouse: 'mousepos',
			};

			this[`_$${this.Pos.mouse}`] = this._$pos.children('.mouse');
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
		setPosition(which, tl = { top: NaN, left: NaN }, zoomlevel = 1){
			const elkey = this.Pos[which];
			if (typeof elkey !== 'string')
				throw new Error('[Statusbar.setPosition] Invalid position display key: '+which);

			if (zoomlevel !== 1){
				tl.left *= zoomlevel;
				tl.top *= zoomlevel;
			}

			this[`_$${elkey}`].text(isNaN(tl.left) || isNaN(tl.top) ? '' : `${$.roundTo(tl.left,2)},${$.roundTo(tl.top,2)}`);
		}
		setColorAt(hex = '', opacity = ''){
			if (hex.length){
				this._$color.css({
					backgroundColor: hex,
					color: $.yiq(hex) > 127 ? 'black' : 'white',
				});
			}
			else this._$color.css({
				backgroundColor: '',
				color: '',
			});

			this._$color.text(hex||'');
			this._$opacity.text(opacity||'');
		}
	}

	const
		areaColorFormUpdatePreview = function($form, data){
			const $preview = $form.find('.color-preview');

			$preview.html($.mk('div').css('background-color',`rgba(${data.red},${data.green},${data.blue},${data.opacity/100})`));
		},
		areaColorFormInputChange = function(e){
			const
				$form =  $(e.target).closest('form'),
				data = $form.mkData();

			areaColorFormUpdatePreview($form, data);
		},
		$AreaColorForm = $.mk('form','set-area-color').append(
			`<div class="label">
				<span>Red, Green, Blue (0-255)</span>
				<div class="input-group-3">
					<input type="number" min="0" max="255" step="1" name="red"   class="change input-red">
					<input type="number" min="0" max="255" step="1" name="green" class="change input-green">
					<input type="number" min="0" max="255" step="1" name="blue"  class="change input-blue">
				</div>
			</div>
			<div class="label">
				<span>Opacity (%)</span>
				<input type="number" min="0" max="100" step="1" name="opacity" class="change">
			</div>
			<div>
				<div class="color-preview"></div>
			</div>`
		).on('change keyup input','.change',areaColorFormInputChange).on('set-color',function(_, color){
			const $form = $(this);
			$.each(['red','green','blue','opacity'],(_,key) => {
				$form.find(`input[name="${key}"]`).val(color[key]);
			});
			areaColorFormUpdatePreview($form, color);
		});
	class Tab {
		constructor(imageName, hash){
			this._fileHash = hash;
			this._imgel = new Image();
			this._imgdata = {};
			this._pickingAreas = [];
			this.file = {
				extension: undefined,
				name: undefined,
			};
			this.setName(imageName);
			this._$pickAreaColorDisplay = $.mk('span').attr({'class':'pickcolor','data-info':'Color of the picking areas on this specific tab'});
			this._$el = $.mk('li').attr('class','tab').append(
				this._$pickAreaColorDisplay,
				$.mk('span').attr({'class':'filename','data-info':this.file.name+'.'+this.file.extension}).text(this.file.name),
				$.mk('span').attr('class','fileext').text(this.file.extension),
				$.mk('span').attr({'class':'close','data-info':'Close tab'}).text("\u00d7")
			);
			this.setPickingAreaColor('rgba(255,0,0,.5)');
			this._$el.on('click',e => {
				e.preventDefault();

				switch(e.target.className){
					case 'close':
						return $.Dialog.confirm('Close tab','Please confirm that you want to close this tab.',['Close','Cancel'],sure => {
							if (!sure) return;

							this.close();
							$.Dialog.close();
						});
					case 'pickcolor':
						return $.Dialog.request('Select a picking area color',$AreaColorForm.clone(true,true),'Set',$form => {
							$form.triggerHandler('set-color', [this.getPickingAreaColor()]);
							$form.on('submit',e => {
								e.preventDefault();

								const data = $form.mkData();
								$.Dialog.wait(false, 'Setting picking area color');

								try {
									this.setPickingAreaColor(`rgba(${data.red},${data.green},${data.blue},${Math.round(data.opacity)/100})`);
								}
								catch(err){
									return $.Dialog.fail(false, e.message);
								}

								$.Dialog.close();
							});
						});
				}

				Tabbar.getInstance().activateTab(this);
			});
		}
		activate(){
			this._$el.addClass('active');
		}
		deactivate(){
			this._$el.removeClass('active');
		}
		isActive(){
			return this._$el.hasClass('active');
		}
		getFileHash(){
			return this._fileHash;
		}
		setImage(src, callback){
			$(this._imgel).attr('src', src).on('load',() => {
				this._imgdata.size = {
					width: this._imgel.width,
					height: this._imgel.height,
				};

				callback(true);
			}).on('error',() => {
				callback(false);
			});
		}
		setName(imageName){
			let fileparts = imageName.split(/\./g);
			this.file.extension = fileparts.pop();
			this.file.name = fileparts.join('.');
		}
		getImageSize(){
			return this._imgdata.size;
		}
		setImagePosition(pos){
			this._imgdata.position = pos;
		}
		getImagePosition(){
			return this._imgdata.position;
		}
		getElement(){
			return this._$el;
		}
		placeArea(pos, size, square = true){
			const boundingRect = Geometry.calcRectanglePoints(pos.left, pos.top, size);
			if (square){
				this.addPickingArea(new SquarePickingArea(boundingRect));
			}
			else {
				const slices = Geometry.calcCircleSlices(pos.left, pos.top, size);
				this.addPickingArea(new CirclePickingArea(boundingRect,slices));
			}
		}
		/** @param {PickingArea} area */
		addPickingArea(area){
			this._pickingAreas.push(area);
		}
		getPickingAreas(){
			return this._pickingAreas;
		}
		clearPickingAreas(){
			this._pickingAreas = [];
			if (!this.isActive())
				return;

			ColorPicker.getInstance().redrawPickingAreas();
		}
		getPickingAreaColor(){
			return this._pickingAreaColor;
		}
		setPickingAreaColor(color){
			this._pickingAreaColor = new ColorFormatter(color);
			this._$pickAreaColorDisplay.html($.mk('span').css('background-color',this._pickingAreaColor.toString()));
			if (!this.isActive())
				return;

			ColorPicker.getInstance().redrawPickingAreas();
		}
		drawImage(){
			ColorPicker.getInstance().getImageCanvasCtx().drawImage(this._imgel, 0, 0, this._imgdata.size.width, this._imgdata.size.height, 0, 0, this._imgdata.size.width, this._imgdata.size.height);
		}
		close(){
			Tabbar.getInstance().closeTab(this);
		}
	}

	class Tabbar {
		constructor(){
			this._$tabbar = $('#tabbar');
			this._activeTab = false;
			this._tabStorage = [];
		}
		/** @return {Tabbar} */
		static getInstance(){
			if (typeof pluginScope.tabbar === 'undefined')
				pluginScope.tabbar = new Tabbar();
			return pluginScope.tabbar;
		}
		/** @return {Tab} */
		newTab(...args){
			const tab = new Tab(...args);
			this._tabStorage.push(tab);
			this.updateTabs();
			return tab;
		}
		/** @param {int|Tab} index */
		activateTab(index){
			if (index instanceof Tab)
				index = this.indexOf(index);
			if (this._tabStorage[index] instanceof Tab){
				this._activeTab = index;
			}
			else this._activeTab = false;
			$.each(this._tabStorage,(i,tab) => {
				if (i === this._activeTab)
					tab.activate();
				else tab.deactivate();
			});
			if (this._activeTab !== false)
				ColorPicker.getInstance().openTab(this._tabStorage[this._activeTab]);
		}
		/** @param {Tab} tab */
		indexOf(tab){
			let index = parseInt(tab.getElement().attr('data-ix'),10);
			if (isNaN(index))
				$.each(this._tabStorage,(i,el) => {
					if (el === tab){
						index = i;
						return false;
					}
				});
			if (isNaN(index)){
				console.log(tab); // KEEP!
				throw new Error('Could not find index of the tab logged above');
			}
			return index;
		}
		updateTabs(){
			this._$tabbar.children().detach();
			$.each(this._tabStorage,(i,tab) => {
				this._$tabbar.append(tab.getElement().attr('data-ix',i));
			});
		}
		/** @return {Tab|undefined} */
		getActiveTab(){
			return this._activeTab !== false ? this._tabStorage[this._activeTab] : undefined;
		}
		/** @return {Tab[]} */
		getTabs(){
			return this._tabStorage;
		}
		hasTabs(){
			return this._tabStorage.length > 0;
		}
		closeTab(whichTab){
			const
				tabIndex = this.indexOf(whichTab),
				tabCount = this._tabStorage.length,
				tabsLeft = tabCount > 1;
			if (!tabsLeft){
				ColorPicker.getInstance().clearImage();
				Menubar.getInstance().updateCloseActiveTab();
			}

			this._tabStorage.splice(tabIndex,1);
			if (tabsLeft)
				this.activateTab(Math.min(tabCount-1,tabIndex));
			this.updateTabs();
		}
	}

	class ColorPicker {
		constructor(){
			this._mousepos = {
				top: NaN,
				left: NaN,
			};
			this._zoomlevel = 1;
			this._moveMode = false;

			this._$picker = $('#picker');
			this.updateWrapSize();
			this._$imageOverlay = $.mk('canvas').attr('class','image-overlay');
			this._$imageCanvas = $.mk('canvas').attr('class','image-element');
			this._$mouseOverlay = $.mk('canvas').attr('class','mouse-overlay');
			this._$placeArea = $.mk('button').attr({'class':'place-area typcn typcn-starburst','data-info':'Randomly place a new square picking area on the image (hold Alt to place rounded)'}).on('click',e => {
				e.preventDefault();

				const imgsize = this.getImageCanvasSize();
				this.placeArea({ left: Math.floor(Math.random()*imgsize.width), top: Math.floor(Math.random()*imgsize.height) }, 45, !e.altKey);
			});
			this._$clearAreas = $.mk('button').attr({'class':'place-area typcn typcn-delete','data-info':'Clear all picking areas'}).on('click',e => {
				e.preventDefault();

				const activeTab = Tabbar.getInstance().getActiveTab();
				if (!activeTab)
					return;

				activeTab.clearPickingAreas();
			});
			this._$zoomin = $.mk('button').attr({'class':'zoom-in typcn typcn-zoom-in','data-info':'Zoom in (Alt+Scroll Up)'}).on('click',(e, mousepos) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel*Zoom.step, mousepos);
			});
			this._$zoomout = $.mk('button').attr({'class':'zoom-out typcn typcn-zoom-out','data-info':'Zoom out (Alt+Scroll Down)'}).on('click',(e, mousepos) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel/Zoom.step, mousepos);
			});
			this._$zoomfit = $.mk('button').attr({'class':'zoom-fit typcn typcn typcn-arrow-minimise','data-info':'Fit in view (Ctrl+0)'}).on('click',e => {
				e.preventDefault();

				this.setZoomFit();
			});
			this._$zoomorig = $.mk('button').attr({'class':'zoom-orig typcn typcn typcn-zoom','data-info':'Original size (Ctrl+1)'}).on('click',e => {
				e.preventDefault();

				this.setZoomOriginal();
			});
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

				this.updateZoomLevelInputs();
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
					this.updateZoomLevelInputs();
				$.clearSelection();
			});
			this._$actionTopLeft = $.mk('div').attr('class','actions actions-tl').append(
				$.mk('div').attr('class','editing-tools').append(
					this._$placeArea,
					this._$clearAreas
				),
				$.mk('div').attr('class','zoom-controls').append(
					this._$zoomin,
					this._$zoomout,
					this._$zoomfit,
					this._$zoomorig,
					this._$zoomperc
				)
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

			$w.on('resize', $.throttle(250,() => { this.resizeHandler() }));
			this._$picker.append(
				this._$actionTopLeft,
				this._$actionsBottomLeft,
				this._$mouseOverlay,
				this._$imageOverlay,
				this._$loader
			);

			let initial,
				initialmouse;
			$body.on('mousemove', $.throttle(50,e => {
				if (!Tabbar.getInstance().getActiveTab())
					return;

				const
					wrapoffset = this.getWrapPosition(),
					imgpos = this.getImagePosition(),
					imgsize = this.getImageCanvasSize();

				this._mousepos.top = e.pageY-wrapoffset.top;
				this._mousepos.left = e.pageX-wrapoffset.left;
				if (
					this._mousepos.top < imgpos.top ||
					this._mousepos.top > imgpos.top+imgsize.height-1 ||
					this._mousepos.left < imgpos.left ||
					this._mousepos.left > imgpos.left+imgsize.width-1
				){
					this._mousepos.top = NaN;
					this._mousepos.left = NaN;
					Statusbar.getInstance().setColorAt();
				}
				else {
					this._mousepos.top = Math.floor((this._mousepos.top-Math.floor(imgpos.top))/this._zoomlevel);
					this._mousepos.left = Math.floor((this._mousepos.left-Math.floor(imgpos.left))/this._zoomlevel);
					const p = this.getImageCanvasCtx().getImageData(this._mousepos.left, this._mousepos.top, 1, 1).data;
					Statusbar.getInstance().setColorAt($.rgb2hex({r:p[0], g:p[1], b:p[2]}), $.roundTo((p[3]/255)*100, 2)+'%');
				}
				Statusbar.getInstance().setPosition('mouse', this._mousepos);

				if (initial && initialmouse){
					let mouse = {
							top: e.pageY,
							left: e.pageX,
						},
						wrapoffset = this.getWrapPosition(),
						top = Geometry.snapPointToPixelGrid((initial.top+(mouse.top-initialmouse.top))-wrapoffset.top, this._zoomlevel),
						left = Geometry.snapPointToPixelGrid((initial.left+(mouse.left-initialmouse.left))-wrapoffset.left, this._zoomlevel);
					this._$imageOverlay.add(this._$imageCanvas).add(this._$mouseOverlay).css({ top, left });

					this.updateZoomLevelInputs();
				}
			}));
			$w.on('mousewheel',e => {
				if (!e.altKey)
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
			this._$picker.on('mousewheel',e => {
				if (e.altKey)
					return;

				e.preventDefault();

				const step = this._wrapheight*(e.shiftKey?0.1:0.025)*Math.sign(e.originalEvent.wheelDelta);
				if (e.ctrlKey)
					this.move({ left: `+=${step}px` });
				else this.move({ top: `+=${step}px` });
			});

			$body.on('mousedown',e => {
				if (!Tabbar.getInstance().getActiveTab() || !$(e.target).is(this._$imageOverlay) || !this._$imageOverlay.hasClass('draggable'))
					return;

				e.preventDefault();
				this._$imageOverlay.addClass('dragging');
				initial = this._$imageOverlay.offset();
				initialmouse = {
					top: e.pageY,
					left: e.pageX,
				};
			});
			$body.on('mouseup mouseleave blur',e => {
				if (!Tabbar.getInstance().getActiveTab())
					return;

				if (e.type === 'mouseup'){
					initial = undefined;
					initialmouse = undefined;
					this._$imageOverlay.removeClass('dragging');
				}
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
		getImageCanvasSize(){
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
		//noinspection JSMethodCanBeStatic
		placeArea(pos, size, square = true){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			activeTab.placeArea(pos, size, square);
			this.redrawPickingAreas();
		}
		redrawPickingAreas(){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			this.clearImageOverlay();
			const ctx = this.getImageOverlayCtx();
			ctx.fillStyle = activeTab.getPickingAreaColor().toString();
			$.each(activeTab.getPickingAreas(),(i, area) => {
				if (area instanceof SquarePickingArea){
					ctx.fillRect(area.boundingRect.topLeft.x, area.boundingRect.topLeft.y, area.boundingRect.sideLength, area.boundingRect.sideLength);
				}
				else if (area instanceof CirclePickingArea){
					$.each(area.slices,(i,el) => {
						const
							x = area.boundingRect.topLeft.x+el.skip,
							y = area.boundingRect.topLeft.y+i;
						ctx.fillRect(x, y, el.length, 1);
					});
				}
			});
		}
		clearImageOverlay(){
			clearCanvas(this._$imageOverlay[0]);
		}
		updateZoomLevelInputs(){
			this._$zoomperc.text($.roundTo(this._zoomlevel*100,2)+'%');
			document.activeElement.blur();

			this._$zoomout.attr('disabled', this._zoomlevel <= Zoom.min);
			this._$zoomin.attr('disabled', this._zoomlevel >= Zoom.max);
		}
		setZoomLevel(perc, center){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			const size = activeTab.getImageSize();
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
			this.move({
				top: zoomed.top,
				left: zoomed.left,
				width: newsize.width,
				height: newsize.height,
			});

			this.updateZoomLevelInputs();
		}
		setZoomFit(){
			this._fitImageHandler(size => {
				const
					wrapwide = this._wrapwidth > this._wrapheight,
					square = size.width === size.height,
					wide = square ? wrapwide : size.width > size.height;
				let ret = $.scaleResize(size.width, size.height, wide ? {height:this._wrapheight} : {width:this._wrapwidth});
				if (wrapwide){
					if (ret.width > this._wrapwidth){
						ret = $.scaleResize(ret.width, ret.height, {width:this._wrapwidth});
					}
					else if (ret.height > this._wrapheight){
						ret = $.scaleResize(ret.width, ret.height, {height:this._wrapheight});
					}
				}
				if (!wrapwide){
					if (ret.height > this._wrapheight){
						ret = $.scaleResize(ret.width, ret.height, {height:this._wrapheight});
					}
					else if (ret.width > this._wrapwidth){
						ret = $.scaleResize(ret.width, ret.height, {width:this._wrapwidth});
					}
				}
				return ret;
			});
		}
		setZoomOriginal(){
			this._fitImageHandler((size, wide) => ({
				width: size.width,
				height: size.height,
				scale: 1,
			}));
		}
		_fitImageHandler(nscalc){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			const size = activeTab.getImageSize();
			let newsize = nscalc(size),
				top = (this._wrapheight-newsize.height)/2,
				left = (this._wrapwidth-newsize.width)/2;
			this.move({
				top: top,
				left: left,
				width: newsize.width,
				height: newsize.height,
			});
			this._zoomlevel = newsize.scale;
			this.setZoomLevel(this._zoomlevel);
		}
		move(pos, restoring = false){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			this._$imageOverlay.add(this._$imageCanvas).add(this._$mouseOverlay).css(pos);
			if (!restoring)
				activeTab.setImagePosition({
					top: this._$imageOverlay.css('top'),
					left: this._$imageOverlay.css('left'),
					width: this._$imageOverlay.css('width'),
					height: this._$imageOverlay.css('height'),
				});
		}
		updateWrapSize(){
			this._wrapwidth = this._$picker.innerWidth();
			this._wrapheight = this._$picker.innerHeight();
		}
		resizeHandler(){
			this.updateWrapSize();

			if (typeof this._zoomlevel === 'number')
				this.setZoomLevel(this._zoomlevel);

			Statusbar.getInstance().setPosition('pickerCenter', this.getWrapCenterPosition());
		}
		_setCanvasSize(w,h){
			this._$imageOverlay[0].width =
			this._$imageCanvas[0].width = w;
			this._$imageOverlay[0].height =
			this._$imageCanvas[0].height = h;
		}
		openImage(src, fname, callback){
			if (this._$picker.hasClass('loading'))
				throw new Error('The picker is already loading another image');

			this._$picker.addClass('loading');
			Statusbar.getInstance().setInfo();

			// Check if we already have the same image open
			const
				hash = CryptoJS.MD5(src).toString(),
				openTabs = Tabbar.getInstance().getTabs();
			let matchingTab;
			$.each(openTabs,(i,tab) => {
				if (tab.getFileHash() === hash){
					matchingTab = tab;
					return false;
				}
			});
			if (typeof matchingTab !== 'undefined'){
				// The image has already been opened
				this._$picker.removeClass('loading');
				Tabbar.getInstance().activateTab(matchingTab);
				callback(true);

				return;
			}

			const tab = Tabbar.getInstance().newTab(fname, hash);
			tab.setImage(src,success => {
				this._$picker.removeClass('loading');

				if (success)
					Tabbar.getInstance().activateTab(tab);
				else $.Dialog.fail('Oh no','The provided image could not be loaded. This is usually caused by attempting to open a file that is, in fact, not an image.');

				callback(success);
			});
		}
		/** @param {Tab} tab */
		openTab(tab){
			const imgsize = tab.getImageSize();
			if (!imgsize)
				throw new Error('Attempt to open a tab without an image');

			this._$imageCanvas.appendTo(this._$picker);

			this._setCanvasSize(imgsize.width, imgsize.height);
			tab.drawImage();

			const storedimgpos = tab.getImagePosition();
			if (!storedimgpos)
				this.setZoomFit();
			else this.move(storedimgpos, true);

			this.redrawPickingAreas();
		}
		clearImage(){
			if (!Tabbar.getInstance().getActiveTab())
				return;

			this._$imageCanvas.detach();
			clearCanvas(this._$imageCanvas[0]);
			clearCanvas(this._$imageOverlay[0]);
			Statusbar.getInstance().setColorAt();
			Statusbar.getInstance().setPosition('mouse');
			this._zoomlevel = 1;
			this.updateZoomLevelInputs();
			$.Dialog.close();
		}
		moveMode(enable){
			if (enable && !this._moveMode){
				this._moveMode = true;
				this._$imageOverlay.addClass('draggable');
			}
			else if (!enable && this._moveMode){
				this._moveMode = false;
				this._$imageOverlay.removeClass('draggable dragging');
			}
		}
		getImageCanvasCtx(){
			return this._$imageCanvas[0].getContext('2d');
		}
		getImageOverlayCtx(){
			return this._$imageOverlay[0].getContext('2d');
		}
		getMouseOverlayCtx(){
			return this._$mouseOverlay[0].getContext('2d');
		}
	}

	// Create instances
	Menubar.getInstance();
	Statusbar.getInstance();
	Tabbar.getInstance();
	ColorPicker.getInstance();

	$(document).on('keydown',function(e){
		const tagname = e.target.tagName.toLowerCase();
		if ((tagname === 'input' && e.target.type !== 'file') || tagname === 'textarea' || e.target.getAttribute('contenteditable') !== null)
			return;

		switch (e.keyCode){
			case Key['0']:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomFit();
			break;
			case Key['1']:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomOriginal();
			break;
			case Key.Space:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().moveMode(true);
			break;
			default:
				return;
		}

		e.preventDefault();
	});
	$(document).on('keyup',function(e){
		const tagname = e.target.tagName.toLowerCase();
		if ((tagname === 'input' && e.target.type !== 'file') || tagname === 'textarea' || e.target.getAttribute('contenteditable') !== null)
			return;

		switch (e.keyCode){
			case Key.Space:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().moveMode(false);
			break;
			case Key.Alt:
				// We just want to prevent the focus to the menu icon
			break;
			default:
				return;
		}

		e.preventDefault();
	});
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
	}).on('dragover dragend',function(e){
		e.stopPropagation();
		e.preventDefault();
	}).on('drop',function(e){
		e.preventDefault();

		const files = e.originalEvent.dataTransfer.files;
		if (files.length === 0)
			return;

		const s = files.length !== 1 ? 's' : '';
		$.Dialog.wait('Drag and drop','Reading dropped file'+s+', please wait');

		let ptr = 0;
		(function next(){
			if (typeof files[ptr] === 'undefined'){
				// All files read, we're done
				$.Dialog.close();
				return;
			}
			Menubar.getInstance().handleFileOpen(files[ptr], success => {
				if (success){
					ptr++;
					return next();
				}

				$.Dialog.fail('Drag and drop',`Failed to read file #${ptr}, aborting`);
			});
		})();
	});

	window.Plugin = pluginScope;
})(jQuery);
