/* jshint bitwise: false */
(function(){
	'use strict';

	if (typeof $.Navigation !== 'undefined' && $.Navigation.firstLoadDone === true)
		return;

	// console placeholder to avoid errors
	if (typeof window.console.log !== 'function')
		window.console.log = function(){};
	if (typeof window.console.group !== 'function')
		window.console.group = function(){};
	if (typeof window.console.groupEnd !== 'function')
		window.console.groupEnd = function(){};
	if (typeof window.console.clear !== 'function')
		window.console.clear = function(){};

	// document.createElement shortcut
	window.mk = function(){ return document.createElement.apply(document,arguments) };

	// $(document.createElement) shortcut
	$.mk = (name, id) => {
		let $el = $(document.createElement.call(document,name));
		if (typeof id === 'string')
			$el.attr('id', id);
		return $el;
	};

	class EmulatedStorage {
		constructor(){
			this.emulatedStorage = {};
		}
		getItem(k){
			return typeof this.emulatedStorage[k] === 'undefined' ? null : this.emulatedStorage[k];
		}
		setItem(k, v){
			this.emulatedStorage[k] = typeof v === 'string' ? v : ''+v;
		}
		removeItem(k){
			delete this.emulatedStorage[k];
		}
	}

	// Storage wrapper with try...catch blocks for incompetent browsers
	class StorageWrapper {
		constructor(store){
			let storeName = store+'Storage';
			try {
				this.store = window[store+'Storage'];
			}catch(e){
				console.error(storeName+' is unavailable, falling back to EmulatedStorage');
				this.store = new EmulatedStorage();
			}
		}
		get(key){
			let val;
			try {
				val = this.store.getItem(key);
			} catch(e){ /* ignore */ }
			return typeof val === 'undefined' ? null : val;
		}
		set(key, value){
			try {
				this.store.setItem(key, value);
			} catch(e){ /* ignore */ }
		}
		remove(key){
			try {
				this.store.removeItem(key);
			} catch(e){ /* ignore */ }
		}
	}

	$.LocalStorage = new StorageWrapper('local');

	$.SessionStorage = new StorageWrapper('session');

	// Convert relative URL to absolute
	$.toAbsoluteURL = url => {
		let a = mk('a');
		a.href = url;
		return a.href;
	};

	// Globalize common elements
	window.$w = $(window);
	window.$d = $(document);
	window.defineCommonElements = function(){
		window.$header = $('header');
		window.$sbToggle = $('.sidebar-toggle');
		window.$main = $('#main');
		window.$content = $('#content');
		window.$sidebar = $('#sidebar');
		window.$footer = $('footer');
		window.$body = $('body');
		window.$head = $('head');
		window.$navbar = $header.find('nav');
	};
	window.defineCommonElements();

	// Common key codes for easy reference
	window.Key = {
		Backspace: 8,
		Tab: 9,
		Enter: 13,
		Alt: 18,
		Escape: 27,
		Space: 32,
		LeftArrow: 37,
		UpArrow: 38,
		RightArrow: 39,
		DownArrow: 40,
		Delete: 46,
		0: 48,
		1: 49,
		A: 65,
		H: 72,
		I: 73,
		O: 79,
		Z: 90,
		Comma: 188,
	};
	$.isKey = function(Key, e){
		return e.keyCode === Key;
	};

	// Time class
	(function($){
		let dateFormat = { order: 'Do MMMM YYYY, H:mm:ss' };
		dateFormat.orderwd = `dddd, ${dateFormat.order}`;

		class DateFormatError extends Error {
			constructor(message, element){
				super(message);

				this.name = 'DateFormatError';
				this.element = element;
			}
		}

		class Time {
			static update(){
				// TODO Rework dyntime
				$('time[datetime]:not(.nodt)').addClass('dynt').each(function(){
					let $this = $(this),
						date = $this.attr('datetime');
					if (typeof date !== 'string') throw new TypeError('Invalid date data type: "'+(typeof date)+'"');

					let Timestamp = moment(date);
					if (!Timestamp.isValid())
						throw new DateFormatError('Invalid date format: "'+date+'"', this);

					let now = moment(),
						showDayOfWeek = !$this.attr('data-noweekday'),
						timeAgoStr = Timestamp.from(now),
						$elapsedHolder = $this.parent().children('.dynt-el'),
						updateHandler = $this.data('dyntime-beforeupdate');

					if (typeof updateHandler === 'function'){
						let result = updateHandler(Time.difference(now.toDate(), Timestamp.toDate()));
						if (result === false) return;
					}

					if ($elapsedHolder.length > 0 || $this.hasClass('no-dynt-el')){
						$this.html(Timestamp.format(showDayOfWeek ? dateFormat.orderwd : dateFormat.order));
						$elapsedHolder.html(timeAgoStr);
					}
					else $this.attr('title', Timestamp.format(dateFormat.order)).html(timeAgoStr);
				});
			}

			static difference(now, timestamp) {
				let subtract = (now.getTime() - timestamp.getTime())/1000,
					d = {
						past: subtract > 0,
						time: Math.abs(subtract),
						target: timestamp,
						week: 0,
						month: 0,
						year: 0,
					},
					time = d.time;

				d.day = Math.floor(time/this.InSeconds.day);
				time -= d.day * this.InSeconds.day;

				d.hour = Math.floor(time/this.InSeconds.hour);
				time -= d.hour * this.InSeconds.hour;

				d.minute = Math.floor(time/this.InSeconds.minute);
				time -= d.minute * this.InSeconds.minute;

				d.second = Math.floor(time);

				if (d.day >= 7){
					d.week = Math.floor(d.day/7);
					d.day -= d.week*7;
				}
				if (d.week >= 4){
					d.month = Math.floor(d.week/4);
					d.week -= d.month*4;
				}
				if (d.month >= 12){
					d.year = Math.floor(d.month/12);
					d.month -= d.year*12;
				}

				return d;
			}
		}
		Time.InSeconds = {
			'year':   31557600,
			'month':  2592000,
			'week':   604800,
			'day':    86400,
			'hour':   3600,
			'minute': 60,
		};
		window.Time = Time;

		Time.update();
		setInterval(Time.update, 10e3);
	})(jQuery);

	// Make the first letter of the first or all word(s) uppercase
	$.capitalize = (str, all) => {
		if (all) return str.replace(/((?:^|\s)[a-z])/g, match =>  match.toUpperCase());
		else return str.length === 1 ? str.toUpperCase() : str[0].toUpperCase()+str.substring(1);
	};

	// Array.includes (ES7) polyfill
	if (typeof Array.prototype.includes !== 'function')
		Array.prototype.includes = function(elem){ return this.indexOf(elem) !== -1 };
	if (typeof String.prototype.includes !== 'function')
		String.prototype.includes = function(elem){ return this.indexOf(elem) !== -1 };

	$.pad = function(str, char, len, dir){
		if (typeof str !== 'string')
			str = ''+str;

		if (typeof char !== 'string')
			char = '0';
		if (typeof len !== 'number' && !isFinite(len) && isNaN(len))
			len = 2;
		else len = parseInt(len, 10);
		if (typeof dir !== 'boolean')
			dir = true;

		if (len <= str.length)
			return str;
		const padstr = new Array(len-str.length+1).join(char);
		str = dir === $.pad.left ? padstr+str : str+padstr;

		return str;
	};
	$.pad.right = !($.pad.left = true);

	$.scaleResize = function(origWidth, origHeight, param, allowUpscale = true){
		let div, dest = {
			scale: param.scale,
			width: param.width,
			height: param.height
		};
		// We have a scale factor
		if (!isNaN(dest.scale)){
			if (allowUpscale || dest.scale <= 1){
				dest.height = Math.round(origHeight * dest.scale);
				dest.width = Math.round(origWidth * dest.scale);
			}
		}
		else if (!isNaN(dest.width)){
			if (!allowUpscale)
				dest.width = Math.min(dest.width, origWidth);
			div = dest.width / origWidth;
			if (!allowUpscale && div > 1)
				div = 1;
			dest.height = Math.round(origHeight * div);
			dest.scale = div;
		}
		else if (!isNaN(dest.height)){
			if (!allowUpscale)
				dest.height = Math.min(dest.height, origHeight);
			div = dest.height / origHeight;
			if (!allowUpscale && div > 1){
				div = 1;
			}
			dest.width = Math.round(origWidth * div);
			dest.scale = div;
		}
		else throw new Error('[scalaresize] Invalid arguments');
		return dest;
	};

	// http://stackoverflow.com/a/3169849/1344955
	$.clearSelection = function(){
		if (window.getSelection){
			let sel = window.getSelection();
			if (sel.empty) // Chrome
				sel.empty();
			else if (sel.removeAllRanges) // Firefox
				sel.removeAllRanges();
		}
		else if (document.selection)  // IE?
			document.selection.empty();
	};

	$.toArray = (args, n = 0) =>  [].slice.call(args, n);

	$.clearFocus = () => {
		if (document.activeElement !== $body[0])
			document.activeElement.blur();
	};

	// Create AJAX response handling function
	$w.on('ajaxerror',function(){
		let details = '';
		if (arguments.length > 1){
			let data = $.toArray(arguments, 1);
			if (data[1] === 'abort')
				return;
			details = ' Details:<pre><code>' + data.slice(1).join('\n').replace(/</g,'&lt;') + '</code></pre>Response body:';
			let xdebug = /^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;
			if (xdebug.test(data[0].responseText))
				details += `<div class="reset">${data[0].responseText.replace(xdebug, '$1')}</div>`;
			else if (typeof data[0].responseText === 'string')
				details += `<pre><code>${data[0].responseText.replace(/</g,'&lt;')}</code></pre>`;
		}
		$.Dialog.fail(false,`There was an error while processing your request.${details}`);
	});
	$.mkAjaxHandler = function(f){
		return function(data){
			if (typeof data !== 'object'){
				//noinspection SSBasedInspection
				console.log(data);
				$w.triggerHandler('ajaxerror');
				return;
			}

			if (typeof f === 'function') f.call(data, data);
		};
	};

	// Checks if a variable is a function and if yes, runs it
	// If no, returns default value (undefined or value of def)
	$.callCallback = (func, params, def) => {
		if (typeof params !== 'object' || !$.isArray(params)){
			def = params;
			params = [];
		}
		if (typeof func !== 'function')
			return def;

		return func(...params);
	};

	// Convert .serializeArray() result to object
	$.fn.mkData = function(obj){
		let tempData = this.find(':input:valid').serializeArray(), data = {};
		$.each(tempData,function(i,el){
			if (/\[]$/.test(el.name)){
				if (typeof data[el.name] === 'undefined')
					data[el.name] = [];
				data[el.name].push(el.value);
			}
			else data[el.name] = el.value;
		});
		if (typeof obj === 'object')
			$.extend(data, obj);
		return data;
	};

	// Get CSRF token from cookies
	$.getCSRFToken = function(){
		let n = document.cookie.match(/CSRF_TOKEN=([a-f\d]+)/i);
		if (n && n.length)
			return n[1];
		else {
			$.Dialog.fail(false, `<p>A request could not be sent due to a missing CSRF_TOKEN, please <a class="send-feedback">let us know</a>. Additional information:</p><pre><code>${document.cookie || '&lt;empty&gt;'}</code></pre>`);
			throw new Error('Missing CSRF_TOKEN');
		}
	};
	$.ajaxPrefilter(function(event, origEvent){
		if ((origEvent.type||event.type).toUpperCase() === 'GET')
			return;

		let t = $.getCSRFToken();
		if (typeof event.data === "undefined")
			event.data = "";
		if (typeof event.data === "string"){
			let r = event.data.length > 0 ? event.data.split("&") : [];
			r.push(`CSRF_TOKEN=${t}`);
			event.data = r.join("&");
		}
		else event.data = { ...event.data, CSRF_TOKEN: t };
	});
	const simpleStatusHandler = xhr => {
		let resp;
		if (xhr.responseJSON)
			resp = xhr.responseJSON.message;
		else {
			try {
				resp = JSON.parse(xhr.responseText).message;
			} catch(e){ /* ignore */ }
		}
		$.Dialog.fail(false, resp);
	};
	const statusCodeHandlers = {
		400: simpleStatusHandler,
		401: function(){
			$.Dialog.fail(undefined, "Cross-site Request Forgery attack detected. Please <a class='send-feedback'>let us know</a> about this issue so we can look into it.");
		},
		403: simpleStatusHandler,
		404: simpleStatusHandler,
		405: simpleStatusHandler,
		500: function(){
			$.Dialog.fail(false, 'A request failed due to an internal server error. If this persists, please <a class="send-feedback">let us know</a>!');
		},
		503: function(){
			$.Dialog.fail(false, `A request failed because the server is temporarily unavailable. This shouldn't take too long, please try again in a few seconds.<br>If the problem still persist after a few minutes, please let us know by clicking the "Send feedback" link in the footer.`);
		},
		504: function(){
			$.Dialog.fail(false, `A request failed because the server took too long to respond. A refresh should fix this issue, but if it doesn't, please <a class="send-feedback">let us know</a>.`);
		},
	};
	$.ajaxSetup({
		dataType: "json",
		error: function(xhr){
			if (typeof statusCodeHandlers[xhr.status] !== 'function')
				$w.triggerHandler('ajaxerror',$.toArray(arguments));
		},
		statusCode: statusCodeHandlers,
	});

	// Copy any text to clipboard
	// Must be called from within an event handler
	let $notif;
	const copyDone = (success, e) => {
		if (typeof $notif === 'undefined' || e){
			if (typeof $notif === 'undefined')
				$notif = $.mk('span')
					.attr({
						id: 'copy-notify',
						'class': ! success ? 'fail' : undefined,
					})
					.html(`<span class="typcn typcn-clipboard"></span> <span class="typcn typcn-${success?'tick':'cancel'}"></span>`)
					.appendTo($body);
			if (e){
				let w = $notif.outerWidth(),
					h = $notif.outerHeight(),
					top = e.clientY - (h/2);
				return $notif.stop().css({
					top: top,
					left: (e.clientX - (w/2)),
					bottom: 'initial',
					right: 'initial',
					opacity: 1,
				}).animate({top: top-20, opacity: 0}, 1000, function(){
					$(this).remove();
					$notif = undefined;
				});
			}
			$notif.fadeTo('fast',1);
		}
		else $notif.stop().css('opacity',1);
		$notif.delay(success ? 300 : 1000).fadeTo('fast',0,function(){
			$(this).remove();
			$notif = undefined;
		});
	};
	$.copy = (text, e) => {
		if (typeof navigator.clipboard !== 'undefined'){
			navigator.clipboard.writeText(text)
				.then(() => { copyDone(true, e) })
				.catch(() => { copyDone(false, e) });
			return;
		}

		if (!document.queryCommandSupported('copy')){
			prompt('Copy with Ctrl+C, close with Enter', text);
			return true;
		}

		let $helper = $.mk('textarea'),
			success = false;
		$helper
			.css({
				opacity: 0,
				width: 0,
				height: 0,
				position: 'fixed',
				left: '-10px',
				top: '50%',
				display: 'block',
			})
			.text(text)
			.appendTo('body')
			.focus();
		$helper.get(0).select();

		try {
			success = document.execCommand('copy');
		} catch(e){ /* ignore */ }

		setTimeout(function(){
			$helper.remove();
			copyDone(success, e);
		}, 1);
	};

	$.compareFaL =  (a,b) => JSON.stringify(a) === JSON.stringify(b);

	// Convert RGB to HEX
	$.rgb2hex = color => $.RGBAColor.fromRGB(color).toHex();

	// :valid pseudo polyfill
	if (typeof $.expr[':'].valid !== 'function')
		$.expr[':'].valid = el => typeof el.validity === 'object' ? el.validity.valid : ((el) => {
			let $el = $(el),
				pattern = $el.attr('pattern'),
				required = $el.hasAttr('required'),
				val = $el.val();
			if (required && (typeof val !== 'string' || !val.length))
				return false;
			if (pattern)
				return (new RegExp(pattern)).test(val);
			else return true;
		})(el);

	$.roundTo = (number, precision = 0) => {
		if (precision === 0)
			console.warn('$.roundTo called with precision 0; you might as well use Math.round');
		let pow = Math.pow(10, precision);
		return Math.round(number*pow)/pow;
	};

	$.average = arr => arr.reduce( ( p, c ) => p + c, 0 ) / arr.length;

	$.clamp = (input, min, max) => Math.min(max, Math.max(min, input));
	$.clampCycle = function(input, min, max){
		if (input > max)
			return min;
		else if (input < min)
			return max;
		return input;
	};

	$.fn.select = function(){
		let range = document.createRange();
		range.selectNodeContents(this.get(0));
		let sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	};

	$.momentToYMD = momentInstance => momentInstance.format('YYYY-MM-DD');
	$.momentToHM = momentInstance => momentInstance.format('HH:mm');
	$.mkMoment = (dateString, timeString, utc) => moment(dateString+'T'+timeString+(utc?'Z':''));

	$.nth = n => {
		switch (n % 10){
			case 1: return n+(/11$/.test(n+'') ? 'th' : 'st');
			case 2: return n+(/12$/.test(n+'') ? 'th' : 'nd');
			case 3: return n+(/13$/.test(n+'') ? 'th' : 'rd');
			default: return n+'th';
		}
	};

	$.escapeRegex = pattern => pattern.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');

	$.fn.toggleHtml = function(contentArray){
		return this.html(contentArray[$.clampCycle(contentArray.indexOf(this.html())+1, 0, contentArray.length-1)]);
	};

	$.fn.moveAttr = function(from, to){
		return this.each(function(){
			let $el = $(this),
				value = $el.attr(from);
			if (typeof value !== 'undefined')
				$el.removeAttr(from).attr(to, value);
		});
	};

	$.fn.backgroundImageUrl = function(url){ return this.css('background-image', 'url("'+url.replace(/"/g,'%22')+'")') };

	$.attributifyRegex = regex => typeof regex === 'string' ? regex : regex.toString().replace(/(^\/|\/[img]*$)/g,'');
	$.fn.patternAttr = function(regex){
		if (typeof regex === 'undefined')
			throw new Error('$.fn.patternAttr: regex is undefined');
		return this.attr('pattern', $.attributifyRegex(regex));
	};

	$.fn.enable = function(removeClass){
		if (typeof removeClass !== 'undefined')
			this.removeClass(removeClass);
		return this.prop('disabled', false);
	};
	$.fn.disable = function(addClass){
		if (typeof addClass !== 'undefined')
			this.removeClass(addClass);
		return this.prop('disabled', true);
	};

	$.fn.hasAttr = function(attr){
		const el = this.get(0);
		return el && el.hasAttribute(attr);
	};

	$.fn.isOverflowing = function(){
		let el = this.get(0),
			curOverflow = el.style.overflow;

		if (!curOverflow || curOverflow === "visible")
			el.style.overflow = "hidden";

		let isOverflowing = el.clientWidth < el.scrollWidth || el.clientHeight < el.scrollHeight;

		el.style.overflow = curOverflow;

		return isOverflowing;
	};

	(function($){
		const handler = () => false;
		const disableEvents = 'mousewheel scroll keydown';
		const elements = 'html,body';

		$.scrollTo = (pos, speed, callback) => {
			$(elements)
				.on(disableEvents,handler)
				.animate({scrollTop:pos},speed,callback)
				.off(disableEvents,handler);
			$w.on('beforeunload',function(){
				$(elements).stop().off(disableEvents,handler);
			});
		};
	})(jQuery);

	const codeMirrorDefaults = {
		lineNumbers: true,
		lineSeparator: '\n',
		tabSize: 4,
		indentWithTabs: true,
		lineWrapping: true,
		styleActiveLine: true,
		styleActiveSelected: true,
		theme: 'custom',
	};
	const codeMirrorModeOptions = {
		html: {
			name: 'xml',
			htmlMode: true,
		},
		markdown: {
			name: 'markdown',
			highlightFormatting: true,
			fencedCodeBlockHighlighting: true,
		},
		colorguide: {
			name: 'colorguide',
		},
	};
	$.renderCodeMirror = ({ $el, mode, ...options }) => {
		if (options.value === null)
			delete options.value;
		// eslint-disable-next-line new-cap
		const instance =  CodeMirror($el.get(0), {
			...codeMirrorDefaults,
			mode: codeMirrorModeOptions[mode],
			...options,
		});
		$el.children().addClass(`mode-${mode}`);
		return instance;
	};

	// Sortable shortcut
	const sortableDataKey = 'sortable';
	$.fn.sortable = function(options = null){
		if (options === null)
			return this.data(sortableDataKey);
		else if (options === 'destroy'){
			this.data(sortableDataKey).destroy();
			this.removeData(sortableDataKey);
		}

		// eslint-disable-next-line new-cap
		this.data(sortableDataKey, new Sortable.default(this.get(0), options))
	};

	// http://stackoverflow.com/a/16270434/1344955
	$.isInViewport = el => {
		let rect;
		try {
			rect = el.getBoundingClientRect();
		} catch (e){
			return true;
		}

		return (
			rect.bottom > 0 &&
			rect.right > 0 &&
			rect.left < $w.width() &&
			rect.top < $w.height()
		);
	};
	$.fn.isInViewport = function(){
		return this[0] ? $.isInViewport(this[0]) : false;
	};

	$.loadImages = html => {
		const $el = $(html);

		return new Promise(fulfill => {
			const $imgs = $el.find('img');
			let loaded = 0;
			if ($imgs.length)
				$imgs.on('load',e => {
					loaded++;
					if (loaded === $imgs.length)
						fulfill({ $el, e });
				}).on('error', e => fulfill({ e }));
			else fulfill({ $el });
		});
	};

	$.isRunningStandalone = () => window.matchMedia('(display-mode: standalone)').matches;

	window.sidebarForcedVisible = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0) >= 1200;
	window.withinMobileBreakpoint = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0) <= 650;

	$.randomString = () => parseInt(Math.random().toFixed(20).replace(/[.,]/,''), 10).toString(36);

	$.hrefToPath = href => href.replace(/^.*?[\w\d]\//,'/');

	(function(){
		const PATTERNS = [
			/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})?$/i,
			/^#?([a-f\d])([a-f\d])([a-f\d])$/i,
			/^rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*([10]|0?\.\d+))?\s*\)$/i,
		];
		/**
		 * Maps patterns to a boolean indicating whether the results can be used directly (without hex->dec conversion)
		 */
		const DIRECT_USE = [false,false,true];
		class RGBAColor {
			constructor(r, g, b, a = 1){
				this.red = !isNaN(r) ? parseFloat(r) : NaN;
				this.green = !isNaN(r) ? parseFloat(g) : NaN;
				this.blue = !isNaN(r) ? parseFloat(b) : NaN;
				this.alpha = parseFloat(a);
			}

			setRed(r){
				this.red = r;

				return this;
			}

			setGreen(g){
				this.green = g;

				return this;
			}

			setBlue(b){
				this.blue = b;

				return this;
			}

			setAlpha(a){
				this.alpha = a;

				return this;
			}

			isTransparent(){
				return this.alpha !== 1.0;
			}

			// Return values range from 0 to 255 (inclusive)
			// http://stackoverflow.com/questions/11867545#comment52204960_11868398
			yiq(){
				return ((this.red*299)+(this.green*587)+(this.blue*114))/1000;
			}

			isLight(){
				return this.yiq() > 127 || this.alpha < 0.5;
			}

			isDark(){
				return !this.isLight();
			}

			toHex(){
				return '#'+($.pad(this.red.toString(16))+$.pad(this.green.toString(16))+$.pad(this.blue.toString(16))).toUpperCase();
			}

			toHexa(){
				return this.toHex()+($.pad(Math.round(this.alpha*255).toString(16)).toUpperCase());
			}

			toRGB(){
				return `rgb(${this.red},${this.green},${this.blue})`;
			}

			toRGBA(){
				return `rgba(${this.red},${this.green},${this.blue},${this.alpha})`;
			}

			toRGBString(){
				return this.isTransparent() ? this.toRGBA() : this.toRGB();
			}

			toHexString(){
				return this.isTransparent() ? this.toHexa() : this.toHex();
			}

			toString(){
				return this.isTransparent() ? this.toRGBA() : this.toHex();
			}

			toRGBArray(){
				return [this.red, this.green, this.blue];
			}

			invert(alpha = false){
				this.red = 255 - this.red;
				this.green = 255 - this.green;
				this.blue = 255 - this.blue;
				if (alpha)
					this.alpha = 1 - this.alpha;

				return this;
			}
			round(copy = false){
				if (copy){
					return $.RGBAColor.fromRGB(this).round();
				}
				else {
					this.red = Math.round(this.red);
					this.green = Math.round(this.green);
					this.blue = Math.round(this.blue);
					this.alpha = $.roundTo(this.alpha, 2);
					return this;
				}
			}

			static _parseWith(color, pattern, index){
				const matches = color.match(pattern);
				if (!matches)
					return null;

				let values = matches.slice(1, 5);

				if (!DIRECT_USE[index]){
					if (values[0].length === 1)
						values = values.map(el => el+el);
					values[0] = parseInt(values[0], 16);
					values[1] = parseInt(values[1], 16);
					values[2] = parseInt(values[2], 16);
					if (typeof values[3] !== 'undefined')
						values[3] = $.roundTo(parseInt(values[3], 16)/255,3);
				}

				return new RGBAColor(...values);
			}

			/**
			 * @param {string} color
			 *
			 * @return {RGBAColor|null}
			 */
			static parse(color){
				let output = null;
				if (color instanceof $.RGBAColor)
					return color;
				if (typeof color === "string"){
					color = color.trim();
					$.each(PATTERNS, (index, pattern) => {
						let result = this._parseWith(color, pattern, index);
						if (result === null)
							return;

						output = result;
						return false;
					});
				}

				return output;
			}

			/**
			 * @param {Object} color
			 *
			 * @return {RGBAColor|null}
			 */
			static fromRGB(color){
				return new $.RGBAColor((color.r || color.red), (color.g || color.green), (color.b || color.blue), (color.a || color.alpha || 1));
			}
		}
		RGBAColor.COMPONENTS = ['red', 'green', 'blue'];

		$.RGBAColor = RGBAColor;
	})();

	(function(){
		class DynamicCache {
			constructor(endpoint, params = {}){
				this._list = {};
				this._endpoint = endpoint;
				this._params = params;
			}
			read(id = 'default'){
				return this.#loadItems(id);
			}
			#loadItems(id){
				return new Promise((res, rej) => {
					if (typeof this._list[id] !== 'undefined'){
						res(this._list[id]);
						return;
					}

					$.API.get(this._endpoint.replace('%d', id),this._params,$.mkAjaxHandler(data => {
						if (!data.status) return $.Dialog.fail('Cache entry retrieval', data.message);

						this._list[id] = data.list;
						res(this._list[id]);
					})).fail(() => rej());
				});
			}
		}

		Object.assign(window, { DynamicCache });
	})();

	function isFunction(obj) {
		return typeof obj === "function" && typeof obj.nodeType !== "number";
	}

	$.each(["put", "delete"], function(i, method) {
		$[method] = function(url, data, callback, type) {
			if (isFunction(data)){
				type = type || callback;
				callback = data;
				data = undefined;
			}

			return jQuery.ajax(jQuery.extend({
				url: url,
				type: method,
				dataType: type,
				data: data,
				success: callback
			}, $.isPlainObject(url) && url));
		};
	});

	if (typeof $.API !== 'undefined'){
		$.each(['get','post','put','delete'], (i, el) => {
			((method) => {
				$.API[method] = function(url, ...args) {
					return $[method]($.API.API_PATH+url, ...args);
				};
			})(el);
		});
	}

	class KeyValueCache {
		constructor() {
			this.clear();
		}

		set(k, v) {
			this.cache[k] = v;

			return v;
		}

		get(k) {
			return this.cache[k];
		}

		has(k) {
			return this.cache.hasOwnProperty(k);
		}

		clear() {
			this.cache = {};
		}
	}
	window.KeyValueCache = KeyValueCache;
})();
