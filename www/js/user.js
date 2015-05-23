$(function(){
	var $signoutBtn = $('#signout');
	$('.session-list').find('button.remove').on('click',function(e){
		e.preventDefault();

		var title = 'Sign out session',
			$btn = $(this),
			$li = $btn.closest('li');

		// First item is always the current session, trigger logout buttons
		if ($li.index() === 0)
			return $signoutBtn.trigger('click');

		var browser = $btn.parent().text().trim(),
			sid = parseInt($btn.attr('data-sid'));

		if (typeof sid === 'undefined' || isNaN(sid))
			return $.Dialog.fail(title,'Could not locate Session ID, please reload the page and try again.');

		$.Dialog.confirm(title,'This will invalidate the active session in the following browser: <em>'+browser+'</em><br>Are you sure?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out from '+browser);

			$.ajax({
				method: "POST",
				url: '/user/sessiondel/'+sid,
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$li.remove();
						$.Dialog.close();
					}
					else $.Dialog.fail(title,data.message);
				}
			});
		});
	});
	$('#signout-everywhere').on('click',function(){
		var title = 'Sign out from ALL sessions';

		$.Dialog.confirm(title,"This will invalidate ALL sessions.<br>Continue?",function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out');

			$.ajax({
				method: "POST",
				url: '/signout?everywhere',
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
			});
		});
	});
	$('#unlink').on('click',function(){
		var title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Removing account link');

			$.ajax({
				method: "POST",
				url: '/signout?unlink',
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
			});
		});
	});
});
