/* globals DocReady,$sidebar,$content,HandleNav */
DocReady.push(function User(){
	'use strict';

	(function rebind(){
		var $pendingRes = $('.pending-reservations');
		if ($pendingRes.length){
			$pendingRes.on('click','button.cancel',function(){
				var $btn = $(this),
					$link = $btn.prev();
				$.Dialog.confirm('Cancel reservation','Are you sure you want to cancel this reservation?',function(sure){
					if (!sure) return;

					$.Dialog.wait(false, 'Cancelling reservation');

					var id = $link.prop('hash').substring(1).split('-');
					$.post('/reserving/'+id[0]+'/'+id[1]+'?cancel',{FROM_PROFILE:true},$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						var pendingRes = this.pendingReservations;
						$btn.closest('li').fadeOut(1000,function(){
							$(this).remove();
							if (pendingRes){
								$pendingRes.replaceWith(pendingRes);
								window.updateTimes();
								rebind();
							}
						});
						$.Dialog.close();
					}));
				});
			});
		}
	})();

	var $signoutBtn = $('#signout'),
		$name = $content.children('h1'),
		$sessionList = $('.session-list'),
		name = $name.text().trim(),
		sameUser = name === $sidebar.children('.welcome').find('.un').text().trim();
	$sessionList.find('button.remove').off('click').on('click',function(e){
		e.preventDefault();

		var title = 'Deleting session',
			$btn = $(this),
			$li = $btn.closest('li'),
			browser = $li.children('.browser').text().trim(),
			$platform = $li.children('.platform'),
			platform = $platform.length ? ' on <em>'+$platform.children('strong').text().trim()+'</em>' : '';

		// First item is sometimes the current session, trigger logout button instead
		if ($li.index() === 0 && $li.children().last().text().indexOf('Current') !== -1)
			return $signoutBtn.triggerHandler('click');

		var SessionID = $li.attr('id').replace(/\D/g,'');

		if (typeof SessionID === 'undefined' || isNaN(SessionID) || !isFinite(SessionID))
			return $.Dialog.fail(title,'Could not locate Session ID, please reload the page and try again.');

		$.Dialog.confirm(title,(sameUser?'You':name)+' will be signed out of <em>'+browser+'</em>'+platform+'.<br>Continue?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Signing out of '+browser);

			$.post('/user/sessiondel/'+SessionID, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(title,this.message);

				if ($li.siblings().length !== 0){
					$li.remove();
					return $.Dialog.close();
				}

				$.Dialog.wait(false, 'Reloading page', true);
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			}));
		});
	});
	$sessionList.find('button.useragent').on('click',function(e){
		e.preventDefault();

		var $this = $(this);
		$.Dialog.info('User Agent string for session #'+$this.parents('li').attr('id').replace(/\D/g,''), '<code>'+$this.data('agent')+'</code>');
	});
	$('#signout-everywhere').on('click',function(){
		$.Dialog.confirm('Sign out from ALL sessions',"This will invalidate ALL sessions. Continue?",function(sure){
			if (!sure) return;

			$.Dialog.wait(false, 'Signing out');

			$.post('/signout?everywhere',{username:name},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.wait(false, 'Reloading page', true);
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			}));
		});
	});
	$('#unlink').on('click',function(){
		var title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Removing account link');

			$.post('/signout?unlink', $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.wait(false, 'Reloading page', true);
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			}));
		});
	});
	$('#awaiting-deviations').children('li').children(':last-child').children('button.check').on('click',function(e){
		e.preventDefault();

		var $li = $(this).parents('li'),
			IDArray = $li.attr('id').split('-'),
			thing = IDArray[0],
			id = IDArray[1];

		$.Dialog.wait('Deviation acceptance status','Checking');

		$.post('/reserving/'+thing+'s/'+id+'?lock',$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			var message = this.message;
			$.Dialog.wait(false, "Reloading page");
			$.Navigation.reload(function(){
				$.Dialog.success(false, message, true);
			});
		}));
	});
});
