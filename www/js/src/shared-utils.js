/* jshint bitwise: false */
/* global $w,$d,$head,$navbar,$body,$header,$sidebar,$sbToggle,$main,$footer,console,prompt,HandleNav,getTimeDiff,one,createTimeStr,PRINTABLE_ASCII_PATTERN,io,moment,Time,ace,mk,WSNotifications */
(function($){
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
			let val = null;
			try {
				val = this.store.getItem(key);
			}catch(e){}
			return val;
		}
		set(key, value){
			try {
				this.store.setItem(key, value);
			}catch(e){}
		}
		remove(key){
			try {
				this.store.removeItem(key);
			}catch(e){}
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
	window.CommonElements = function(){
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
	window.CommonElements();

	// Common key codes for easy reference
	window.Key = {
		Tab: 9,
		Enter: 13,
		Alt: 18,
		Space: 32,
		LeftArrow: 37,
		UpArrrow: 38,
		RightArrow: 39,
		DownArrrow: 40,
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
		let dateformat = { order: 'Do MMMM YYYY, H:mm:ss' };
		dateformat.orderwd = `dddd, ${dateformat.order}`;

		class DateFormatError extends Error {
			constructor(message, element){
				super(message);

		        this.name = 'DateFormatError';
				this.element = element;
			}
		}

		class Time {
			static Update(){
				$('time[datetime]:not(.nodt)').addClass('dynt').each(function(){
					let $this = $(this),
						date = $this.attr('datetime');
					if (typeof date !== 'string') throw new TypeError('Invalid date data type: "'+(typeof date)+'"');

					let Timestamp = moment(date);
					if (!Timestamp.isValid())
						throw new DateFormatError('Invalid date format: "'+date+'"', this);

					let Now = moment(),
						showDayOfWeek = !$this.attr('data-noweekday'),
						timeAgoStr = Timestamp.from(Now),
						$elapsedHolder = $this.parent().children('.dynt-el'),
						updateHandler = $this.data('dyntime-beforeupdate');

					if (typeof updateHandler === 'function'){
						let result = updateHandler(Time.Difference(Now.toDate(), Timestamp.toDate()));
						if (result === false) return;
					}

					if ($elapsedHolder.length > 0 || $this.hasClass('no-dynt-el')){
						$this.html(Timestamp.format(showDayOfWeek ? dateformat.orderwd : dateformat.order));
						$elapsedHolder.html(timeAgoStr);
					}
					else $this.attr('title', Timestamp.format(dateformat.order)).html(timeAgoStr);
				});
			}

			static Difference(now, timestamp) {
				let substract = (now.getTime() - timestamp.getTime())/1000,
					d = {
						past: substract > 0,
						time: Math.abs(substract),
						target: timestamp
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

		Time.Update();
		setInterval(Time.Update, 10e3);
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

		return func.apply(window, params);
	};

	// Convert .serializeArray() result to object
	$.fn.mkData = function(obj){
		let tempdata = this.find(':input:valid').serializeArray(), data = {};
		$.each(tempdata,function(i,el){
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
		let n = document.cookie.match(/CSRF_TOKEN=([a-z\d]+)/i);
		if (n && n.length)
			return n[1];
		else throw new Error('Missing CSRF_TOKEN');
	};
	$.ajaxPrefilter(function(event, origEvent){
		if ((origEvent.type||event.type).toUpperCase() !== 'POST')
			return;

		let t = $.getCSRFToken();
		if (typeof event.data === "undefined")
			event.data = "";
		if (typeof event.data === "string"){
			let r = event.data.length > 0 ? event.data.split("&") : [];
			r.push(`CSRF_TOKEN=${t}`);
			event.data = r.join("&");
		}
		else event.data.CSRF_TOKEN = t;
	});
	let lasturl,
		statusCodeHandlers = {
			401: function(){
				$.Dialog.fail(undefined, "Cross-site Request Forgery attack detected. Please <a class='send-feedback'>let us know</a> about this issue so we can look into it.");
			},
			404: function(){
				$.Dialog.fail(false, "Error 404: The requested endpoint ("+lasturl.replace(/</g,'&lt;').replace(/\//g,'/<wbr>')+") could not be found");
			},
			500: function(){
				$.Dialog.fail(false, 'A request failed due to an internal server error. If this persists, please <a class="send-feedback">let us know</a>!');
			},
			503: function(){
				$.Dialog.fail(false, 'A request failed because the server is temporarily unavailable. This shouldn’t take too long, please try again in a few seconds.<br>If the problem still persist after a few minutes, please let us know by clicking the "Send feedback" link in the footer.');
			},
			504: function(){
				$.Dialog.fail(false, 'A request failed because the server took too long to respond. A refresh should fix this issue, but if it doesn\'t, please <a class="send-feedback">let us know</a>.');
			},
		};
	$.ajaxSetup({
		dataType: "json",
		error: function(xhr){
			if (typeof statusCodeHandlers[xhr.status] !== 'function')
				$w.triggerHandler('ajaxerror',$.toArray(arguments));
		},
		beforeSend: function(_, settings){
			lasturl = settings.url;
		},
		statusCode: statusCodeHandlers,
	});

	// Copy any text to clipboard
	// Must be called from within an event handler
	let $notif;
	$.copy = (text, e) => {
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
		} catch(err){}

		setTimeout(function(){
			$helper.remove();
			if (typeof $notif === 'undefined' || e){
				if (typeof $notif === 'undefined')
					$notif = $.mk('span')
						.attr({
							id: 'copy-notify',
							'class': ! success ? 'fail' : undefined,
						})
						.html(`<span class="typcn typcn-clipboard fa fa-clipboard"></span> <span class="typcn typcn-${success?'tick':'cancel'} fa fa-${success?'check':'times'}"></span>`)
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
		}, 1);
	};

	$.compareFaL =  (a,b) => JSON.stringify(a) === JSON.stringify(b);

	// Convert HEX to RGB
	$.hex2rgb = hexstr =>
		({
			r: parseInt(hexstr.substring(1, 3), 16),
			g: parseInt(hexstr.substring(3, 5), 16),
			b: parseInt(hexstr.substring(5, 7), 16)
		});

	// Convert RGB to HEX
	$.rgb2hex = color => '#'+(16777216 + (parseInt(color.r, 10) << 16) + (parseInt(color.g, 10) << 8) + parseInt(color.b, 10)).toString(16).toUpperCase().substring(1);

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

	$.rangeLimit = function(input, overflow){
		let min, max, paramCount = 2;
		switch (arguments.length-paramCount){
			case 1:
				min = 0;
				max = arguments[paramCount];
			break;
			case 2:
				min = arguments[paramCount];
				max = arguments[paramCount+1];
			break;
			default:
				throw new Error('Invalid number of parameters for $.rangeLimit');
		}
		if (overflow){
			if (input > max)
				input = min;
			else if (input < min)
				input = max;
		}
		return Math.min(max, Math.max(min, input));
	};

	$.fn.select = function(){
		let range = document.createRange();
		range.selectNodeContents(this.get(0));
		let sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	};

	let shortHexRegex = /^#?([\dA-Fa-f]{3})$/;
	window.SHORT_HEX_COLOR_PATTERN = shortHexRegex;
	$.hexpand = shorthex => {
		let match = shorthex.trim().match(shortHexRegex);
		if (!match)
			return shorthex.replace(/^#?/,'#');
		match = match[1];
		return '#'+match[0]+match[0]+match[1]+match[1]+match[2]+match[2];
	};

	// Return values range from 0 to 255 (inclusive)
	// http://stackoverflow.com/questions/11867545#comment52204960_11868398
	$.yiq = hex => {
		if (typeof hex !== 'string')
			throw new Error(`Invalid hex value (${hex})`);
		const rgb = $.hex2rgb(hex);
	    return ((rgb.r*299)+(rgb.g*587)+(rgb.b*114))/1000;
	};

	$.momentToYMD = momentInstance => momentInstance.format('YYYY-MM-DD');
	$.momentToHM = momentInstance => momentInstance.format('HH:mm');
	$.mkMoment = (datestr, timestr, utc) => moment(datestr+'T'+timestr+(utc?'Z':''));

	$.escapeRegex = pattern => pattern.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');

	$.fn.toggleHtml = function(contentArray){
		return this.html(contentArray[$.rangeLimit(contentArray.indexOf(this.html())+1, true, contentArray.length-1)]);
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

	$.fn.enable = function(){
		return this.attr('disabled', false);
	};
	$.fn.disable = function(){
		return this.attr('disabled', true);
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

	$.scrollTo = (pos, speed, callback) => {
		let scrollf = function(){return false};
		$('html,body')
			.on('mousewheel scroll',scrollf)
			.animate({scrollTop:pos},speed,callback)
			.off('mousewheel scroll',scrollf);
		$w.on('beforeunload',function(){
			$('html,body').stop().off('mousewheel scroll',scrollf);
		});
	};

	$.getAceEditor = (title, mode, cb) => {
		let fail = () => $.Dialog.fail(false, 'Failed to load Ace Editor'),
			done = () => {
				$.Dialog.clearNotice();
				cb(`ace/mode/${mode}`);
			};
		if (typeof window.ace === 'undefined'){
			$.Dialog.wait(title, 'Loading Ace Editor');
			$.getScript('/js/min/ace/ace.js', function(){
				window.ace.config.set('basePath', '/js/min/ace');
				done();
			}).fail(fail);
		}
		else done();
	};
	
	$.aceInit = function(editor){
		editor.$blockScrolling = Infinity;
		editor.setShowPrintMargin(false);
		let session = editor.getSession();
		session.setUseSoftTabs(false);
		session.setOption('indentedSoftWrap', false);
		session.setOption('useWorker', true);
		session.on("changeAnnotation", function() {
			let annotations = session.getAnnotations() || [],
				i = 0,
				len = annotations.length,
				removed = false;
			while (i < len){
				if (/doctype first\. Expected/.test(annotations[i].text)){
					annotations.splice(i, 1);
					len--;
					removed = true;
				}
				else i++;
			}
			if (removed)
				session.setAnnotations(annotations);
		});
		return session;
	};

	// http://stackoverflow.com/a/16270434/1344955
	$.isInViewport = el => {
		let rect;
		try {
	        rect = el.getBoundingClientRect();
		}catch(e){ return true }

	    return rect.bottom > 0 &&
	        rect.right > 0 &&
	        rect.left < (window.innerWidth || document.documentElement.clientWidth) /* or $(window).width() */ &&
	        rect.top < (window.innerHeight || document.documentElement.clientHeight) /* or $(window).height() */;
	};
	$.fn.isInViewport = function(){
		return this[0] ? $.isInViewport(this[0]) : false;
	};

	$.loadImages = html => {
		const $el = $(html);

		return new Promise((fulfill) => {
			const $imgs = $el.find('img');
			if ($imgs.length)
				$el.find('img').on('load error',function(e){
					fulfill($el, e);
				});
			else fulfill($el);
		});
	};

	$.isRunningStandalone = () => window.matchMedia('(display-mode: standalone)').matches;

	window.URL = url => {
		let a = document.createElement('a'),
			parsed = {};
		a.href = url;
		$.each(['hash','host','hostname','href','origin','pathname','port','protocol','search'],function(_,el){
			parsed[el] = a[el];
		});
		parsed.pathString = parsed.pathname.replace(/^([^\/].*)$/,'/$1')+parsed.search+parsed.hash;
		return parsed;
	};

	window.sidebarForcedVisible = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0) >= 1200;
	window.withinMobileBreakpoint = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0) <= 650;

	$.randomString = () => parseInt(Math.random().toFixed(20).replace(/[.,]/,''), 10).toString(36);
})(jQuery);
