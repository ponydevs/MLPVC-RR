/* globals DocReady,$sidebar,$content,HandleNav,Time */
DocReady.push(function(){
	'use strict';

	$('.personal-cg-say-what').on('click',function(e){
		e.preventDefault();

		$.Dialog.info('About Personal Color Guides',
			`<p>We are forever grateful to our members who help others out by fulfilling their requests on our website. As a means of giving back, we're introducing Personal Color Guides. This is a place where you can store and share colors for any of your OCs, similar to our <a href="/cg/">Official Color Guide</a>.</p>
			<p><em>&ldquo;So where's the catch?&rdquo;</em> &mdash; you might ask. Everyone starts with 0 slots*, which they can increase by fulfilling requests on our website, then submitting them to the club and getting them approved. You'll get your first slot after you've fulfilled 10 requests, all of which got approved by our staff to the club gallery. After that, you will be granted an additional slot for every 10 requests you finish and we approve.</p>
			<p><small>* Staff members get an honorary slot for free</small></p>
			<br>
			<p><strong>However</strong>, there are a few things to keep in mind:</p>
			<ul>
				<li>You may only add characters made by you, for you, or characters you've purchased to your Personal Color Guide. If we're asked to remove someone else's character from your guide we'll certainly comply.</li>
				<li>Finished requests only count toward additional slots after they have been submitted to the group and have been accepted to the gallery. This is indicated by a tick symbol (<span class="color-green typcn typcn-tick"></span>) on the post throughout the site.</li>
				<li>Do not attempt to abuse the system in any way. Exploiting any bugs you may encounter instead of <a class="send-feedback">reporting them</a> will be sanctioned.</li>
			</ul>`
		);
	});

	let $pendingRes = $('.pending-reservations');
	if ($pendingRes.length){
		$pendingRes.on('click','button.cancel',function(){
			let $btn = $(this),
				$link = $btn.prev();
			$.Dialog.confirm('Cancel reservation','Are you sure you want to cancel this reservation?', function(sure){
				if (!sure) return;

				$.Dialog.wait(false, 'Cancelling reservation');

				let id = $link.prop('hash').substring(1).split('-');
				$.post(`/post/unreserve/${id.join('/')}`,{FROM_PROFILE:true},$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					let pendingRes = this.pendingReservations;
					$btn.closest('li').fadeOut(1000,function(){
						$(this).remove();
						if (pendingRes){
							$pendingRes.html($(pendingRes).children());
							Time.Update();
						}
					});
					$.Dialog.close();
				}));
			});
		});
	}

	let $signoutBtn = $('#signout'),
		$sessionList = $('.session-list'),
		name = $content.find('.title .username').text().trim(),
		sameUser = name === $sidebar.children('.welcome').find('.un').text().trim();
	$sessionList.find('button.remove').off('click').on('click', function(e){
		e.preventDefault();

		let title = 'Deleting session',
			$btn = $(this),
			$li = $btn.closest('li'),
			browser = $li.children('.browser').text().trim(),
			$platform = $li.children('.platform'),
			platform = $platform.length ? ` on <em>${$platform.children('strong').text().trim()}</em>` : '';

		// First item is sometimes the current session, trigger logout button instead
		if ($li.index() === 0 && $li.children().last().text().indexOf('Current') !== -1)
			return $signoutBtn.triggerHandler('click');

		let SessionID = $li.attr('id').replace(/\D/g,'');

		if (typeof SessionID === 'undefined' || isNaN(SessionID) || !isFinite(SessionID))
			return $.Dialog.fail(title,'Could not locate Session ID, please reload the page and try again.');

		$.Dialog.confirm(title,`${sameUser?'You':name} will be signed out of <em>${browser}</em>${platform}.<br>Continue?`, function(sure){
			if (!sure) return;

			$.Dialog.wait(title,`Signing out of ${browser}${platform}`);

			$.post(`/user/sessiondel/${SessionID}`, $.mkAjaxHandler(function(){
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
	$sessionList.find('button.useragent').on('click', function(e){
		e.preventDefault();

		let $this = $(this);
		$.Dialog.info(`User Agent string for session #${$this.parents('li').attr('id').substring(8)}`, `<code>${$this.data('agent')}</code>`);
	});
	$('#signout-everywhere').on('click',function(){
		$.Dialog.confirm('Sign out from ALL sessions',"This will invalidate ALL sessions. Continue?", function(sure){
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
	$('#discord-verify').on('click',function(e){
		e.preventDefault();

		$.Dialog.wait('Verify identity on Discord','Getting your token');

		$.post('/user/discord-verify',$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			let command = `/verify ${this.token}`,
				$out = $.mk('div').attr('class','align-center').append(
					'Run the following command in any of the channels:',
					$.mk('div').attr('class', 'disc-verify-code').html(`<code>${command}</code>`).on('mousedown',function(e){
						e.preventDefault();

						$(this).select();
					}),
					$.mk('button').attr('class','darkblue typcn typcn-clipboard').text('Copy command to clipboard').on('click',function(e){
						e.preventDefault();

						$.copy(command, e);
					})
				);
			$.Dialog.info(false, $out);
		}));
	});
	$('#unlink').on('click',function(e){
		e.preventDefault();

		let title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?', function(sure){
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
	$('#awaiting-deviations').children('li').children(':last-child').children('button.check').on('click', function(e){
		e.preventDefault();

		let $li = $(this).parents('li'),
			IDArray = $li.attr('id').split('-'),
			thing = IDArray[0],
			id = IDArray[1];

		$.Dialog.wait('Deviation acceptance status','Checking');

		$.post(`/post/lock-${thing}/${id}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			let message = this.message;
			$.Dialog.wait(false, "Reloading page");
			$.Navigation.reload(function(){
				$.Dialog.success(false, message, true);
			});
		}));
	});

	function settingChanged(which,from,to_what){
		switch (which){
			case "p_vectorapp":
				if (to_what.length === 0 && from.length !== 0){
					let className = `app-${from}`;
					$(`.${className}`).removeClass(className);
					$('.title h1 .vectorapp-logo').remove();
					$.Dialog.close();
				}
				else {
					$.Dialog.wait(false,'Reloading page');
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}
			break;
			case "p_hidediscord":
				let $discordBtn = $sidebar.find('.welcome .discord-join');
				if (to_what){
					if ($discordBtn.length)
						$discordBtn.remove();
				}
				else if (!$discordBtn.length)
					$sidebar.find('.welcome .buttons').append('<a class="btn typcn discord-join" href="http://fav.me/d9zt1wv" target="_blank">Join Discord</a>');
				$.Dialog.close();
			break;
			case "p_disable_ga":
				if (to_what){
					$.Dialog.wait(false, 'Performing a hard reload to remove user ID from the tracking code');
					return window.location.reload();
				}
				$.Dialog.close();
			break;
			case "p_hidepcg":
				$.Dialog.wait('Navigation','Reloading page');
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			break;
			default:
				$.Dialog.close();
		}
	}

	let $slbl = $('#settings').find('form').on('submit', function(e){
		e.preventDefault();

		let $form = $(this),
			endpoint = $form.attr('action'),
			data = $form.mkData(),
			$input = $form.find('[name="value"]'),
			orig = $input.data('orig');

		$.Dialog.wait('Saving setting','Please wait');

		$.post(endpoint,data,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);
			
			if ($input.is('[type=number]'))
				$input.val(this.value);
			else if ($input.is('[type=checkbox]')){
				this.value = Boolean(this.value);
				$input.prop('checked', this.value);
			}
			$input.data('orig', this.value).triggerHandler('change');

			settingChanged(endpoint.split('/').pop(), orig, this.value);
		}));
	}).children('label');
	$slbl.children('input[type=number], select').each(function(){
		let $el = $(this);
		$el.data('orig', $el.val().trim()).on('keydown keyup change',function(){
			let $el = $(this);
			$el.siblings('.save').attr('disabled', $el.val().trim() === $el.data('orig'));
		});
	});
	$slbl.children('input[type=checkbox]').each(function(){
		let $el = $(this);
		$el.data('orig', $el.prop('checked')).on('keydown keyup change',function(){
			let $el = $(this);
			$el.siblings('.save').attr('disabled', $el.prop('checked') === $el.data('orig'));
		});
	});
/*	$slbl.children('select').each(function(){
		let $el = $(this);
		$el.data('orig', $el.find('option:selected').val()).on('keydown keyup change',function(){
			let $el = $(this),
				$val = $el.find('option:selected');
			console.log($val[0], $el.data('orig'));
			$el.siblings('.save').attr('disabled', $val.val() === $el.data('orig'));
		});
	});*/
});
