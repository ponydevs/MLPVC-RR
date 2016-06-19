/* jshint bitwise: false */
/* global $w,$d,$head,$navbar,$body,$header,$sidebar,$sbToggle,$main,$footer,console,prompt,HandleNav,getTimeDiff,one,createTimeStr,PRINTABLE_ASCII_PATTERN,io */
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
	if (typeof window.console.warn !== 'function')
		window.console.warn = function(){};
	if (typeof window.console.clear !== 'function')
		window.console.clear = function(){};

	// document.createElement shortcut
	var mk = function(){ return document.createElement.apply(document,arguments) };
	window.mk = function(){return mk.apply(window,arguments)};

	// $(document.createElement) shortcut
	$.mk = function(){ return $(document.createElement.apply(document,arguments)) };

	// Convert relative URL to absolute
	$.urlToAbsolute = function(url){
		var a = mk('a');
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
		Enter: 13,
		Space: 32,
		LeftArrow: 37,
		RightArrow: 39,
		Tab: 9,
		Comma: 188,
	};
	$.isKey = function(Key, e){
		return e.keyCode === Key;
	};

	// Make the first letter of the first or all word(s) uppercase
	$.capitalize = function(str, all){
		if (all) return str.replace(/((?:^|\s)[a-z])/g, function(match){
			return match.toUpperCase();
		});
		else return str.length === 1 ? str.toUpperCase() : str[0].toUpperCase()+str.substring(1);
	};

	// Array.includes (ES7) polyfill
	if (typeof Array.prototype.includes !== 'function')
		Array.prototype.includes = function(elem){ return this.indexOf(elem) !== -1 };

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

		while (str.length < len)
			str = dir === $.pad.left ? char+str : str+char;

		return str;
	};
	$.pad.right = !($.pad.left = true);

	$.scaleResize = function(w, h, p){
		var div, d = {
			scale: p.scale,
			width: p.width,
			height: p.height
		};
		if (!isNaN(d.scale)){
			d.height = Math.round(h * d.scale);
			d.width = Math.round(w * d.scale);
		}
		else if (isNaN(d.width)){
			div = d.height / h;
			d.width = Math.round(w * div);
			d.scale = div;
		}
		else if (isNaN(d.height)){
			div = d.width / w;
			d.height = Math.round(h * div);
			d.scale = div;
		}
		else throw new Error('[scalaresize] Invalid arguments');
		return d;
	};

	// http://stackoverflow.com/a/3169849/1344955
	$.clearSelection = function(){
		if (window.getSelection){
			var sel = window.getSelection();
			if (sel.empty) // Chrome
				sel.empty();
			else if (sel.removeAllRanges) // Firefox
				sel.removeAllRanges();
		}
		else if (document.selection)  // IE?
			document.selection.empty();
	};

	// Create AJAX response handling function
	$w.on('ajaxerror',function(){
		var details = '';

		if (arguments.length > 1){
			var data = [].slice.call(arguments, 1);
			if (data[1] === 'abort')
				return;
			details = ' Details:<pre><code>' + data.slice(1).join('\n').replace(/</g,'&lt;') + '</code></pre>';
			details += ' Response body:';
			var xdebug = /^(?:<br \/>\n)?(<pre class='xdebug-var-dump'|<font size='1')/;
			if (xdebug.test(data[0].responseText))
				details += '<div class="reset">'+data[0].responseText.replace(xdebug, '$1')+'</div>';
			else details += '<pre><code>' + data[0].responseText.replace(/</g,'&lt;') + '</code></pre>';
		}
		$.Dialog.fail(false,'There was an error while processing your request.'+details);
	});
	$.mkAjaxHandler = function(f){
		return function(data){
			if (typeof data !== 'object'){
				//noinspection SSBasedInspection
				console.log(data);
				$w.triggerHandler('ajaxerror');
				return;
			}

			if (typeof f === 'function') f.call(data);
		};
	};

	// Checks if a variable is a function and if yes, runs it
	// If no, returns default value (undefined or value of def)
	$.callCallback = function(func, params, def){
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
		var tempdata = $(this).serializeArray(), data = {};
		$.each(tempdata,function(i,el){
			data[el.name] = el.value;
		});
		if (typeof obj === 'object')
			$.extend(data, obj);
		return data;
	};

	// Get CSRF token from cookies
	$.getCSRFToken = function(){
		var n = document.cookie.match(/CSRF_TOKEN=([a-z\d]+)/i);
		if (n && n.length)
			return n[1];
		else throw new Error('Missing CSRF_TOKEN');
	};
	// Get Access token from cookies
	$.getAccessToken = function(){
		var n = document.cookie.match(/access=([^;]+)(?:$|;)/i);
		if (n && n.length)
			return n[1];
	};
	$.ajaxPrefilter(function(event, origEvent){
		if ((origEvent.type||event.type).toUpperCase() !== 'POST')
			return;

		var t = $.getCSRFToken();
		if (typeof event.data === "undefined")
			event.data = "";
		if (typeof event.data === "string"){
			var r = event.data.length > 0 ? event.data.split("&") : [];
			r.push("CSRF_TOKEN=" + t);
			event.data = r.join("&");
		}
		else event.data.CSRF_TOKEN = t;
	});
	var lasturl;
	$.ajaxSetup({
		dataType: "json",
		error: function(xhr){
			if ([401, 404, 500, 503].indexOf(xhr.status) === -1)
				$w.triggerHandler('ajaxerror',[].slice.call(arguments));
			$body.removeClass('loading');
		},
		beforeSend: function(_, settings) {
			lasturl = settings.url;
		},
		statusCode: {
			401: function(){
				$.Dialog.fail(undefined, "Cross-site Request Forgery attack detected. Please notify the site administartors.");
			},
			404: function(){
				$.Dialog.fail(false, "Error 404: The requested endpoint ("+lasturl.replace(/</g,'&lt;').replace(/\//g,'/<wbr>')+") could not be found");
			},
			500: function(){
				$.Dialog.fail(false, 'The request failed due to an internal server error. If this persists, please <a href="#feedback" class="send-feedback">let us know</a>!');
			},
			503: function(){
				$.Dialog.fail(false, 'The request failed because the server is temporarily unavailable. This whouldn\'t take too long, please try again in a few seconds.<br>If the problem still persist after a few minutes, please let us know by clicking the "Send feedback" link in the footer.');
			}
		}
	});

	// Copy any text to clipboard
	// Must be called from within an event handler
	var $notif;
	$.copy = function(text, e){
		if (!document.queryCommandSupported('copy')){
			prompt('Copy with Ctrl+C, close with Enter', text);
			return true;
		}

		var $helper = $.mk('textarea'),
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
						.html('<span class="typcn typcn-clipboard"></span> <span class="typcn typcn-'+(success?'tick':'cancel')+'"></span>')
						.appendTo($body);
				if (e){
					var w = $notif.outerWidth(),
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

	// Convert HEX to RGB
	$.hex2rgb = function(hexstr){
		return {
			r: parseInt(hexstr.substring(1, 3), 16),
			g: parseInt(hexstr.substring(3, 5), 16),
			b: parseInt(hexstr.substring(5, 7), 16)
		};
	};

	// Convert RGB to HEX
	$.rgb2hex = function(color){
		return '#'+(16777216 + (parseInt(color.r, 10) << 16) + (parseInt(color.g, 10) << 8) + parseInt(color.b, 10)).toString(16).toUpperCase().substring(1);
	};

	// :valid pseudo polyfill
	if (typeof $.expr[':'].valid !== 'function')
		$.extend($.expr[':'], {
			valid: function(el){
				return el.validity.valid;
			}
		});

	$.roundTo = function(number, precision){
		var pow = Math.pow(10, precision);
		return Math.round(number*pow)/pow;
	};

	$.rangeLimit = function(input,overflow){
		var min, max, paramCount = 2;
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
		var range = document.createRange();
		range.selectNodeContents(this.get(0));
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	};

	var shortHex = /^#?([A-Fa-f0-9]{3})$/;
	window.SHORT_HEX_COLOR_PATTERN = shortHex;
	$.hexpand = function(shorthex){
		var match = shorthex.trim().match(shortHex);
		if (!match)
			return shorthex.replace(/^#?/,'#');
		match = match[1];
		return '#'+match[0]+match[0]+match[1]+match[1]+match[2]+match[2];
	};

	$.fn.toggleHtml = function(contentArray){
		return this.html(contentArray[$.rangeLimit(contentArray.indexOf(this.html())+1, true, contentArray.length-1)]);
	};

	$.fn.moveAttr = function(from, to){
		return this.each(function(){
			var $el = $(this),
				value = $el.attr(from);
			if (typeof value !== 'undefined')
				$el.removeAttr(from).attr(to, value);
		});
	};

	$.fn.backgroundImageUrl = function(url){
		return this.css('background-image', 'url("'+url.replace(/"/g,'%22')+'")');
	};

	$.attributifyRegex = function(regex){
		return regex.toString().replace(/(^\/|\/[img]*$)/g,'');
	};
	$.fn.patternAttr = function(regex){
		this.attr('pattern', $.attributifyRegex(regex));

		return this;
	};

	$.fn.enable = function(){ return this.attr('disabled', false) };
	$.fn.disable = function(){ return this.attr('disabled', true) };

	$.fn.hasAttr = function(attr){
		return this.get(0).hasAttribute(attr);
	};

	$.scrollTo = function(pos, speed, callback){
		var scrollf = function(){return false};
		$('html,body')
			.on('mousewheel scroll',scrollf)
			.animate({scrollTop:pos},speed,callback)
			.off('mousewheel scroll',scrollf);
		$w.on('beforeunload',function(){
			$('html,body').stop().off('mousewheel scroll',scrollf);
		});
	};

	window.URL = function(url){
		var a = document.createElement('a'),
			me = {};
		a.href = url;
		$.each(['hash','host','hostname','href','origin','pathname','port','protocol','search'],function(_,el){
			me[el] = a[el];
		});
		me.pathString = me.pathname.replace(/^([^\/].*)$/,'/$1')+me.search+me.hash;
		return me;
	};

	window.OpenSidebarByDefault = function(){
		return Math.max(document.documentElement.clientWidth, window.innerWidth || 0) >= 1200;
	};
	
	// http://stackoverflow.com/a/16861050
	var PopupCalcCenter = function(w, h){
		var dualScreenLeft = typeof window.screenLeft !== 'undefined' ? window.screenLeft : screen.left,
			dualScreenTop = typeof window.screenTop !== 'undefined' ? window.screenTop : screen.top,
			width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width,
			height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height,
			left = ((width / 2) - (w / 2)) + dualScreenLeft,
			top = ((height / 2) - (h / 2)) + dualScreenTop;

		return {top:top, left:left};
	};
	$.PopupOpenCenter = function(url, title, w, h) {
		var calcpos = PopupCalcCenter(w,h),
			newWindow = window.open(url,title,'scrollbars=yes,width='+w+',height='+h+',top='+calcpos.top+',left='+calcpos.left);

		if (window.focus)
			newWindow.focus();
		
		return newWindow;
	};
	$.PopupMoveCenter = function(popup, w, h){
		var calcpos = PopupCalcCenter(w,h);
		popup.resizeTo(w,h);
		popup.moveTo(calcpos.left,calcpos.top);
	};

	var DocReadyAlwaysRun = function(){
		console.log('> DocReadyAlwaysRun()');
		$d.triggerHandler('paginate-refresh');

		// Sign in button handler
		var OAUTH_URL = window.OAUTH_URL,
			consent = localStorage.getItem('cookie_consent');

		$('#signin').off('click').on('click',function(){
			var $this = $(this),
				opener = function(sure){
					if (!sure) return;

					$.Dialog.close();
					localStorage.setItem('cookie_consent',1);
					$this.disable();

					var redirect = function(){
						$.Dialog.wait(false, 'Redirecting you to DeviantArt');
						window.location.href = OAUTH_URL+"&state="+encodeURIComponent(window.location.href.replace(window.location.origin,''));
					};

					if (navigator.userAgent.indexOf('Trident') !== -1)
						return redirect();

					var rndk = (~~(Math.random()*99999999)).toString(36), success = false, closeCheck, popup;
					window[' '+rndk] = function(){
						success = true;
						clearInterval(closeCheck);
						$.Dialog.wait(false, 'Reloading page');
						$.Navigation.reload(function(){
							$.Dialog.close();
							popup.close();
						});
					};
					try {
						popup = $.PopupOpenCenter(OAUTH_URL+"&state="+rndk,'login','450','580');
					}catch(e){}
					// http://stackoverflow.com/a/25643792
					var onWindowClosed = function(){
							if (success)
								return;

							if (document.cookie.indexOf('auth=') !== -1)
								return window[' '+rndk];

							$.Dialog.fail(false, 'Popup-based login unsuccessful');
							redirect();
						};
					closeCheck = setInterval(function () {
						try {
							if (!popup || popup.closed){
								clearInterval(closeCheck);
								onWindowClosed();
							}
						}catch(e){}
					}, 500);
					$w.on('beforeunload', function(){
						success = true;
						popup.close();
					});
					$.Dialog.wait('Sign-in process', "Opening popup window");
					$.Dialog.wait(false, "Waiting for you to sign in");
				};

			if (!consent) $.Dialog.confirm('EU Cookie Policy Notice','In compliance with the <a href="http://ec.europa.eu/ipg/basics/legal/cookies/index_en.htm">EU Cookie Policy</a> we must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.<br><br>If you would like to avoid these completly harmless pieces of information required to use this website, click "Decline" and continue browsing as a guest.<br><br>This warning will not appear again if you accept our use of persistent cookies.',['Accept','Decline'],opener);
			else opener(true);
		});

		// Sign out button handler
		$('#signout').off('click').on('click',function(){
			var title = 'Sign out';
			$.Dialog.confirm(title,'Are you sure you want to sign out?',function(sure){
				if (!sure) return;

				$.Dialog.wait(title,'Signing out');

				$.post('/signout',$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(title,this.message);

					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		});

		// HTTPS button
		try {
			if (/^https/.test(location.protocol))
				throw undefined;
			var canhttps = sessionStorage.getItem('canhttps');
			if (canhttps === 'false')
				throw undefined;
			$.ajax({
				method: "POST",
				url: 'https://'+location.host+'/ping',
				success: $.mkAjaxHandler(function(){
					if (this.status)
						$sidebar.append(
							$.mk('a').attr({
								'class': 'btn green typcn typcn-lock-closed',
								href: location.href.replace(/^http:/,'https:')
							}).text('Switch to HTTPS')
						);
					sessionStorage.setItem('canhttps', canhttps = this.status.toString());
				}),
				error: function(){ sessionStorage.setItem('canhttps', canhttps = 'false') }
			});
		}
		catch(e){}
	};

	$.Navigation = (function(module){
		module = this;

		// Document Ready state simulation
		var DocReadyHandlers = [];
		window.DocReady = {
			push: function(f, flusher){
				if (typeof flusher === 'function')
					f.flush = flusher;
				DocReadyHandlers.push(f);
			}
		};
		var _docReady = function(){
			console.log('> _docReady()');

			DocReadyAlwaysRun();

			var l = DocReadyHandlers.length;
			if (l) for (var i = 0; i<l; i++){
				DocReadyHandlers[i].call(window);
				console.log('> DocReadyHandlers[%d]()',i);
			}
		};
		module.docReady = function(){ _docReady() };
		module.flushDocReady = function(){
			for (var i=0,l=DocReadyHandlers.length; i<l; i++){
				if (typeof DocReadyHandlers[i].flush !== 'function')
					continue;

				DocReadyHandlers[i].flush();
				console.log('Flushed DocReady handler #%d', i);
			}
			DocReadyHandlers = [];
		};

		// Navigation & page loading
		var _xhr = false,
			_loadCSS = function _loadCSS(css, callback){
				if (!css.length)
					return $.callCallback(callback);

				console.group('Loading CSS');
				(function _recursivelyLoadCSS(item){
					if (item >= css.length){
						console.groupEnd();
						return $.callCallback(callback);
					}

					var requrl = css[item];
					_xhr = $.ajax({
						url: requrl,
						dataType: 'text',
						success: function(data){
							data = data.replace(/url\((['"])?\.\.\//g,'url($1/');
							$head.append($.mk('style').attr('href',requrl).text(data));
							console.log('%c#%d (%s)', 'color:green', item, requrl);
						},
						error: function(){
							console.log('%c#%d (%s)', 'color:red', item, requrl);
						},
						complete: function(){ _recursivelyLoadCSS(item+1) }
					});
				})(0);
			},
			_loadJS = function _loadJS(js, callback){
				if (!js.length)
					return $.callCallback(callback);

				console.group('Loading JS');
				(function _recursivelyLoadJS(item){
					if (item >= js.length){
						console.groupEnd();
						return $.callCallback(callback);
					}

					var requrl = js[item];
					_xhr = $.ajax({
						url: requrl,
						dataType: 'text',
						success:function(data){
							$body.append(
								$.mk('script')
									.attr('data-src', requrl)
									.text(data.replace(/(\/\/#\s*sourceMappingURL=)(.*)/g,'$1/js/$2'))
							);
							console.log('%c#%d (%s)', 'color:green', item, requrl);
						},
						error: function(){
							console.log('%c#%d (%s)', 'color:red', item, requrl);
						},
						complete: function(){ _recursivelyLoadJS(item+1) }
					});
				})(0);
			},
			_navigateTo = function(url, callback, block_reload){
				console.clear();
				console.group('[AJAX-Nav] PING %s (block_reload: %s)', url, block_reload);

				if (_xhr !== false){
					try {
						_xhr.abort();
						console.log('Previous AJAX request aborted');
					}catch(e){}
					_xhr = false;
				}

				$body.addClass('loading');
				var ajaxcall = $.ajax({
					url: url,
					data: {'via-js': true},
					success: $.mkAjaxHandler(function(){
						if (_xhr !== ajaxcall){
							console.log('%cAJAX request objects do not match, bail','color:red');
							console.groupEnd();
							return;
						}
						if (!this.status){
							$body.removeClass('loading');
							_xhr = false;
							console.log('%cNavigation error %s', 'color:red', this.message);
							console.groupEnd();
							return $.Dialog.fail('Navigation error', this.message);
						}

						url = new URL(this.responseURL).pathString+(new URL(url).hash);
						$w.triggerHandler('unload');
						if (!window.OpenSidebarByDefault())
							$sbToggle.trigger('sb-close');

						var css = this.css,
							js = this.js,
							content = this.content,
							sidebar = this.sidebar,
							footer = this.footer,
							pagetitle = this.title,
							avatar = this.avatar,
							signedIn = this.signedIn;

						$main.empty();
						var doreload = false,
							ParsedLocation = new URL(location.href),
							reload = !block_reload && ParsedLocation.pathString === url;

						module.flushDocReady();

						console.groupCollapsed('Checking JS files to skip...');
						$body.children('script[src], script[data-src]').each(function(){
							var $this = $(this),
								src = $this.attr('src') || $this.attr('data-src');
							if (reload){
								if (!/js\/dialog\./.test(src))
									$this.remove();
								return true;
							}

							var pos = js.indexOf(src);

							if (pos !== -1 && !/js\/(colorguide[\.\-]|episodes-manage)/.test(src)){
								js.splice(pos, 1);
								console.log('%cSkipped %s','color:saddlebrown',src);
							}
							else {
								if (src.indexOf('global') !== -1)
									return !(doreload = true);
								$this.remove();
							}
						});
						console.log('%cFinished.','color:green');
						console.groupEnd();
						if (doreload !== false){
							console.log('%cFull page reload forced by changes in global.js', 'font-weight:bold;color:orange');
							console.groupEnd();
							return (location.href = url);
						}

						console.groupCollapsed('Checking CSS files to skip...');
						var CSSSelector = 'link[href], style[href]';
						$head.children(CSSSelector).each(function(){
							var $this = $(this),
								href = $this.attr('href'),
								pos = css.indexOf(href);

							if (pos !== -1){
								css.splice(pos, 1);
								console.log('%cSkipped %s','color:saddlebrown',href);
							}
							else $this.attr('data-remove','true');
						});
						console.log('%cFinished.','color:green');
						console.groupEnd();

						console.groupEnd();
						console.group('[AJAX-Nav] GET %s', url);

						$w.trigger('beforeunload');
						_loadCSS(css, function(){
							$head.children(CSSSelector.replace(/href/g,'data-remove=true')).remove();
							$main.addClass('pls-wait').html(content);
							$sidebar.html(sidebar);
							$footer.html(footer);
							window.updateTimes();
							window.setCD();
							var $headerNav = $header.find('nav').children();
							$headerNav.children().first().children('img').attr('src', avatar);
							$headerNav.children(':not(:first-child)').remove();
							$headerNav.append($sidebar.find('nav').children().children().clone());

							window.CommonElements();
							if (!block_reload)
								history[ParsedLocation.pathString === url?'replaceState':'pushState']({'via-js':true},'',url);
							document.title = pagetitle;
							module.lastLoadedPathname = window.location.pathname;

							_loadJS(js, function(){
								$.Navigation.docReady();
								console.log('%cDocument ready', 'color:green');
								console.groupEnd();
								$body.removeClass('loading');
								$main.removeClass('pls-wait');

								if (signedIn)
									window.WSNotifications(signedIn);

								$.callCallback(callback);
								//noinspection JSUnusedAssignment
								_xhr = false;
							});
						});
					})
				});
				_xhr = ajaxcall;
			};
		module.visit = function(){ _navigateTo.apply(module, arguments) };
		module.lastLoadedPathname = window.location.pathname;

		// Page reloading
		var _reload = function(callback, delay){
			var f = (typeof delay === 'number' && delay > 0)
				? function(){ setTimeout(callback, delay) }
				: callback;
			_navigateTo(location.pathname+location.search+location.hash, f);
		};
		module.reload = function(){ _reload.apply(module, arguments) };

		module.firstLoadDone = false;

		return module;
	}).call({});
})(jQuery);

// Runs on first load
$(function(){
	'use strict';

	if ($.Navigation.firstLoadDone)
		return;

	console.log('[HTTP-Nav] > $(document).ready()');
	console.group('[HTTP-Nav] GET '+window.location.pathname+window.location.search+window.location.hash);

	// Load footer
	$.get('/footer-git',$.mkAjaxHandler(function(){
		if (this.footer)
			$footer.prepend(this.footer);
	}));

	// Sidebar toggle handler
	(function(){
		var triggerResize = function(){
			setTimeout(function(){
				$w.trigger('resize');
			},510);
		};
		$sbToggle.off('click sb-open sb-close').on('click',function(e){
			e.preventDefault();

			$sbToggle.trigger('sb-'+($body.hasClass('sidebar-open')?'close':'open'));
		}).on('sb-open sb-close',function(e){
			var close = e.type.substring(3) === 'close';
			$body[close ? 'removeClass' : 'addClass']('sidebar-open');
			localStorage[close ? 'setItem' : 'removeItem']('sidebar-closed', 'true');
			triggerResize();
		});
		if (localStorage.getItem('sidebar-closed') !== 'true' && window.OpenSidebarByDefault()){
			$body.addClass('sidebar-open');
			triggerResize();
		}
	})();

	// Upcoming Episode Countdown
	(function(){
		var $cd, cdtimer,
			months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			cdupdate = function(){
				var cdExists = typeof $cd.parent === "function" && $cd.parent().length > 0,
					diff = {}, now, airs;
				if (cdExists){
					now = new Date();
					airs = new Date($cd.attr('datetime'));
					diff = getTimeDiff(now, airs);
				}
				if (!cdExists || diff.past){
					if (cdExists)
						$cd.parents('li').remove();
					window.clearCD();
					return window.setCD();
				}
				var text = 'in ';
				if (diff.time < one.month){
					if (diff.week > 0)
						diff.day += diff.week * 7;
					if (diff.day > 0)
						text += diff.day+' day'+(diff.day!==1?'s':'')+' & ';
					if (diff.hour > 0)
						text += diff.hour+':';
					text += $.pad(diff.minute)+':'+$.pad(diff.second);
				}
				else text = createTimeStr(now, airs);
				$cd.text(text);
			};
		window.clearCD = function(){
			if (typeof cdtimer !== 'undefined'){
				clearInterval(cdtimer);
				cdtimer = undefined;
			}
		};
		window.setCD = function(){
			var $uc = $('#upcoming');
			if (!$uc.length)
				return;

			var $lis = $uc.children('ul').children();
			if (!$lis.length)
				return $uc.remove();

			$cd = $lis.first().find('time').addClass('nodt');
			window.clearCD();
			cdtimer = setInterval(cdupdate, 1000);
			cdupdate();

			$uc.find('li').each(function(){
				var $this = $(this),
					$calendar = $this.children('.calendar'),
					d = new Date($this.find('.countdown').data('airs') || $this.find('time').attr('datetime'));
				$calendar.children('.top').text(months[d.getMonth()]);
				$calendar.children('.bottom').text(d.getDate());
			});
			window.updateTimes();
		};
		window.setCD();
	})();

	// Feedback form
	$(document).off('click','.send-feedback').on('click','.send-feedback',function(e){
		e.preventDefault();
		e.stopPropagation();
		$('#ctxmenu').hide();

		$.Dialog.info('How to send feedback',$.mk('div').append(
			"<p>If you're having an issue with the site and would like to let the developer know, here's how you can contact him:</p>",
			$.mk('ul').append(
				"<li><a href='https://discord.gg/0vv70fepSINi2Hy8'>Join our Discord server</a> and describe your issue in the <strong>#support</strong> channel</li>",
				"<li>Send a note to <a href='http://djdavid98.deviantart.com/'>DJDavid98</a> on DeviantArt</li>",
				"<li>Send an e-mail to <a href='mailto:seinopsys@gmail.com'>seinopsys@gmail.com</a></li>",
				"<li>Add <a href='skype:guzsik.david?add'>guzsik.david</a> on Skype</li>"
			),
			$.mk('p').attr('class','notice info').append(
				"If you have a GitHub account, please ",
				$.mk('a').attr('href',$footer.find('a.issues').attr('href')).text('create an issue'),
				" on the project's GitHub page instead of using the methods above."
			)
		).children(),'feedback-form');
	});

	// Color Average form
	$(document).off('click','.action--color-avg').on('click','.action--color-avg',function(e){
		e.preventDefault();
		e.stopPropagation();

		var title = 'Colour Average Calculator',
			callme = function(){
				$.Dialog.close();
				$.Dialog.request(title,window.$ColorAvgForm.clone(true,true),'color-avg-form','Save progress',function($form){
					$form.triggerHandler('added');
				});
			};

		if (typeof window.$ColorAvgForm === 'undefined'){
			$.Dialog.wait(title,'Loading form, please wait');
			var scriptUrl = '/js/global-color_avg_form.min.js';
			$.getScript(scriptUrl,callme).fail(function(){
				setTimeout(function(){
					$.Dialog.close(function(){
						$.Dialog.wait(title, 'Loading script (attempt #2)');
						$.getScript(scriptUrl.replace(/min\./,''), callme).fail(function(){
							$.Dialog.fail(title, 'Form could not be loaded');
						});
					});
				},1);
			});
		}
		else callme();
	});

	$d.on('click','a[href]',function LinkClick(e){
		if (e.which > 2) return true;

		var link = this;
		if (link.host !== location.host) return true;

		if (link.pathname === location.pathname && link.search === location.search){
			if (link.protcol !== location.protocol)
				return true;
			e.preventDefault();
			window._trighashchange = link.hash !== location.hash;
			if (window._trighashchange === true)
				history.replaceState(history.state,'',link.href);
			setTimeout(function(){ delete window._trighashchange },1);
			$w.triggerHandler('hashchange');
			return;
		}

		// Check if link seems to have a file extension
		if (!/^.*\/[^.]*$/.test(link.pathname))
			return true;

		if ($(this).parents('#dialogContent').length !== 0)
			$.Dialog.close();

		e.preventDefault();
		$.Navigation.visit(this.href);
	});

	$w.on('popstate',function(e){
		if (typeof window._trighashchange !== 'undefined')
			return;
		var state = e.originalEvent.state,
			goto = function(url,callback){ $.Navigation.visit(url, callback, true) };

		if (state !== null && !state['via-js'] && state.paginate === true)
			return $w.trigger('nav-popstate', [state, goto]);
		goto(location.href);
	});

	(function(){
		var conn,
			wsdecoder = function(f){
				return function(data){
					if (typeof data === 'string'){
						try {
							data = JSON.parse(data);
						}
						catch(err){}
					}

					f(data);
				};
			};
		function WSNotifications(signedIn){
			if (!window.io || !signedIn)
				return;

			var $notifCnt = $sbToggle.children('.notif-cnt'),
				$notifSb = $sidebar.children('.notifications'),
				$notifSbList = $notifSb.children('.notif-list');

			$notifSbList.off('click','.mark-read').on('click','.mark-read',function(e){
				e.preventDefault();

				var $el = $(this);
				if ($el.is(':disabled'))
					return;
				$el.css('opacity', '.5').disable();

				var nid = $el.attr('data-id');
				$.post('/notifications/mark-read/'+nid,$.mkAjaxHandler(function(){
					if (this.status)
						return;

					$el.css('opacity', '').enable();
					return $.Dialog.fail('Mark notification as read', this.message);
				}));
			});

			if ($notifCnt.length === 0)
				$notifCnt = $.mk('span').attr({'class':'notif-cnt',title:'New notifications'}).prependTo($sbToggle);

			if (conn)
				return;

			conn = io('https://ws.'+location.hostname+':8667/', { reconnectionDelay: 5000 });
			conn.on('connect', function(){
				console.log('[WS] Connected, authenticating…');
				conn.emit('auth', {access:$.getAccessToken()}, wsdecoder(function(data){
					console.log('[WS] '+data.message);
				}));
			});
			conn.on('notif-cnt', wsdecoder(function(data){
				console.log('[WS] Got notification count (data.cnt=%d)', parseInt(data.cnt||0));
				if (!data.cnt){
					$notifSb.stop().slideUp(undefined,function(){
						$notifSbList.empty();
						$notifCnt.empty();
					});
				}
				else $.post('/notifications/get',$.mkAjaxHandler(function(){
					$notifCnt.text(data.cnt);
					$notifSbList.html(this.list);
					$notifSb.stop().slideDown();
				}));
			}));
			conn.on('disconnect',function(){
				console.log('[WS] Disconnected');
			});
		}
		WSNotifications(window.signedIn);
		window.WSNotifications = function(uid){WSNotifications(uid)};
	})();

	$.Navigation.docReady();
	console.log('%cDocument ready','color:green');
	console.groupEnd();
	$.Navigation.firstLoadDone = true;
});

// Remove loading animation from header on load
$w.on('load',function(){
	'use strict';
	$body.removeClass('loading');
});
