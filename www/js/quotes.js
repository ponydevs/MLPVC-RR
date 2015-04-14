$(function(){
	var $w = $(window),
		quotes = {
			Applejack: "Alright, sugarcube.",
			Fluttershy: [
				"<small>you rock! woo hoo!</small>",
				"<small>yay</small>",
				"<small>*squee*</small>",
				"You're sooo cute!",
				"I'd like to be a tree,"
			],
			PinkiePie: [
				"It was under E!",
				"Make a wish!",
			],
			RainbowDash: "<strong>The</strong> one and only.",
			Rarity: "Gorgeous!",
			TwilightSparkle: [
				"Together, we're friends.",
				"A mark of one's destiny singled out alone, fulfilled.",
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
		}, $quote = $('#quote');
	function unCamelCase(str){
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