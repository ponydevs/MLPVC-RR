(function($){
	// document.createElement shortcut
	$.mk = function(){ return $(document.createElement.apply(document,arguments)) };

	// Convert relative URL to absolute
	$.urlToAbsolute = function(url){
		var a = $.mk('a');
		a.href = url;
		return a.href;
	};

	// Create AJAX response handling function
	$(window).on('ajaxerror',function(){
		$.Dialog.fail(false,'There was an error while processing your request. You may find additional details in the browser\'s console.');
	});
	$.mkAjaxHandler = function(f){
		return function(data){
			if (typeof data !== 'object'){
				console.log(data);
				$(window).trigger('ajaxerror');
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
	$.ajaxPrefilter(function(e){
		var t = $.getCSRFToken();
		if (typeof e.data === "undefined")
			e.data = "";
		if (typeof e.data === "string"){
			var r = e.data.length > 0 ? e.data.split("&") : [];
			r.push("CSRF_TOKEN=" + t);
			e.data = r.join("&");
		}
		else e.data.CSRF_TOKEN = t;
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
})(jQuery);

$(function(){
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

	// Quotes
	var quotes = {
			Applejack: [
				"Alright, sugarcube.",
				"A good night's sleep cures just about everythin'!",
			],
			Fluttershy: [
				"<small>you rock! woo hoo!</small>",
				"<small>yay</small>",
				"<small>*squee*</small>",
				"You're sooo cute!",
				"<small>I'd like to be a tree</small>",
				"<strong>Your face!</strong>",
			],
			PinkiePie: [
				"It was under E!",
				"Make a wish!",
				"<em>Forever!</em>",
				"She's not a tree, Dashie!",
			],
			RainbowDash: "<strong>The</strong> one and only.",
			Rarity: "Gorgeous!",
			TwilightSparkle: [
				"Together, we're friends.",
				"A mark of one's destiny singled out alone, fulfilled.",
				"You know she's not a tree, right?",
				"Huh?! I'm pancake! I mean, awake...",
			],
			StarlightGlimmer: "We're so pleased to have you here!",
			Applebloom: [
				"Buy some apples?",
				"But ah want it naaow!",
			],
			BigMacintosh: "Eeyup",
			WinterWrapUp: "We must work so very hard, it's just so much to do!",
			MaudPie: "Rocks.",
			ScrewLoose: "woof woof",
			ButtonMash: [
				"I don't get it",
				"But it looks <strong>sooo</strong> kewl!"
			],
			TheGreatandPowerfulTrixie: [
				"It seems we have some NEIGH-sayers in the audience.",
				"The Great and Powerful Trixie doesn't trust wheels.",
			]
		}, $quote = $('#quote');
	function unCamelCase(str){
		str = str.replace(/(\w)and/g, '$1 and');
		return str.charAt(0)+str.substring(1).replace(/([A-Z])/g,' $1');
	}
	function random(arr){
		return arr[Math.floor(Math.random()*arr.length)]
	}
	function getQuote(){
		$quote.fadeTo(500,0,function(){
			var qs = $.extend(true,{},quotes),
				pony = $quote.data('pony');
			if (typeof pony !== 'undefined') delete qs[pony];
			var qskeys = Object.keys(qs);
			pony = random(qskeys);
			var ponyQuotes = qs[pony];
			$quote.data('index', pony).attr('data-cite', unCamelCase(pony));
			$quote.html(typeof ponyQuotes === 'string' ? ponyQuotes : random(ponyQuotes)).fadeTo(500,1);
		});
	}
	getQuote();
	var quoteInterval;
	$('#sidebar').on('mouseenter',function(){
		clearInterval(quoteInterval);
	}).on('mouseleave',function(){
		quoteInterval = setInterval(getQuote,11000);
	}).trigger('mouseleave');
});
