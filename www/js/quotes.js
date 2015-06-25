$(function(){
	var quotes, $quote = $('#quote');

	function random(arr){
		return arr[Math.floor(Math.random()*arr.length)]
	}
	$.post('/quotes/json',{},function(data){
		if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

		if (!data.status) return $.Dialog.fail(data.message);

		quotes = data.quotes;

		function getQuote(init){
			var go = function(){
				var qs = $.extend(true,{},quotes),
					pony = $quote.data('pony');
				if (typeof pony !== 'undefined') delete qs[pony];
				var qskeys = Object.keys(qs);
				pony = random(qskeys);
				var ponyQuotes = qs[pony];
				$quote
					.data('index', pony).attr('data-cite', pony)
					.html(typeof ponyQuotes === 'string' ? ponyQuotes : random(ponyQuotes))
					.fadeTo(500,1);
			};
			if (init !== true) $quote.fadeTo(500,0,go);
			else {
				go();
				$quote.stop().css('opacity', 1);
			}
		}
		getQuote(true);
		var quoteInterval;
		$('#sidebar').on('mouseenter',function(){
			clearInterval(quoteInterval);
		}).on('mouseleave',function(){
			quoteInterval = setInterval(getQuote,11000);
		}).trigger('mouseleave');
	});
});