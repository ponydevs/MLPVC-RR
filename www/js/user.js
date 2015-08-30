DocReady.push(function User(){
	var $signoutBtn = $('#signout');
	$('.session-list').find('button.remove').on('click',function(e){
		e.preventDefault();

		var title = 'Sign out session',
			$btn = $(this),
			$li = $btn.closest('li'),
			browser = $btn.parent().text().trim();

		// First item is sometimes the current session, trigger logout button instead
		if ($li.index() === 0 && /\(current\)$/.test(browser))
			return $signoutBtn.trigger('click');

		var sid = parseInt($btn.attr('data-sid'));

		if (typeof sid === 'undefined' || isNaN(sid))
			return $.Dialog.fail(title,'Could not locate Session ID, please reload the page and try again.');

		$.Dialog.confirm(title,'This will invalidate the active session in the following browser: <em>'+browser+'</em><br>Are you sure?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out from '+browser);

			$.post('/user/sessiondel/'+sid, $.mkAjaxHandler(function(){
				if (this.status){
					$li.remove();
					$.Dialog.close();
				}
				else $.Dialog.fail(title,this.message);
			}));
		});
	});
	$('#signout-everywhere').on('click',function(){
		var title = 'Sign out from ALL sessions';

		$.Dialog.confirm(title,"This will invalidate ALL sessions. Continue?",function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out');

			$.post('/signout?everywhere',$.mkAjaxHandler(function(){
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
	$('#unlink').on('click',function(){
		var title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Removing account link');

			$.post('/signout?unlink', $.mkAjaxHandler(function(){
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
