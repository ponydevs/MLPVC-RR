$(function(){
	// Countdown
	var $cd, cdtimer,
		months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	window.setCD = function(){
		var $uc = $('#upcoming');
		$cd = $uc.find('li').first().find('.countdown');
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
		var airs = new Date($cd.data('airs')),
			diff = window.getTimeDiff(new Date, airs);
		if (diff.past === true || $cd.length === 0){
			if ($cd.length > 0){
				var $oldcd = $cd,
					$nextime = $oldcd.parents('li').next().find('time');
				$oldcd.parents('li').remove();
			}
			clearInterval(cdtimer);
			if ($cd.length === 0 || $nextime.length === 0) return $('#upcoming').remove();
			$(document.createElement('span')).addClass('countdown').data('airs', $nextime.attr('datetime')).insertAfter($nextime);
			$nextime.remove();
			return window.setCD();
		}
		var text = 'in ';
		if (diff.day > 0)
			text += diff.day+' day'+(diff.day!==1?'s':'')+' & ';
		if (diff.hour > 0)
			text += diff.hour+':';
		$cd.text(text+pad(diff.minute)+':'+pad(diff.second));
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