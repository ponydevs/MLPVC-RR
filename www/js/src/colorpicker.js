/* Color Picker | by @SeinopSys + Trildar & Masem | for gh:ponydevs/MLPVC-RR */
/* global $w,$body,CryptoJS,Key,mk */
(function($, undefined){
	'use strict';
	const pluginScope = {
		menubar: undefined,
		statusbar: undefined,
		tabbar: undefined,
		picker: undefined,
		settings: undefined,
	};

	// TODO Zoom tool that utilizes LMB/RMB/Scroll

	let filterPrefix = '';
	const
		Tools = {
			hand: 0,
			picker: 1,
			zoom: 2,
		},
		Zoom = {
			min: 0.004,
			max: 32,
			step: 1.1,
		},
		clearCanvas = ctx => { ctx.clearRect(0,0,ctx.canvas.width,ctx.canvas.height) },
		// https://stackoverflow.com/a/6860916/1344955
		S4 = function() {
			// jshint -W016
	        //noinspection NonShortCircuitBooleanExpressionJS
			return (((1+Math.random())*0x10000)|0).toString(16).substring(1);
	    },
		guid = () => (S4()+S4()+"-"+S4()+"-"+S4()+"-"+S4()+"-"+S4()+S4()+S4()),
		isMac = typeof navigator.userAgent === 'string' && /(macos|iphone|os ?x|ip[ao]d)/i.test(navigator.userAgent),
		filterSupport = (function(){
			// https://stackoverflow.com/a/11047247/1344955
			function test(checkPrefix = false){
				if (checkPrefix === undefined)
					checkPrefix = false;
				const el = document.createElement('div');
				el.style.cssText = (checkPrefix ? '-webkit-' : '') + 'filter: blur(2px)';
				const test1 = (el.style.length !== 0);
				const test2 = (
					document.documentMode === undefined
					|| document.documentMode > 9
				);
				return test1 && test2;
			}

			if (test() === true){
				return true;
			}
			else if (test(true) === true){
				filterPrefix = '-webkit-';
				return true;
			}
			return false;
		})();


	class Pixel {
		constructor(r,g,b,a, overrideAlpha = false){
			this.red = r;
			this.green = g;
			this.blue = b;
			this.alpha = overrideAlpha !== false ? overrideAlpha : a;
		}
		invert(){
			this.red = 255-this.red;
			this.green = 255-this.green;
			this.blue = 255-this.blue;

			return this;
		}
	}

	class ImageDataHelper {
		/**
		 * Extracts the pixel data from an ImageData object into a more easy-to-use format
		 * A filter function can be specified which is passed the x and y coordinates
		 * of the looped color and if a boolean false value is returned the color is skipped.
		 *
		 * @param {ImageData} imgd
		 * @param {Function} filter
		 */
		static getPixels(imgd, filter = undefined){
			const
				pixels = [],
				useFilter = typeof filter === 'function';

			for (let ptr = 0; ptr < imgd.data.length; ptr += 4){
				if (useFilter){
					const pxix = ptr/4;
					const
						x = pxix % imgd.width,
						y = Math.floor(pxix / imgd.width);
					if (filter(x,y) === false)
						continue;
				}

				pixels.push(new Pixel(...imgd.data.slice(ptr,ptr+4)));
			}

			return pixels;
		}
	}

	class PickingArea {
		constructor(boundingRect){
			this.id = guid();
			this.boundingRect =  boundingRect;
			this._tab = undefined;
		}
		belongsToTab(tab){
			this._tab = tab;
		}
		/**
		 * @param {Pixel[]} pixelArray
		 * @return {Pixel}
		 */
		static averageColor(pixelArray){
			const l = pixelArray.length;
			let r = 0, g = 0, b = 0, a = 0;
			$.each(pixelArray,(_,pixel) => {
				r += pixel.red;
				g += pixel.green;
				b += pixel.blue;
				a += pixel.alpha;
			});
			return new Pixel(Math.round(r/l), Math.round(g/l), Math.round(b/l), Math.round(a/l));
		}
		_getImageData(){
			if (!(this._tab instanceof Tab))
				throw new Error('Attempting to get image data without being bound to a tab');

			const ctx = this._tab.getCanvasCtx();

			const
				x = Math.max(0, this.boundingRect.topLeft.x),
				y = Math.max(0, this.boundingRect.topLeft.y),
				width = Math.min(this.boundingRect.sideLength, ctx.canvas.width-x),
				height = Math.min(this.boundingRect.sideLength, ctx.canvas.height-y);
			return ctx.getImageData(x, y, width, height);
		}
		_getPixels(filter = undefined){
			return ImageDataHelper.getPixels(this._getImageData(), filter);
		}
		static draw(area, ctx){
			if (area instanceof SquarePickingArea){
				ctx.fillRect(area.boundingRect.topLeft.x, area.boundingRect.topLeft.y, area.boundingRect.sideLength, area.boundingRect.sideLength);
			}
			else if (area instanceof RoundedPickingArea){
				$.each(area.slices,(i,el) => {
					const
						x = area.boundingRect.topLeft.x+el.skip,
						y = area.boundingRect.topLeft.y+i;
					ctx.fillRect(x, y, el.length, 1);
				});
			}
		}
		/**
		 * @param {object} pos
		 * @param {int}    size
		 * @param {bool}   square
		 * @return {PickingArea}
		 */
		static getArea(pos, size, square){
			const boundingRect = Geometry.calcRectanglePoints(pos.left, pos.top, size);
			if (square){
				return new SquarePickingArea(boundingRect);
			}
			else {
				const slices = Geometry.calcCircleSlices(size);
				return new RoundedPickingArea(boundingRect,slices);
			}
		}
	}

	class SquarePickingArea extends PickingArea {
		constructor(boundingRect){
			super(boundingRect);
		}
		/**
		 * @return {Pixel}
		 */
		getAverageColor(){
			return PickingArea.averageColor(this._getPixels());
		}
	}

	class RoundedPickingArea extends PickingArea {
		constructor(boundingRect, slices){
			super(boundingRect);
			this.slices = slices;
		}
		/** @return {Pixel} */
		getAverageColor(){
			return PickingArea.averageColor(this._getPixels( (x, y) => this.slices[y].skip < x && x < this.slices[y].skip+this.slices[y].length ));
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
		static calcCircleSlices(diameter){
			const radius = diameter/2;
			let slices = new Array(diameter);
			$.each(slices,i => {
				slices[i] = new Array(diameter);
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
		/*static snapPointToPixelGrid(value, grid){
			return Math.round(Math.round(value/grid)*grid);
		}*/
	}

	const
		availableSettings = {
			'pickingAreaSize': 25,
			'pickerWidth': '85%',
			'sidebarColorFormat': 'hex',
		},
		settingsLSKey = 'picker_settings';
	class PersistentSettings {
		constructor(){
			this._settings = $.extend(true,{},availableSettings);

			let storedSettings = {};
			try {
				storedSettings = JSON.parse($.LocalStorage.get(settingsLSKey));
			}
			catch(_){}
			if (storedSettings)
				$.each(availableSettings,k => {
					if (typeof storedSettings[k] !== 'undefined' && storedSettings[k] !== this._settings[k])
						this._settings[k] = storedSettings[k];
				});

			this.save();
		}
		/** @return {PersistentSettings} */
		static getInstance(){
			if (typeof pluginScope.settings === 'undefined')
				pluginScope.settings = new PersistentSettings();
			return pluginScope.settings;
		}
		get(k){
			if (typeof availableSettings[k] === 'undefined')
				throw new Error('Trying to get non-existant setting: '+k);

			return this._settings[k];
		}
		set(k,v){
			if (typeof availableSettings[k] === 'undefined')
				throw new Error('Trying to set non-existant setting: '+k);
			this._settings[k] = v;

			this.save();
		}
		save(){
			$.LocalStorage.set(settingsLSKey,JSON.stringify(this._settings));
		}
		static clear(){
			$.LocalStorage.remove(settingsLSKey);
		}
	}

	class ColorFormatter {
		constructor(value){
			const
				rgbaTest = /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([01]|(?:0?\.\d+)))?\)$/i,
				hexTest = /^#([a-f0-9]{3}|[a-f0-9]{6})$/i;
			if (typeof value === 'string'){
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
			}
			else if (!isNaN(value.red) && !isNaN(value.green) && !isNaN(value.blue)){
				this.red = value.red;
				this.green = value.green;
				this.blue = value.blue;
				if (value instanceof Pixel)
					this.alpha = value.alpha/255;
				else this.alpha = isNaN(value.alpha) ? 1 : value.alpha;
			}
			else throw new Error('Unrecognized color format: '+JSON.stringify(value));
			this.opacity = Math.round(this.alpha*100);
		}
		toString(forceHex = false){
			if (this.alpha === 1 || forceHex)
				return this.toHexString();

			return this.toRGBString();
		}
		toHexString(){
			return $.rgb2hex({ r: this.red, g: this.green, b: this.blue });
		}
		toRGBString(ignoreAlpha = false){
			const showAlpha = !ignoreAlpha && this.alpha !== 1;
			return `rgb${showAlpha?'a':''}(${this.red},${this.green},${this.blue}${showAlpha?','+this.alpha:''})`;
		}
	}

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

				this._$menubar.find('a.active').removeClass('active').next().addClass('hidden');
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

				this.requestFileOpen();
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
			this._$clearSettings = $('#clear-settings').on('click',e => {
				e.preventDefault();

				$.Dialog.confirm('Clear settings','<p>The editor remembers your picking area size, sidebar color format and sidebar width settings.</p><p>If you want to reset these to their defaults, click the "Clear settings" button below.</p><p><strong>This will reload the picker, and any progress will be lost.</strong></p>',['Clear settings','Nevermind'],sure => {
					if (!sure) return;

					$.Dialog.wait(false, 'Clearing settings');

					PersistentSettings.clear();
					window.location.reload();
				});
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
		requestFileOpen(){
			this._$filein.trigger('click');
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

			if (isMac)
				text.replace(/Shift/g,'Option').replace(/Ctrl/g,'Command');

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
			this._canvas = mk('canvas');
			this._imgdata = {};
			this._pickingAreas = {};
			this._zoomlevel = undefined;
			this._contrast = undefined;
			this._pickingSize = undefined;

			this.file = {
				extension: undefined,
				name: undefined,
			};
			this.setName(imageName);
			this._$pickAreaColorDisplay = $.mk('span').attr({'class':'pickcolor','data-info':'Change the color of the picking areas on this tab'});
			this._$el = $.mk('li').attr('class','tab').append(
				this._$pickAreaColorDisplay,
				$.mk('span').attr({'class':'filename','data-info':this.file.name+'.'+this.file.extension}).text(this.file.name),
				$.mk('span').attr('class','fileext').text(this.file.extension),
				$.mk('span').attr({'class':'close','data-info':'Close tab'}).text("\u00d7")
			);
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
			const imgel = new Image();
			$(imgel).attr('src', src).on('load',() => {
				this._imgdata.size = {
					width: imgel.width,
					height: imgel.height,
				};
				this._canvas.width = this._imgdata.size.width;
				this._canvas.height = this._imgdata.size.height;
				this._canvas.getContext('2d').drawImage(imgel,0,0);

				if (typeof this._pickingAreaColor === 'undefined'){
					const tmpc = mk('canvas');
					tmpc.width = 1;
					tmpc.height = 1;
					const tmpctx = tmpc.getContext('2d');
					tmpctx.drawImage(imgel,0,0,1,1);
					const px = new Pixel(...tmpctx.getImageData(0,0,1,1).data, 127).invert();
					this.setPickingAreaColor(new ColorFormatter(px));
				}

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
		getName(){
			return `${this.file.name}.${this.file.extension}`;
		}
		getImageSize(){
			return this._imgdata.size;
		}
		getImagePosition(){
			return this._imgdata.position;
		}
		setImagePosition(pos){
			this._imgdata.position = pos;
		}
		getZoomLevel(){
			return this._zoomlevel;
		}
		setZoomLevel(level){
			this._zoomlevel = level;
		}
		getContrast(){
			return this._contrast;
		}
		setContrast(level){
			this._contrast = level;
		}
		getPickingSize(){
			return this._pickingSize;
		}
		setPickingSize(size){
			this._pickingSize = size;
		}
		getElement(){
			return this._$el;
		}
		getCanvasCtx(){
			return this._canvas.getContext('2d');
		}
		placeArea(pos, size, square = true){
			this.addPickingArea(PickingArea.getArea(pos, size, square));
		}
		/** @param {PickingArea} area */
		addPickingArea(area){
			area.belongsToTab(this);
			this._pickingAreas[area.id] = area;
		}
		getPickingAreas(){
			return this._pickingAreas;
		}
		clearPickingAreas(bulk = false){
			this._pickingAreas = {};
			if (!this.isActive())
				return;

			if (!bulk)
				ColorPicker.getInstance().redrawPickingAreas();
		}
		removePickingArea(ix, bulk = false){
			if (typeof this._pickingAreas[ix] === 'undefined')
				throw new Error('Trying to remove non-existing area, guid: '+ix);

			delete this._pickingAreas[ix];
			if (!this.isActive())
				return;

			if (!bulk)
				ColorPicker.getInstance().redrawPickingAreas();
		}
		/** @return {ColorFormatter|string} */
		getPickingAreaColor(){
			return this._pickingAreaColor || 'rgba(255,0,255,.5)';
		}
		setPickingAreaColor(color){
			this._pickingAreaColor = new ColorFormatter(color);
			this._$pickAreaColorDisplay.html($.mk('span').css('background-color',this._pickingAreaColor.toString()));
			if (!this.isActive())
				return;

			ColorPicker.getInstance().redrawPickingAreas();
		}
		drawImage(){
			ColorPicker.getInstance().getImageCanvasCtx().drawImage(this._canvas, 0, 0, this._imgdata.size.width, this._imgdata.size.height, 0, 0, this._imgdata.size.width, this._imgdata.size.height);
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
			if (!tabsLeft)
				ColorPicker.getInstance().clearImage();

			this._tabStorage.splice(tabIndex,1);
			if (tabsLeft){
				this.activateTab(Math.min(tabCount-2,tabIndex));
			}
			this.updateTabs();
			ColorPicker.getInstance().updatePickingState();
		}
		closeActiveTabConfirm(){
			if (!(this._activeTab instanceof Tab))
				return;

			this._activeTab.getElement().find('.close').trigger('click');
		}
	}

	const $ContrastChangeForm = $.mk('form','contrast-changer').append(
		$.mk('label').append(
			`<span>Contrast value (<span id='disp'>&hellip;</span>%)</span>`,
			$.mk('input').attr({
				type: 'range',
				min: 0,
				max: 5,
				step: 0.01,
				name: 'contrast',
			}).on('change mousemove',$.throttle(50,function(){
				$(this).closest('form').trigger('update-disp');
			}))
		),
		`<div class="notice info">The contrast setting does not affect the returned average color values in any way.</div>
		<fieldset>
			<legend>Preview</legend>
			<div id="contrast-preview"><canvas></canvas></div>
		</fieldset>`,
		$.mk('button').attr('class','darkblue').text('Set to 100%').on('click',function(e){
			e.preventDefault();

			const $form = $(this).closest('form');

			$form.find('input').val(1);
			$form.trigger('submit');
		})
	).on('update-disp',function(){
		const
			$this = $(this),
			$disp = $this.find('#disp'),
			$preview = $this.find('#contrast-preview'),
			$input = $this.find('input[name="contrast"]'),
			val = $.roundTo(parseFloat($input.val()),2);
		$disp.text(Math.round(val*100));
		$preview.css(filterPrefix+'filter',`contrast(${val})`);
	});

	class ColorPicker {
		constructor(){
			this._mouseImagePos = {
				top: NaN,
				left: NaN,
			};
			this._zoomlevel = 1;
			this._contrast = 1;
			this._moveMode = false;

			this._$picker = $('#picker');
			this.updatePickerSize();
			this._$imageOverlay = $.mk('canvas').attr('class','image-overlay');
			this._$imageCanvas = $.mk('canvas').attr('class','image-element');
			this._$mouseOverlay = $.mk('canvas').attr('class','mouse-overlay');
			this._$handTool = $.mk('button').attr({'class':'fa fa-hand-paper-o','data-info':'Hand Tool (H) - Move around without having to hold Space'}).on('click',e => {
				e.preventDefault();

				this.switchTool('hand');
			});
			this._$pickerTool = $.mk('button').attr({'class':'fa fa-eyedropper','data-info':'Eyedropper Tool (I) - Click to place picking areas on the image (Hold Alt for rounded area)'}).on('click',e => {
				e.preventDefault();

				this.switchTool('picker');
			});
			this._$zoomTool = $.mk('button').attr({'class':'fa fa-search','data-info':'Zoom Tool (Z)',disabled:true}).on('click',e => {
				e.preventDefault();

				this.switchTool('zoom');
			});
			this.switchTool('hand');
			this._$contrastChanger = $.mk('button').attr({'class':'fa fa-adjust','data-info':'Change contrast'+(!filterSupport?' (not supported by your browser)':''),readonly:!filterSupport}).on('click',e => {
				e.preventDefault();

				const activeTab = Tabbar.getInstance().getActiveTab();

				if (!activeTab || !filterSupport)
					return;

				const imgsize = activeTab.getImageSize();

				$.Dialog.request('Chnage contrast',$ContrastChangeForm.clone(true,true),'Set',$form => {
					$form.find('input[name="contrast"]').val(this._contrast);
					$form.triggerHandler('update-disp');

					const
						$previewCanvas = $form.find('#contrast-preview').children(),
						fitsize = $.scaleResize(imgsize.width, imgsize.height, { height: 150 }, false);

					$previewCanvas[0].width = fitsize.width;
					$previewCanvas[0].height = fitsize.height;
					$previewCanvas[0].getContext('2d').drawImage(this._$imageCanvas[0], 0, 0, imgsize.width, imgsize.height, 0, 0, fitsize.width, fitsize.height);

					$form.on('submit',e => {
						e.preventDefault();

						const data = $form.mkData();
						$.Dialog.wait(false, 'Setting contrast');
						this.setContrast(data.contrast);
						$.Dialog.close();
					});
				});
			});
			this._$zoomin = $.mk('button').attr({'class':'zoom-in fa fa-search-plus','data-info':'Zoom in (Alt+Scroll Up)'}).on('click',(e, trigger) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel*Zoom.step, trigger);
				if (trigger)
					this.updateMousePosition(trigger);
				this.drawPickerCursor(!e.altKey);
			});
			this._$zoomout = $.mk('button').attr({'class':'zoom-out fa fa-search-minus','data-info':'Zoom out (Alt+Scroll Down)'}).on('click',(e, trigger) => {
				e.preventDefault();

				this.setZoomLevel(this._zoomlevel/Zoom.step, trigger);
				if (trigger)
					this.updateMousePosition(trigger);
				this.drawPickerCursor(!e.altKey);
			});
			this._$zoomfit = $.mk('button').attr({'class':'zoom-fit fa fa-window-maximize','data-info':'Fit in view (Ctrl+0)'}).on('click',e => {
				e.preventDefault();

				this.setZoomFit();
			});
			this._$zoomorig = $.mk('button').attr({'class':'zoom-orig fa fa-search','data-info':'Original size (Ctrl+1)'}).on('click',e => {
				e.preventDefault();

				this.setZoomOriginal();
			});
			this._$zoomperc = $.mk('span').attr({
				'class': 'zoom-perc',
				'data-info': 'Current zoom level (Click to enter a custom value between 0.4% and 3200%)',
				contenteditable: true,
				spellcheck: 'false',
				autocomplete: 'off',
			}).text('100%').on('keydown',e => {
				if (!$.isKey(Key.Enter, e))
					return;

				e.preventDefault();

				let perc = parseFloat(this._$zoomperc.text());
				if (!isNaN(perc))
					this.setZoomLevel(perc/100);

				$.clearFocus();
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
				$.mk('div').attr('class','picking-tools').append(
					"<span class='label'>Picking</span>",
					this._$handTool,
					this._$pickerTool,
					this._$zoomTool,
					this._$contrastChanger
				),
				/*$.mk('div').attr('class','debug-tools').append(
					"<span class='label'>Debugging</span>",

				),*/
				$.mk('div').attr('class','zoom-controls').append(
					"<span class='label'>Zooming</span>",
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
			this._$pickingSize = $.mk('span').attr({
				'class': 'picking-size',
				'data-info': 'Size of newly placed picking areas (Click to enter a custom value between 1px and 400px)',
				contenteditable: true,
				spellcheck: 'false',
				autocomplete: 'off',
			}).on('keydown',e => {
				if (!$.isKey(Key.Enter, e))
					return;

				e.preventDefault();

				const px = parseInt(this._$pickingSize.text().trim());
				this.setPickingSize(!isNaN(px) ? px : undefined);
				$.clearFocus();
			}).on('mousedown',() => {
				this._$pickingSize.data('mousedown', true);
			}).on('mouseup',() => {
				this._$pickingSize.data('mousedown', false);
			}).on('click',() => {
				if (this._$pickingSize.data('focused') !== true){
					this._$pickingSize.data('focused', true);
					this._$pickingSize.select();
				}
			}).on('dblclick',e => {
				e.preventDefault();
				this._$pickingSize.select();
			}).on('blur', () => {
				if (!this._$pickingSize.data('mousedown'))
					this._$pickingSize.data('focused', false);
				if (this._$pickingSize.text().trim().length === 0)
					this.setPickingSize();
				$.clearSelection();
			});
			this._pickingSizeDecreaseInterval = undefined;
			this._pickingSizeIncreaseInterval = undefined;
			this._$decreasePickingSize = $.mk('button').attr({'class':'fa fa-minus-circle','data-info':'Decrease picking area size (Down Arrow)'}).on('mousedown',e => {
				e.preventDefault();

				if (typeof this._pickingSizeIncreaseInterval !== 'undefined'){
					clearInterval(this._pickingSizeIncreaseInterval);
					this._pickingSizeIncreaseInterval = undefined;
				}
				const square = !e.altKey;
				this.decreasePickingSize(square, false);
				this._pickingSizeDecreaseInterval = setInterval(() => {
					this.decreasePickingSize(square, false);
				},150);
			}).on('mouseup mouseleave',() => {
				if (typeof this._pickingSizeDecreaseInterval === 'undefined')
					return;

				clearInterval(this._pickingSizeDecreaseInterval);
				this._pickingSizeDecreaseInterval = undefined;
			});
			this._$increasePickingSize = $.mk('button').attr({'class':'fa fa-plus-circle','data-info':'Increase picking area size (Up Arrow)'}).on('mousedown',e => {
				e.preventDefault();

				if (typeof this._pickingSizeDecreaseInterval !== 'undefined'){
					clearInterval(this._pickingSizeDecreaseInterval);
					this._pickingSizeDecreaseInterval = undefined;
				}
				const square = !e.altKey;
				this.increasePickingSize(square, false);
				this._pickingSizeIncreaseInterval = setInterval(() => {
					this.increasePickingSize(square, false);
				},150);
			}).on('mouseup mouseleave',() => {
				if (typeof this._pickingSizeIncreaseInterval === 'undefined')
					return;

				clearInterval(this._pickingSizeIncreaseInterval);
				this._pickingSizeIncreaseInterval = undefined;
			});
			this.setPickingSize(PersistentSettings.getInstance().get('pickingAreaSize'), false);
			this._$areaCounter = $.mk('span');
			this._$areaImageCounter = $.mk('span');
			this._$averageColor = $.mk('span').attr('class','average text');
			this._$copyColorBtn = $.mk('button').attr({'class':'fa fa-clipboard','data-info':'Copy average color to clipboard'}).on('click',e => {
				const color = this._$averageColor.children().eq(0).text();

				$.copy(color, e);
			});
			this._$actionsBottomLeft = $.mk('div').attr('class','actions actions-bl').append(
				$.mk('div').append(
					'<span class="label">Picking tool settings</span>',
					$.mk('div').attr('class','picking-controls text').append(
						this._$decreasePickingSize,
						this._$pickingSize,
						this._$increasePickingSize
					)
				),
				$.mk('div').append(
					'<span class="label">Picking status</span>',
					$.mk('span').attr('class','counters text').append(
						this._$areaCounter,
						' & ',
						this._$areaImageCounter
					),
					this._$averageColor
				)
			).on('mousedown',e => {
				e.stopPropagation();
				this._$zoomperc.triggerHandler('blur');
			});
			this._$areasList = $.mk('ul');
			let areaListResizing = false;
			this._$listResizeHandle = $.mk('div').attr('class','resize-handle').on('mousedown',() => {
				areaListResizing = true;
				$body.addClass('area-list-resizing');
			});
			this._$areasDeleteSelected = $.mk('button').attr({'class':'fa fa-trash','data-info':'Delete selected areas (Del)'}).on('click',e => {
				e.preventDefault();

				this.deleteSelectedAreas();
			});
			this._$displayFormatSwitch = $.mk('button').attr('class','fa');
			this._$selectAllAreas = $.mk('button').attr({'class':'fa fa-check-circle','data-info':'Selects all areas (Ctrl+A). Shift+Click to deselect (Ctrl+Shift+A).'}).on('click',e => {
				e.preventDefault();

				this.selectAllAreas(e.shiftKey);
			});
			this.setSidebarDisplayFormat(PersistentSettings.getInstance().get('sidebarColorFormat'), false);
			this._$areasListButtons = $.mk('div').attr('class','action-buttons').append(
				this._$areasDeleteSelected,
				this._$displayFormatSwitch,
				this._$selectAllAreas
			);
			this._$areasSidebar = $('#areas-sidebar').append(
				this._$listResizeHandle,
				this._$areasListButtons,
				this._$areasList
			).on('mousewheel',e => {
				e.stopPropagation();
			}).on('click','.select-handle',e => {
				e.preventDefault();

				const $li = $(e.target).closest('li');
				if (e.ctrlKey && !e.altKey){
					const $parentLi = $li.closest('ul').parent();
					if ($parentLi.hasClass('selected'))
						return;

					$li.toggleClass('selected').find('.selected').removeClass('selected');
				}
				else if (e.shiftKey && !e.altKey && !e.ctrlKey){
					const
						$lastClicked = this._$areasSidebar.find('.lastclicked'),
						lClen = $lastClicked.length;

					if (lClen > 0){
						const isTabItem = $li.children('ul').length > 0;
						let startIndex, endIndex, $sameLevelEntries;
						if (!isTabItem){
							startIndex = $lastClicked.index();
							endIndex = $li.index();
							$sameLevelEntries = $li.parent().find('.entry');

							if (!$li.parent().is($lastClicked.parent()))
								startIndex = Math.min(endIndex,$li.siblings().first().index());

						}
						else {
							const isLastClickedTabItem = $lastClicked.children('ul').length > 0;
							if (isLastClickedTabItem){
								startIndex = $lastClicked.index();
								endIndex = $li.index();
								$sameLevelEntries = $li.parent().children();
							}
							else {
								$li.addClass('selected');
							}
						}

						this._$areasSidebar.find('li.selected').removeClass('selected');

						if (typeof startIndex !== 'undefined'){
							// Swap start & end
							if (startIndex > endIndex){
								const tmp = startIndex;
								startIndex = endIndex;
								endIndex = tmp;
							}

							for (let i = startIndex; i <= endIndex || i < lClen; i++){
								$sameLevelEntries.eq(i).addClass('selected');
							}
						}
					}
				}
				else {
					this._$areasSidebar.find('li.selected').removeClass('selected');
					$li.addClass('selected');
				}
				this._$areasSidebar.find('li.lastclicked').removeClass('lastclicked');
				$li.addClass('lastclicked');
			}).on('dblclick','.entry',e => {
				e.preventDefault();

				// TODO Implement
				console.log(e.target);
			});
			this.setPickerWidth(PersistentSettings.getInstance().get('pickerWidth'));
			this.updatePickingState();
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
				if (areaListResizing){
					const listSize = Math.max(e.pageX-2,0)/$body.width();
					this.setPickerWidth($.roundTo(listSize*100,2));
					return;
				}

				if (!Tabbar.getInstance().getActiveTab() || $.Dialog.isOpen())
					return;

				// Mouse position indicator
				this.updateMousePosition(e);

				// Canvas movement if these are defined
				if (initial && initialmouse){
					let mouse = {
							top: e.pageY,
							left: e.pageX,
						},
						pickeroffset = this.getPickerPosition(),
						top = (initial.top+(mouse.top-initialmouse.top))-pickeroffset.top,
						left = (initial.left+(mouse.left-initialmouse.left))-pickeroffset.left;
					this.move({ top, left });

					this.updateZoomLevelInputs();
				}
			}));
			this._$mouseOverlay.on('mousemove',e => {
				this.updateMousePosition(e);
				this.drawPickerCursor(!e.altKey);
			}).on('mousedown',e => {
				if (!Tabbar.getInstance().getActiveTab() || $.Dialog.isOpen())
					return;

				e.preventDefault();

				if (this._activeTool !== Tools.picker)
					return;

				this.placeArea(this._mouseImagePos, this._pickingAreaSize, !e.altKey);
			}).on('mouseleave',() => {
				this.clearMouseOverlay();
			});
			$w.on('mousewheel',e => {
				if (!e.altKey)
					return;

				e.preventDefault();

				if (e.originalEvent.deltaY > 0)
					this._$zoomout.trigger('click', [e]);
				else this._$zoomin.trigger('click', [e]);
			});
			this._$picker.on('mousewheel',e => {
				if (e.altKey)
					return;

				e.preventDefault();

				const step = this._pickerHeight*(e.shiftKey?0.1:0.025)*Math.sign(e.originalEvent.wheelDelta);
				if (e.ctrlKey)
					this.move({ left: `+=${step}px` });
				else this.move({ top: `+=${step}px` });
				this.updateMousePosition(e);
			});

			$body.on('mousedown',e => {
				if (!Tabbar.getInstance().getActiveTab() || !$(e.target).is(this._$imageOverlay) || !this._$imageOverlay.hasClass('draggable'))
					return;

				e.preventDefault();
				this._$imageOverlay.addClass('dragging');
				initial = this.getImagePosition();
				initialmouse = {
					top: e.pageY,
					left: e.pageX,
				};
			});
			$body.on('mouseup mouseleave blur',e => {
				if (areaListResizing && e.type === 'mouseup'){
					areaListResizing = false;
					this.storeSidebarWidth();
					$body.removeClass('area-list-resizing');
				}
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
		getZoomedTopLeft(imgoffset, scalefactor, center = this.getPickerCenterPosition()){
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
			const pickeroffset = this.getPickerPosition();
			imgoffset.left -= pickeroffset.left;
			imgoffset.right -= pickeroffset.right;
			return imgoffset;
		}
		getPickerCenterPosition(){
			return {
				top: this._pickerHeight/2,
				left: this._pickerWidth/2,
			};
		}
		getPickerPosition(){
			let pickerOffset = this._$picker.offset();
			pickerOffset.top -= (this._pickerHeight - this._$picker.outerHeight())/2;
			pickerOffset.left -= (this._pickerWidth - this._$picker.outerWidth())/2;
			return pickerOffset;
		}
		setPickingSize(size = undefined, store = true){
			if (!isNaN(size))
				this._pickingAreaSize = $.rangeLimit(size,false,1,400);
			this._$pickingSize.text(this._pickingAreaSize+'px');
			this._$decreasePickingSize.attr('disabled', this._pickingAreaSize === 1);
			this._$increasePickingSize.attr('disabled', this._pickingAreaSize === 400);

			if (store)
				PersistentSettings.getInstance().set('pickingAreaSize',this._pickingAreaSize);

			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			activeTab.setPickingSize(this._pickingAreaSize);
		}
		decreasePickingSize(square, drawCursor = true){
			this.setPickingSize(this._pickingAreaSize-5);
			if (drawCursor)
				this.drawPickerCursor(square);
		}
		increasePickingSize(square, drawCursor = true){
			this.setPickingSize(this._pickingAreaSize === 1 ? 5 : this._pickingAreaSize+5);
			if (drawCursor)
				this.drawPickerCursor(square);
		}
		drawPickerCursor(square){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab || $.Dialog.isOpen() || this._activeTool !== Tools.picker)
				return;

			const area = PickingArea.getArea(this._mouseImagePos, this._pickingAreaSize, square);
			this.clearMouseOverlay();
			const ctx = this.getMouseOverlayCtx();
			ctx.fillStyle = activeTab.getPickingAreaColor().toString();
			PickingArea.draw(area, ctx);
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
			$.each(activeTab.getPickingAreas(),(_,area) => {
				PickingArea.draw(area, ctx);
			});
			this.updatePickingState();
		}
		updatePickingState(){
			let areaCount = 0,
				imgCount = 0,
				pixels = [];

			this._$areasList.empty();
			$.each(Tabbar.getInstance().getTabs(),(_,tab) => {
				const
					areas = tab.getPickingAreas(),
					areaGuids = Object.keys(areas),
					$areaList = $.mk('ul'),
					$tabLi = $.mk('li').append(
						$.mk('span').attr('class','entry').append(
							$.mk('span').attr('class','name').text(tab.getName()),
							$.mk('span').attr('class','select-handle')
						),
						$areaList
					);
				this._$areasList.append($tabLi);

				if (!areaGuids.length)
					return;

				imgCount++;
				areaCount += areaGuids.length;

				let ix = 0;
				$.each(areas,(_,area) => {
					const
						avgc = area.getAverageColor(),
						hexOut = this._sidebarDisplayFormat === 'hex',
						avgchex = new ColorFormatter(avgc).toHexString();
					let avgcbg, avgcsout;
					if (hexOut){
						avgcbg = new ColorFormatter(avgc).toString();
						avgcsout = avgchex+(avgc.alpha !== 255 ? ` @ ${$.roundTo(avgc.alpha/255,2)}%`:'');
					}
					else {
						avgcbg = new ColorFormatter(avgc).toRGBString();
						avgcsout = avgcbg.replace(/^rgba?\((.+)\)$/,'$1').split(',');
						if (avgcsout.length === 4){
							const opacity = $.roundTo(parseFloat(avgcsout.pop())*100, 2);
							avgcsout = avgcsout.join(', ')+` @ ${opacity}%`;
						}
						else avgcsout = avgcsout.join(', ');
					}
					$areaList.append(
						$.mk('li','picking-area-'+area.id).attr('class','entry').append(
							$.mk('span').attr('class','index').text(++ix),
							$.mk('span').attr('class','color').css({
								backgroundColor: avgcbg,
								color: $.yiq(avgchex) > 127 ? 'black' : 'white',
							}).html(avgcsout),
							$.mk('span').attr('class','size '+(area instanceof RoundedPickingArea?'rounded':'square')).html($.mk('span').text(area.boundingRect.sideLength)),
							$.mk('span').attr('class','select-handle')
						)
					);
					pixels.push(avgc);
				});
			});

			this._$areaCounter.text(areaCount+' area'+(areaCount!==1?'s':''));
			this._$areaImageCounter.text(imgCount+' image'+(imgCount!==1?'s':''));

			this._$averageColor.empty();
			if (pixels.length){
				const
					averageColor = new ColorFormatter(PickingArea.averageColor(pixels)),
					averageHex = averageColor.toString(true);
				this._$averageColor.append(
					$.mk('span').attr('class','color').css({
						backgroundColor:averageColor.toString(),
						color: $.yiq(averageHex) > 127 ? 'black' : 'white',
					}).text(averageHex),
					this._$copyColorBtn.clone(true,true)
				);
			}
		}
		selectAllAreas(unselect = false){
			this._$areasList.children()[unselect?'removeClass':'addClass']('selected');
		}
		deleteSelectedAreas(){
			const $selected = this._$areasList.find('.selected');
			if (!$selected.length)
				return;

			const
				guids = {},
				tabs = Tabbar.getInstance().getTabs();
			$selected.each((_,el) => {
				const
					$this = $(el),
					isEntireTab = $this.children('ul').length > 0;

				if (isEntireTab){
					const tabIndex = $this.index();
					tabs[tabIndex].clearPickingAreas(true);
				}
				else {
					const tabIndex = $this.parents('li').index();
					console.log($this,tabIndex);
					if (typeof guids[tabIndex] === 'undefined')
						guids[tabIndex] = [];
					guids[tabIndex].push($this.attr('id').replace(/^picking-area-/,''));
				}
			});

			$.each(guids,(tabIndex,guids) => {
				$.each(guids,(_,guid) =>{
					tabs[tabIndex].removePickingArea(guid, true);
				});
			});

			ColorPicker.getInstance().redrawPickingAreas();
		}
		setSidebarDisplayFormat(format, store = true){
			if (!/^(hex|rgb)$/.test(format))
				throw new Error('Invalid sidebar displa format: '+format);
			const isHex = format === 'hex';
			this._$displayFormatSwitch.attr({
				'class':'fa fa-'+(isHex?'tint':'hashtag'),
				'data-info':'Switch to displaying colors in the sidebar in '+(isHex?'RGB':'HEX')+' format',
			}).off('click').on('click',e => {
				e.preventDefault();

				this.setSidebarDisplayFormat(isHex?'rgb':'hex');

				const $this = $(e.target);
				if ($this.is(':hover'))
					Statusbar.getInstance().setInfo($this.attr('data-info'));
			});

			this._sidebarDisplayFormat = format;
			this.updatePickingState();

			if (store)
				PersistentSettings.getInstance().set('sidebarColorFormat',format);
		}
		clearImageOverlay(){
			clearCanvas(this.getImageOverlayCtx());
		}
		clearMouseOverlay(){
			clearCanvas(this.getMouseOverlayCtx());
		}
		updateZoomLevelInputs(){
			this._$zoomperc.text($.roundTo(this._zoomlevel*100,2)+'%');
			document.activeElement.blur();

			this._$zoomout.attr('disabled', this._zoomlevel <= Zoom.min);
			this._$zoomin.attr('disabled', this._zoomlevel >= Zoom.max);
		}
		updateMousePosition(e){
			const imgpos = this.getImagePosition();
			this._mouseImagePos.top  = Math.floor((e.pageY - imgpos.top ) / this._zoomlevel);
			this._mouseImagePos.left = Math.floor((e.pageX - imgpos.left) / this._zoomlevel);
			Statusbar.getInstance().setPosition('mouse', this._mouseImagePos);

			const activeTab = Tabbar.getInstance().getActiveTab();
			if (activeTab instanceof Tab){
				const imgsize = activeTab.getImageSize();

				const isOffImage = (
					this._mouseImagePos.top < 0 ||
					this._mouseImagePos.top > imgsize.height-1 ||
					this._mouseImagePos.left < 0 ||
					this._mouseImagePos.left > imgsize.width-1
				);
				if (isOffImage)
					Statusbar.getInstance().setColorAt();
				else {
					const p = this.getImageCanvasCtx().getImageData(this._mouseImagePos.left, this._mouseImagePos.top, 1, 1).data;
					Statusbar.getInstance().setColorAt($.rgb2hex({r:p[0], g:p[1], b:p[2]}), $.roundTo((p[3]/255)*100, 2)+'%');
				}
			}
		}
		setContrast(level){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			if (this._contrast === level)
				return;

			this._contrast = level;
			this._$imageCanvas.css(filterPrefix+'filter',this._contrast === 1 ? '' : `contrast(${this._contrast})`);
			activeTab.setContrast(this._contrast);
		}
		setZoomLevel(perc, center){
			const activeTab = Tabbar.getInstance().getActiveTab();
			if (!activeTab)
				return;

			const size = activeTab.getImageSize();
			let newzoomlevel = $.rangeLimit(perc, false, Zoom.min, Zoom.max),
				newsize,
				oldzoomlevel;
			if (this._zoomlevel !== newzoomlevel){
				newsize = $.scaleResize(size.width, size.height, {scale: newzoomlevel});
				oldzoomlevel = this._zoomlevel;
				this._zoomlevel = newsize.scale;

				const
					pickeroffset = this.getPickerPosition(),
					zoomed = this.getZoomedTopLeft(this.getImagePosition(), newzoomlevel/oldzoomlevel, center ? { top: center.pageY, left: center.pageX } : undefined);

				this.move({
					top: zoomed.top-pickeroffset.top,
					left: zoomed.left-pickeroffset.left,
					width: newsize.width,
					height: newsize.height,
				});
			}

			activeTab.setZoomLevel(this._zoomlevel);
			this.updateZoomLevelInputs();
		}
		setZoomFit(){
			this._fitImageHandler(size => {
				const
					pickerwide = this._pickerWidth > this._pickerHeight,
					square = size.width === size.height,
					wide = square ? pickerwide : size.width > size.height;
				let ret = $.scaleResize(size.width, size.height, wide ? {height:this._pickerHeight} : {width:this._pickerWidth});
				if (pickerwide){
					if (ret.width > this._pickerWidth){
						ret = $.scaleResize(size.width, size.height, {width:this._pickerWidth});
					}
					else if (ret.height > this._pickerHeight){
						ret = $.scaleResize(size.width, size.height, {height:this._pickerHeight});
					}
				}
				if (!pickerwide){
					if (ret.height > this._pickerHeight){
						ret = $.scaleResize(size.width, size.height, {height:this._pickerHeight});
					}
					else if (ret.width > this._pickerWidth){
						ret = $.scaleResize(size.width, size.height, {width:this._pickerWidth});
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
				top = (this._pickerHeight-newsize.height)/2,
				left = (this._pickerWidth-newsize.width)/2;
			this.move({
				top: top,
				left: left,
				width: newsize.width,
				height: newsize.height,
			});
			this._zoomlevel = newsize.scale;
			this.setZoomLevel(this._zoomlevel);
		}
		setPickerWidth(perc){
			const pickerwidth = $.rangeLimit(parseFloat(perc),false,50,85);
			this._$picker.width(pickerwidth+'%');
			this._$areasSidebar.outerWidth((100-pickerwidth)+'%');
			this.resizeHandler();
		}
		storeSidebarWidth(){
			PersistentSettings.getInstance().set('pickerWidth', $.roundTo((this._$picker.width()/$w.width())*100,2)+'%');
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
				}, activeTab);
		}
		updatePickerSize(){
			this._pickerWidth = this._$picker.innerWidth();
			this._pickerHeight = this._$picker.innerHeight();
		}
		resizeHandler(){
			this.updatePickerSize();

			if (typeof this._zoomlevel === 'number')
				this.setZoomLevel(this._zoomlevel);
		}
		_setCanvasSize(w,h){
			this._$mouseOverlay[0].width =
			this._$imageOverlay[0].width =
			this._$imageCanvas[0].width = w;

			this._$mouseOverlay[0].height =
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
			else {
				this.move(storedimgpos, true);
				const storedzoomlevel = tab.getZoomLevel();
				if (typeof storedzoomlevel !== 'undefined'){
					this._zoomlevel = storedzoomlevel;
					this.setZoomLevel(storedzoomlevel);
				}
			}
			if (filterSupport){
				this.setContrast(tab.getContrast() || this._contrast, true);
			}
			this.setPickingSize(tab.getPickingSize());

			this.updatePickerSize();
			this.redrawPickingAreas();
		}
		clearImage(){
			if (!Tabbar.getInstance().getActiveTab())
				return;

			this._$imageCanvas.detach();
			clearCanvas(this.getImageCanvasCtx());
			clearCanvas(this.getImageOverlayCtx());
			Statusbar.getInstance().setColorAt();
			Statusbar.getInstance().setPosition('mouse');
			this._zoomlevel = 1;
			this.updateZoomLevelInputs();
			$.Dialog.close();
		}
		moveMode(enable, force = false){
			const handToolActive = this._activeTool === Tools.hand;
			if (enable && !this._moveMode && (force || !handToolActive)){
				this._moveMode = true;
				this._$imageOverlay.addClass('draggable');
			}
			else if (!enable && this._moveMode && (force || !handToolActive)){
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
		switchTool(tool){
			if (this._activeTool === tool)
				return;

			// Cleanup after old tool
			switch (this._activeTool){
				case Tools.hand:
					this.moveMode(false, true);
				break;
				case Tools.picker:
					this._$mouseOverlay.removeClass('picking');
					this.clearMouseOverlay();
				break;
			}

			// Activate new tool
			switch (Tools[tool]){
				case Tools.hand:
					this.moveMode(true, true);
				break;
				case Tools.picker:
					this._$mouseOverlay.addClass('picking');
				break;
			}
			if (tool !== 'zoom')
				this._$zoomTool.removeClass('selected');
			if (tool !== 'picker')
				this._$pickerTool.removeClass('selected');
			if (tool !== 'hand')
				this._$handTool.removeClass('selected');
			this[`_$${tool}Tool`].addClass('selected');
			this._activeTool = Tools[tool];
		}
	}

	// Create instances
	Menubar.getInstance();
	Statusbar.getInstance();
	Tabbar.getInstance();
	ColorPicker.getInstance();

	$body.on('keydown',function(e){
		const tagname = e.target.tagName.toLowerCase();
		if ((tagname === 'input' && e.target.type !== 'file') || tagname === 'textarea' || e.target.getAttribute('contenteditable') !== null)
			return;

		switch (e.keyCode){
			case Key[0]:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomFit();
			break;
			case Key[1]:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().setZoomOriginal();
			break;
			case Key.Space:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().moveMode(true);
			break;
			case Key.H:
				ColorPicker.getInstance().switchTool(Tools.hand);
			break;
			case Key.I:
				ColorPicker.getInstance().switchTool(Tools.picker);
			break;
			case Key.O:
				if (!e.ctrlKey || e.altKey)
					return;

				Menubar.getInstance().requestFileOpen();
			break;
			case Key.Z:
				ColorPicker.getInstance().switchTool(Tools.zoom);
			break;
			case Key.UpArrrow:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().increasePickingSize(!e.altKey);
			break;
			case Key.DownArrrow:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().decreasePickingSize(!e.altKey);
			break;
			case Key.Delete:
				if (e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().deleteSelectedAreas();
			break;
			case Key.A:
				if (!e.ctrlKey || e.altKey)
					return;

				ColorPicker.getInstance().selectAllAreas(e.shiftKey);
			break;
			default:
				return;
		}

		e.preventDefault();
	});
	$body.on('keyup',function(e){
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
	$body.on('paste', '[contenteditable]', function(e){
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
