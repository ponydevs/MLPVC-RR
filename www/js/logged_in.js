$(function(){
	var $w = $(window),
		quotes = [
		"We're so pleased to have you here!",
		"Thanks for stopping by!",
		"<small>you rock! woo hoo!</small>",
		"<small>yay</small>",
		"You're sooo cute!",
		"Hey there!",
		"Buy some apples?",
		"Hold onto your hats!",
		"Together, we're friends.",
		"Partly cloudy with a chance of frog."
	], $quote = $('main .sidebar > .welcome em');
	function getQuote(){
		$quote.fadeTo(500,0,function(){
			var qs = quotes.slice(),
				current = $quote.data('index');
			if (typeof current !== 'undefined') qs.splice(current,1);
			current = parseInt(Math.random()*qs.length);
			$quote.data('index', current);
			$quote.html(qs[current]).fadeTo(500,1);
		});
	}
	setInterval(getQuote,11000);
	getQuote();

	$('#signout').on('click',function(){
		var title = 'Sign out';
		$.Dialog.confirm(title,'Are you sure you want to sign out?',function(sure){
			if (sure) signout(title);
		});
	});
	$('#unlink').on('click',function(){
		var title = 'Unlink account & sign out';
		$.Dialog.confirm(title,"By unlinking your account you revoke this site's access to your account information.<br>The next time you want to log in, you'll have to link your account to the site again.<br>This will not remove any of your data from this site.<br><br>Are you sure you want to unlink your account?",function(sure){
			if (sure) signout(title, true);
		});
	});

	function signout(title, unlink){
		$.Dialog.wait(title,'Sending '+(unlink===true?'unlink':'sign out')+' request');

		$.ajax({
			method: "POST",
			url: "/signout"+(unlink===true?'?unlink':''),
			success: function(data){
				if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

				if (data.status){
					$.Dialog.success(title,data.message);
					setTimeout(function(){
						window.location.reload();
					},1000);
				}
				else $.Dialog.fail(title,data.message);
			}
		})
	}
});