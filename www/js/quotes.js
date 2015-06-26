$(function(){
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