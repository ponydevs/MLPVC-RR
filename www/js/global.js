/* jshint bitwise: false */
/* global $w,$d,$head,$body,$header,$sidebar,$sbToggle,$main,$footer,console,prompt,HandleNav,getTimeDiff,one,createTimeStr,PRINTABLE_ASCII_REGEX */
(function($){
	'use strict';
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

	// Create AJAX response handling function
	$w.on('ajaxerror',function(){
		var details = '';

		if (arguments.length > 1){
			var data = [].slice.call(arguments, 1);
			if (data[1] === 'abort')
				return;
			details = ' Details:<pre><code>' + data.slice(1).join('\n').replace(/</g,'&lt;') + '</code></pre>';
			details += ' Response body:';
			var xdebug = /^(?:<br \/>\n)?(<pre class='xdebug-var-dump')/;
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
	$.copy = function(text){
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
		} catch(e){}

		if (!success)
			$.Dialog.fail('Copy to clipboard', 'Copying text to clipboard failed!');
		setTimeout(function(){
			$helper.remove();
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
		this.html(contentArray[$.rangeLimit(contentArray.indexOf(this.html())+1, true, contentArray.length-1)]);

		return this;
	};

	$.fn.moveAttr = function(from, to){
		this.each(function(){
			var $el = $(this),
				value = $el.attr(from);
			if (typeof value !== 'undefined')
				$el.removeAttr(from).attr(to, value);
		});

		return this;
	};

	$.fn.backgroundImageUrl = function(url){
		this.css('background-image', 'url("'+url.replace(/"/g,'%22')+'")');

		return this;
	};

	$.fn.patternAttr = function(regex){
		this.attr('pattern', regex.toString().replace(/(^\/|\/[img]*$)/g,''));

		return this;
	};

	$.fn.enable = function(){
		this.attr('disabled', false);

		return this;
	};

	$.fn.disable = function(){
		this.attr('disabled', true);

		return this;
	};

	$.scrollTo = function(pos, speed, callback){ $('html,body').animate({scrollTop:pos},speed,callback) };

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
})(jQuery);

function DocumentIsReady(){
	'use strict';
	$d.triggerHandler('paginate-refresh');

	// Sign in button handler
	var OAUTH_URL = window.OAUTH_URL,
		consent = localStorage.getItem('cookie_consent');

	$('#signin').off('click').on('click',function(){
		var $this = $(this),
			opener = function(sure){
				if (!sure) return;

				localStorage.setItem('cookie_consent',1);
				$this.attr('disabled', true);
				$.Dialog.wait('Sign-in process', 'Redirecting you to DeviantArt');
				window.location.href = OAUTH_URL;
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

				HandleNav.reload(function(){
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
		if (canhttps === null || canhttps === 'true'){
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
	}
	catch(e){}

	var l = window.DocReady.length;
	if (l) for (var i = 0; i<l; i++)
		window.DocReady[i].call(window);
}
function OpenSidebarByDefault(){
	'use strict';
	return window.matchMedia('all and (min-width: 1200px)').matches;
}
var DocReadyOnce = false;
$(function(){
	'use strict';
	if (DocReadyOnce) return;
	DocReadyOnce = true;

	// Sidebar toggle handler
	var xhr = false;
	$sbToggle.off('click').on('click',function(e){
		e.preventDefault();

		$sbToggle.trigger('sb-'+($body.hasClass('sidebar-open')?'close':'open'));
	}).on('sb-open sb-close',function(e){
		var close = e.type.substring(3) === 'close';
		$body[close ? 'removeClass' : 'addClass']('sidebar-open');
		localStorage[close ? 'setItem' : 'removeItem']('sidebar-closed', 'true');
	});
	if (localStorage.getItem('sidebar-closed') !== 'true' && OpenSidebarByDefault())
		$body.addClass('sidebar-open');

	// Upcoming Episode Countdown
	var $cd, cdtimer,
		months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	window.setCD = function(){
		var $uc = $('#upcoming');
		if (!$uc.length)
			return;

		var $lis = $uc.children('ul').children();
		if (!$lis.length)
			return $uc.remove();

		$cd = $lis.first().find('time').addClass('nodt');
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
	function cdupdate(){
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
			clearInterval(cdtimer);
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
	}
	$w.on('unload',function(){
		$cd = {length:0};
	});

	// Feedback form
	$(document).on('click','.send-feedback',function(e){
		e.preventDefault();
		e.stopPropagation();
		$('#ctxmenu').hide();

		$.Dialog.info('How to send feedback',$.mk('div').append(
			"<p>If you're having an issue with the site and would like to let the developer know, here's how you can contact him:</p>",
			$.mk('ul').append(
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

	// AJAX page loader
	var REWRITE_REGEX = window.REWRITE_REGEX,
		SITE_TITLE = window.SITE_TITLE;

	function LinkClick(e){
		if (e.which > 2) return true;

		var link = this;
		if (
			link.hostname !== location.hostname || (
				!REWRITE_REGEX.test(link.pathname) &&
				!/^\/@([A-Za-z\-\d]{1,20})$/.test(link.pathname)
			)
		) return true;

		if (link.pathname === location.pathname && link.search === location.search)
			return true;

		if ($(this).parents('#dialogContent').length !== 0)
			$.Dialog.close();

		e.preventDefault();
		Navigation(this.href);
	}
	$d.off('click','a[href]',LinkClick).on('click','a[href]',LinkClick);

	$w.off('popstate').on('popstate',function(e){
		var state = e.originalEvent.state,
			goto = function(url,callback){ Navigation(url, callback, true) };

		if (state !== null && !state['via-js'] && state.paginate === true)
			return $w.trigger('nav-popstate', [state, goto]);
		goto(location.href);
	});

	function Navigation(url, callback, block_reload){
		if (xhr !== false){
			try {
				xhr.abort();
			}catch(e){}
			xhr = false;
		}

		$body.addClass('loading');
		var ajaxcall = $.ajax({
			url: url,
			data: {'via-js': true},
			success: $.mkAjaxHandler(function(){
				if (xhr !== ajaxcall) return;
				if (!this.status){
					$body.removeClass('loading');
					xhr = false;
					return $.Dialog.fail('Navigation error', this.message);
				}

				url = new URL(this.responseURL).pathString+(new URL(url).hash);
				$w.triggerHandler('unload');
				if (!OpenSidebarByDefault())
					$sbToggle.trigger('sb-close');

				var css = this.css,
					js = this.js,
					content = this.content,
					sidebar = this.sidebar,
					footer = this.footer,
					pagetitle = this.title,
					avatar = this.avatar;

				$main.empty();
				var doreload = false,
					ParsedLocation = new URL(location.href),
					reload = !block_reload && ParsedLocation.pathString === url;

				$body.children('script[src], script[data-src]').each(function(){
					var $this = $(this),
						src = $this.attr('src') || $this.attr('data-src');
					if (reload){
						if (!/js\/dialog\./.test(src))
							$this.remove();
						return true;
					}

					var pos = js.indexOf(src);

					if (pos !== -1 && !/js\/colorguide[\.\-]/.test(src))
						js.splice(pos, 1);
					else {
						if (src.indexOf('global') !== -1)
							return !(doreload = true);
						$this.remove();
					}
				});
				if (doreload !== false){
					location.href = url;
					return location.href;
				}

				var CSSSelector = 'link[href], style[href]';
				$head.children(CSSSelector).each(function(){
					var $this = $(this),
						href = $this.attr('href'),
						pos = css.indexOf(href);

					if (pos !== -1)
						css.splice(pos, 1);
					else $this.attr('data-remove','true');
				});

				console.clear();

				(function LoadCSS(item){
					if (item >= css.length){
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
						document.title = (pagetitle?pagetitle+' - ':'')+SITE_TITLE;
						window.HandleNav.lastLoadedPathname = window.location.pathname;

						window.DocReady = [];

						return (function LoadJS(item){
							if (item >= js.length){
								DocumentIsReady();
								$body.removeClass('loading');
								$main.removeClass('pls-wait');

								$.callCallback(callback);
								//noinspection JSUnusedAssignment
								xhr = false;
								return;
							}

							var requrl = js[item];
							xhr = $.ajax({
								url: requrl,
								dataType: 'text',
								success:function(data){
									$body.append(
										$.mk('script')
											.attr('data-src', requrl)
											.text(data.replace(/(\/\/#\s*sourceMappingURL=)(.*)/g,'$1/js/$2'))
									);
									LoadJS(item+1);
								}
							});
						})(0);
					}

					var requrl = css[item];
					xhr = $.ajax({
						url: requrl,
						dataType: 'text',
						success: function(data){
							data = data.replace(/url\((['"])?\.\.\//g,'url($1/');
							$head.append($.mk('style').attr('href',requrl).text(data));
							LoadCSS(item+1);
						}
					});
				})(0);
			})
		});
		xhr = ajaxcall;
	}
	window.HandleNav = function(){ Navigation.apply(window, arguments) };
	var Reload = function(callback, delay){
		var f = (typeof delay === 'number' && delay > 0)
			? function(){ setTimeout(callback, delay) }
			: callback;
		Navigation(location.pathname, f);
	};
	window.HandleNav.reload = function(){ Reload.apply(window, arguments) };
	window.HandleNav.lastLoadedPathname = window.location.pathname;

	DocumentIsReady();
});

// Remove loading animation from header on load
$w.on('load',function(){
	'use strict';
	$body.removeClass('loading');
});
