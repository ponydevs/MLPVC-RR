(function($){
	// document.createElement shortcut
	$.mk = function(){ return $(document.createElement.apply(document,arguments)) };

	// Convert relative URL to absolute
	$.urlToAbsolute = function(url){
		var a = $.mk('a');
		a.href = url;
		return a.href;
	};

	// Globalie zcoomon elements
	$.extend(window, {
		$w: $(window),
		$document: $(document),
		$body: $(document.body),
		$head: $(document.head),
		$header: $('header'),
		$sbToggle: $('.sidebar-toggle'),
		$main: $('#main'),
		$sidebar: $('#sidebar'),
		$footer: $('footer'),
	});
	window.$title = $head.children('title');

	// Create AJAX response handling function
	$w.on('ajaxerror',function(){
		$.Dialog.fail(false,'There was an error while processing your request. You may find additional details in the browser\'s console.');
	});
	$.mkAjaxHandler = function(f){
		return function(data){
			if (typeof data !== 'object'){
				console.log(data);
				$w.trigger('ajaxerror');
				return;
			}

			if (typeof f === 'function') f.call(data);
		};
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
	$.ajaxSetup({
		statusCode: {
			401: function(){
				$.Dialog.fail(undefined, "Cross-site Request Forgery attack detected. Please notify the site administartors.");
			},
			404: function(){
				$.Dialog.fail(undefined, "Error 404: The requested endpoint could not be found");
			},
			500: function(){
				$.Dialog.fail(false, 'The request failed due to an internal server error. If this persists, please open an issue on GitHub using the link in the footer!');
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

	window.URL = function(url){
		var a = document.createElement('a'),
			me = this;
		a.href = url;
		$.each(['hash','host','hostname','href','origin','pathname','port','protocol','search'],function(_,el){
			me[el] = a[el];
		});
		me.pathString = me.pathname+me.search+me.hash;
	}
})(jQuery);

DocReady.push(function Global(){
	// Sign in button handler
	var OAUTH_URL = window.OAUTH_URL,
		consent = localStorage.getItem('cookie_consent');

	$('#signin').on('click',function(){
		var $this = $(this),
			opener = function(sure){
				if (!sure) return;

				localStorage.setItem('cookie_consent',1);
				$this.attr('disabled', true);
				$.Dialog.wait('Sign-in process', 'Redirecting you to DeviantArt', function(){
					window.location.href = OAUTH_URL;
				});
			};

		if (consent == undefined) $.Dialog.confirm('EU Cookie Policy Notice','In compliance with the <a href="http://ec.europa.eu/ipg/basics/legal/cookies/index_en.htm">EU Cookie Policy</a> we must inform you that our website will store cookies on your device to remember your logged in status between browser sessions.<br><br>If you would like to avoid these completly harmless pieces of information required to use this website, click "Decline" and continue browsing as a guest.<br><br>This warning will not appear again if you accept our use of persistent cookies.',['Accept','Decline'],opener);
		else opener(true);
	});

	// Sign out button handler
	$('#signout').on('click',function(){
		var title = 'Sign out';
		$.Dialog.confirm(title,'Are you sure you want to sign out?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out');

			$.post('/signout',$.mkAjaxHandler(function(){
				if (this.status){
					$.Dialog.success(title,this.message);
					setTimeout(function(){
						HandleNav(location.href, function(){
							$.Dialog.close();
						});
					},1000);
				}
				else $.Dialog.fail(title,this.message);
			}));
		});
	});

	// Countdown
	var $cd, cdtimer,
		months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	window.setCD = function(){
		var $uc = $('#upcoming');
		if ($uc.length === 0) return;
		$cd = $uc.find('li').first().find('time').addClass('nodt');
		cdtimer = setInterval(function(){
			cdupdate($cd);
		}, 1000);
		cdupdate($cd);

		$uc.find('li').each(function(){
			var $this = $(this),
				$calendar = $this.children('.calendar'),
				d = new Date($this.find('.countdown').data('airs') || $this.find('time').attr('datetime'));
			$calendar.children('.top').text(months[d.getMonth()]);
			$calendar.children('.bottom').text(d.getDate());
		});
		window.updateTimesF();
	};
	window.setCD();
	function pad(n){return n<10?'0'+n:n}
	function cdupdate($cd){
		var now = new Date(),
			airs = new Date($cd.attr('datetime')),
			diff = getTimeDiff(now, airs);
		if (diff.past === true || $cd.length === 0){
			if ($cd.length > 0){
				var $oldcd = $cd,
					$nextime = $oldcd.parents('li').next().find('time');
				$oldcd.parents('li').remove();
			}
			clearInterval(cdtimer);
			if ($cd.length === 0 || $nextime.length === 0) return $('#upcoming').remove();
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
			text += +pad(diff.minute)+':'+pad(diff.second);
		}
		else text = createTimeStr(now, airs);
		$cd.text(text);
	}
	$w.on('unload',function(){ clearInterval(cdtimer) });
});

function DocumentIsReady(){
	$document.triggerHandler('paginate-refresh');
	if (window.DocReady.length < 1) return;
	for (var i = 0, l = window.DocReady.length; i<l; i++)
		window.DocReady[i].call(window);
}
function OpenSidebarByDefault(){
	return window.matchMedia('all and (min-width: 1200px)').matches;
}
var DocReadyOnce = false;
$(function(){
	if (DocReadyOnce) return;
	DocReadyOnce = true;

	// Sidebar toggle handler
	var $body = $(document.body),
		xhr = false;
	$sbToggle.off('click').on('click',function(e){
		e.preventDefault();

		if (xhr !== false) return;
		$sbToggle.trigger('sb-'+($body.hasClass('sidebar-open')?'close':'open'));
	}).on('sb-open sb-close',function(e){
		var close = e.type.substring(3) === 'close';
		$body[close ? 'removeClass' : 'addClass']('sidebar-open');
		localStorage[close ? 'setItem' : 'removeItem']('sidebar-closed', 'true');
	});
	var openSidebar = localStorage.getItem('sidebar-closed') !== 'true';
	if (!OpenSidebarByDefault()) openSidebar = !openSidebar;
	if (openSidebar)
		$body.addClass('sidebar-open');

	// AJAX page loader
	var REWRITE_REGEX = window.REWRITE_REGEX;

	function LinkClick(e){
		if (e.which > 2) return true;

		var link = this;
		if (link.hostname !== location.hostname || !REWRITE_REGEX.test(link.pathname))
			return true;

		e.preventDefault();

		HandleNav(this.href);
	}
	$document.off('click','a[href]',LinkClick).on('click','a[href]',LinkClick);

	$w.off('popstate').on('popstate',function(e){
		var state = e.originalEvent.state;

		if (!state['via-js'])
			return $w.trigger('nav-popstate', [state]);
		HandleNav(location.href, state);
	});

	function HandleNav(url, callback){
		if (xhr !== false){
			xhr.abort();
			xhr = false;
		}

		var title = 'Navigation';
		$body.addClass('loading');
		xhr = $.ajax({
			url: url,
			data: {'via-js': true},
			success: $.mkAjaxHandler(function(){
				if (!this.status) $.Dialog.fail(title, this.message);

				url = new URL(this.responseURL+(new URL(url).hash)).pathString;
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
					reload = ParsedLocation.pathString === url;
				$body.children('script[src], script[data-src]').each(function(){
					var $this = $(this),
						src = $this.attr('src') || $this.attr('data-src'),
						pos = js.indexOf(src);

					if (!reload && pos !== -1)
						js.splice(pos, 1);
					else {
						if (src.indexOf('global') !== -1){
							doreload = true;
							return false;
						}
						else $this.remove();
					}
				});
				if (doreload) return location.href = url;
				$head.children('link[href], style[href]').each(function(){
					var $this = $(this),
						href = $this.attr('href'),
						pos = css.indexOf(href);

					if (pos !== -1)
						css.splice(pos, 1);
					else $this.remove();
				});

				(function LoadCSS(item){
					if (item >= css.length){
						$main.addClass('pls-wait').html(content);
						$sidebar.html(sidebar);
						$footer.html(footer);
						window.updateTimesF();
						var $headerNav = $header.find('nav').children();
						$headerNav.children().first().children('img').attr('src', avatar);
						$headerNav.children(':not(:first-child)').remove();
						$headerNav.append($sidebar.find('nav').children().children().clone());
						$title.text(pagetitle);

						history[ParsedLocation.pathString === url?'replaceState':'pushState']({'via-js':true},'',url);

						window.DocReady = [];

						return (function LoadJS(item){
							if (item >= js.length){
								DocumentIsReady();
								$body.removeClass('loading');
								$main.removeClass('pls-wait');
								if (typeof callback === 'function')
									callback();
								//noinspection JSUnusedAssignment
								xhr = false;
								return;
							}

							var requrl = js[item];
							xhr = $.ajax({
								url: requrl,
								dataType: 'text',
								success:function(data){
									$body.append($.mk('script').attr('data-src', requrl).text(data));
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
							$head.append($.mk('style').attr('href',requrl).text(data));
							LoadCSS(item+1);
						}
					});
				})(0);
			})
		});
	}
	window.HandleNav = function(){ HandleNav.apply(window, arguments) };

	DocumentIsReady();
});

// Remove loading animation from header on load
$w.on('load',function(){
	$body.removeClass('loading');
});
