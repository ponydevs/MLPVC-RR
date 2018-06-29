/* global moment,ace */
(function(){
	"use strict";

	let fluidboxThisAction = (jQueryObject) => {
		jQueryObject.fluidbox({
			immediateOpen: true,
			loader: true,
		})
		.on('openstart.fluidbox',function(){
			$body.addClass('fluidbox-open');
			let $this = $(this);
			if ($this.parents('#dialogContent').length)
				$body.addClass('fluidbox-in-dialog');
		})
		.on('openend.fluidbox',function(){
			let $this = $(this),
				href = $this.attr('href');
			$this.data('href', href);
			$this.removeAttr('href');
			let $ghost = $this.find('.fluidbox__ghost');
			if ($ghost.children().length === 0)
				$this.find('.fluidbox__ghost').append(
					$.mk('img').attr('src',href).css({
						opacity: 0,
						width: '100%',
						height: '100%',
					})
				);
		})
		.on('closestart.fluidbox', function() {
			$body.removeClass('fluidbox-open');
			let $this = $(this);
			$this.attr('href', $this.data('href'));
			$this.removeData('href');
		})
		.on('closeend.fluidbox',function(){
			$body.removeClass('fluidbox-in-dialog');
		});
	};
	$.fn.fluidboxThis = function(callback){
		if (typeof $.fn.fluidbox === 'function'){
			fluidboxThisAction(this);
			$.callCallback(callback);
		}
		else {
			$.getScript('/js/min/jquery.ba-throttle-debounce.js',() => {
				$.getScript('/js/min/jquery.fluidbox.js',() => {
					fluidboxThisAction(this);
					$.callCallback(callback);
				}).fail(function(){
					$.Dialog.fail(false, 'Loading Fluidbox plugin failed');
				});
			}).fail(function(){
				$.Dialog.fail(false, 'Loading Debounce/throttle plugin failed');
			});
		}
		return this;
	};

	// http://stackoverflow.com/a/16861050
	let popupCalcCenter = (w, h) => {
		let dualScreenLeft = typeof window.screenLeft !== 'undefined' ? window.screenLeft : screen.left,
			dualScreenTop = typeof window.screenTop !== 'undefined' ? window.screenTop : screen.top,
			width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width,
			height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height,
			left = ((width / 2) - (w / 2)) + dualScreenLeft,
			top = ((height / 2) - (h / 2)) + dualScreenTop;

		return {top:top, left:left};
	};
	$.PopupOpenCenter = (url, title, w, h) => {
		let calcPos = popupCalcCenter(w,h),
			newWindow = window.open(url,title,`scrollbars=yes,width=${w},height=${h},top=${calcPos.top},left=${calcPos.left}`);

		if (window.focus)
			newWindow.focus();

		return newWindow;
	};
	$.PopupMoveCenter = (popup, w, h) => {
		let calcpos = popupCalcCenter(w,h);
		popup.resizeTo(w,h);
		popup.moveTo(calcpos.left,calcpos.top);
	};
	$d.on('click','#turbo-sign-in',function(e){
		e.preventDefault();

		let $this = $(this),
			origNotice = $this.parent().html();
		$this.disable();

		let success = false,
			closeCheck,
			popup;
		window.__authCallback = function(){
			success = true;
			if ($.Dialog._open.type === 'request')
				$.Dialog.clearNotice(/Redirecting you to DeviantArt/);
			else $.Dialog.close();
			popup.close();
		};
		try {
			popup = window.open('/da-auth/begin');
		}
		catch(_){ return $.Dialog.fail(false, 'Could not open login pop-up. Please open another page') }

		$.Dialog.wait(false, 'Redirecting you to DeviantArt');
		closeCheck = setInterval(function(){
			try {
				if (!popup || popup.closed){
					clearInterval(closeCheck);
					if (success)
						return;
					$.Dialog.fail(false, origNotice);
				}
			}catch(e){}
		}, 500);
	});

	$.Navigation = {
		visit(url){
			window.location.href = url;
		},
		reload(displayDialog = false){
			if (displayDialog)
				$.Dialog.wait(false, 'Reloading page', true);
			window.location.reload();
		}
	};

	// Sidebar toggle handler
	(function(){
		let triggerResize = function(){
			setTimeout(function(){
				$w.trigger('resize');
			},510);
		};

		$sbToggle.off('click sb-open sb-close').on('click', function(e){
			e.preventDefault();

			if (window.sidebarForcedVisible())
				return;

			$sbToggle.trigger('sb-'+($body.hasClass('sidebar-open')?'close':'open'));
		}).on('sb-open sb-close', function(e){
			let close = e.type.substring(3) === 'close';
			$body[close ? 'removeClass' : 'addClass']('sidebar-open');
			try {
				$.LocalStorage[close ? 'set' : 'remove']('sidebar-closed', 'true');
			}catch(_){}
			triggerResize();
		});
	})();

	// Upcoming Countdowns
	(function(){
		let $cd, cdtimer,
			clearCD = function(){
				if (typeof cdtimer !== 'undefined'){
					clearInterval(cdtimer);
					cdtimer = undefined;
				}
			},
			cdupdate = function(){
				let cdExists = typeof $cd.parent === "function" && $cd.parent().length !== 0,
					diff = {}, now, airs;
				if (cdExists){
					now = new Date();
					airs = new Date($cd.attr('datetime'));
					diff = Time.Difference(now, airs);
				}
				if (!cdExists || diff.past){
					clearCD();
					$.API.get('/about/upcoming', $.mkAjaxHandler(function(){
						if (!this.status) return console.error(`Failed to load upcoming event list: ${this.message}`);

						const $uc = $('#upcoming');
						$uc.find('ul').html(this.html);
						if (!this.html)
							$uc.addClass('hidden');
						else $uc.removeClass('hidden');
						window.setUpcomingCountdown();
					}));
					return;
				}
				let text;
				if (diff.time < Time.InSeconds.month && diff.month === 0){
					if (diff.week > 0)
						diff.day += diff.week * 7;
					text = 'in ';
					if (diff.day > 0)
						text += diff.day+' day'+(diff.day!==1?'s':'')+' & ';
					if (diff.hour > 0)
						text += diff.hour+':';
					text += $.pad(diff.minute)+':'+$.pad(diff.second);
				}
				else {
					clearCD();
					setTimeout(cdupdate, 10000);
					text = moment(airs).from(now);
				}
				$cd.text(text);
			};
		window.setUpcomingCountdown = function(){
			let $uc = $('#upcoming');
			if (!$uc.length)
				return;

			let $lis = $uc.children('ul').children();
			if (!$lis.length)
				return $uc.addClass('hidden');
			$uc.removeClass('hidden');

			$cd = $lis.first().find('time').addClass('nodt');
			clearCD();
			cdtimer = setInterval(cdupdate, 1000);
			cdupdate();

			$uc.find('li').each(function(){
				let $this = $(this),
					$calendar = $this.children('.calendar'),
					d = moment($this.find('.countdown').data('airs') || $this.find('time').attr('datetime'));
				$calendar.children('.top').text(d.format('MMM'));
				$calendar.children('.bottom').text(d.format('D'));
			});
			Time.Update();

			$lis.find('.title').simplemarquee({
			    speed: 25,
			    cycles: Infinity,
			    space: 25,
			    handleHover: false,
			    delayBetweenCycles: 0,
			}).addClass('marquee');
		};
		window.setUpcomingCountdown();
	})();

	// Feedback form
	$(document).off('click','.send-feedback').on('click','.send-feedback', function(e){
		e.preventDefault();
		e.stopPropagation();
		$('#ctxmenu').hide();

		// Screw JS file scraping spam bots
		const email = ['seinopsys','gmail.com'].join('@');

		$.Dialog.info($.Dialog.isOpen() ? undefined : 'Contact',
			`<h3>How to contact us</h3>
			<p>You can use any of the following methods to reach out to us:</p>
			<ul>
				<li><a href='https://discord.gg/0vv70fepSILbdJOD'>Join our Discord server</a> and describe your issue/idea in the <strong>#support</strong> channel</li>
				<li><a href='https://www.deviantart.com/mlp-vectorclub/notes/'>Send a note </a>to the group on DeviantArt</li>
				<li><a href='mailto:${email}'>Send an e-mail</a> to ${email}</li>
			</ul>`
		);
	});

	// Color Average form
	$(document).off('click','.action--color-avg').on('click','.action--color-avg', function(e){
		e.preventDefault();
		e.stopPropagation();

		let title = 'Color Average Calculator',
			callme = function(){
				$.Dialog.close();
				let $clone = window.$ColorAvgFormTemplate.clone(true,true);
				$.Dialog.request(title,$clone,false, function(){
					$clone.triggerHandler('added');
				});
			};

		if (typeof window.$ColorAvgFormTemplate === 'undefined'){
			$.Dialog.wait(title,'Loading form, please wait');
			let scriptUrl = '/js/min/global-color_avg_form.js';
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

	const $html = $('html');

	// Disappearing header when in standalone mode
	// Replace condition with "true" when needed for development
	if ($.isRunningStandalone()){
		let lastScrollTop = $html.scrollTop(),
			disappearingHeaderHandler = function(){
				if (!window.withinMobileBreakpoint() || $html.is(':animated'))
					return;

				let scrollTop = $html.scrollTop(),
					headerHeight = $header.outerHeight(),
					headerTop = parseInt($header.css('top'),10);

				$header.css('top',
					scrollTop > lastScrollTop
					 ? Math.max(-headerHeight,headerTop-(scrollTop-lastScrollTop))
					 : Math.min(0,headerTop+(lastScrollTop-scrollTop))
				);

				lastScrollTop = scrollTop;
			};
		$d.on('scroll',disappearingHeaderHandler);
		disappearingHeaderHandler();
	}
	const $toTheTop = $('#to-the-top').on('click',function(e){
		e.preventDefault();

		$html.stop().animate({scrollTop: 0}, 200);
		$toTheTop.removeClass('show');
	});
	function checkToTop(){
		if (!window.withinMobileBreakpoint() || $html.is(':animated'))
			return;

		const show = $html.scrollTop() !== 0;
		if (!show && $toTheTop.hasClass('show'))
			$toTheTop.removeClass('show');
		else if (show && !$toTheTop.hasClass('show'))
			$toTheTop.addClass('show');
	}
	$d.on('scroll',checkToTop);
	checkToTop();

	// Sign in button handler
	$.LocalStorage.remove('cookie_consent_v2');
	$('#signin').off('click').on('click',function(){
		let $this = $(this);
		$this.disable();

		let redirect = function(){
			$.Dialog.wait(false, 'Redirecting you to DeviantArt');
			$.Navigation.visit(`/da-auth/begin?return=${encodeURIComponent($.hrefToPath(location.href))}`);
		};

		if (navigator.userAgent.indexOf('Trident') !== -1)
			return redirect();

		$.Dialog.wait('Sign-in process', "Opening popup window");

		let success = false, closeCheck, popup, waitForIt = false;
		window.__authCallback = function(fail, openedWindow){
			clearInterval(closeCheck);
			if (fail === true){
				if (!openedWindow.jQuery)
					$.Dialog.fail(false, 'Sign in failed, check popup for details.');
				else {
					const
						pageTitle = openedWindow.$('#content').children('h1').html(),
						noticeText = openedWindow.$('#content').children('.notice').html();
					$.Dialog.fail(false, `<p class="align-center"><strong>${pageTitle}</strong></p><p>${noticeText}</p>`);
					popup.close();
				}
				$this.enable();
				return;
			}

			success = true;
			$.Dialog.success(false, 'Signed in successfully');
			popup.close();
			$.Navigation.reload(true);
		};
		try {
			popup = $.PopupOpenCenter('/da-auth/begin','login','450','580');
		}catch(e){}
		// http://stackoverflow.com/a/25643792
		let onWindowClosed = function(){
				if (success)
					return;

				if (document.cookie.indexOf('auth=') !== -1)
					return window.__authCallback;

				$.Dialog.fail(false, 'Popup-based login failed');
				redirect();
			};
		closeCheck = setInterval(function(){
			try {
				if (!popup || popup.closed){
					clearInterval(closeCheck);
					onWindowClosed();
				}
			}catch(e){}
		}, 500);
		$w.on('beforeunload', function(){
			success = true;
			if (!waitForIt)
				popup.close();
		});
		$.Dialog.wait(false, "Waiting for you to sign in");
	});

	// Sign out button handler
	$('#signout').off('click').on('click',function(){
		let title = 'Sign out';
		$.Dialog.confirm(title,'Are you sure you want to sign out?', function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out');

			$.API.post('/da-auth/sign-out',$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(title,this.message);

				$.Navigation.reload();
			}));
		});
	});


	let $sessionUpdating = $('#session-update-indicator');
	if ($sessionUpdating.length){
		const sessionRefTitle = 'Session refresh issue';
		const pollInterval = 1000;
		setTimeout(function poll(){
			if ($sessionUpdating === null)
				return;

			$.API.get('/da-auth/status', $.mkAjaxHandler(function(){
				if ($sessionUpdating === null)
					return;

				if (!this.status) return $.Dialog.fail(sessionRefTitle, this.message);

				if (this.updating === true)
					return setTimeout(poll, pollInterval);

				if (this.deleted === true)
					$.Dialog.fail(sessionRefTitle, "We couldn't refresh your DeviantArt session automatically so you have been signed out. Due to elements on the page assuming you are signed in some actions will not work as expected until the page is reloaded.");
				$('.logged-in').replaceWith(this.loggedIn);
			}));
		}, pollInterval);
	}

	if (!window.ServiceUnavailableError)
		$body.swipe($.throttle(10, function(direction, offset){
			if (window.sidebarForcedVisible() || !$body.hasClass('sidebar-open'))
				return;

			// noinspection JSSuspiciousNameCombination
			const
				offX = Math.abs(offset.x),
				offY = Math.abs(offset.y),
				minMove = Math.min($body.width()/2, 200);

			if (direction.x !== 'left' || offX < minMove || offY > 75)
				return;

			$sbToggle.trigger('click');
		}));
})();

// Remove loading animation from header on load
$w.on('load',function(){
	'use strict';
	$body.removeClass('loading');
});
