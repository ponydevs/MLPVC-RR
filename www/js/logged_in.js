$(function(){
	$('#signout').on('click',function(){
		var title = 'Sign out';
		$.Dialog.confirm(title,'Are you sure you want to sign out?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out');

			$.post('/signout',$.mkAjaxHandler(function(){
				if (this.status){
					$.Dialog.success(title,this.message);
					setTimeout(function(){
						window.location.reload();
					},1000);
				}
				else $.Dialog.fail(title,this.message);
			}));
		});
	});
});
