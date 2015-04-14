$(function(){
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