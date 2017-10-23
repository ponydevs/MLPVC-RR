/* globals DocReady,$sidebar,$content,HandleNav,Time,$w,$body */
$(function(){
	'use strict';

	$('.personal-cg-say-what').on('click',function(e){
		e.preventDefault();

		$.Dialog.info('About Personal Color Guides',
			`<p>We are forever grateful to our members who help others out by fulfilling their requests on our website. As a means of giving back, we're introducing Personal Color Guides. This is a place where you can store and share colors for any of your OCs, similar to our <a href="/cg/">Official Color Guide</a>.</p>
			<p><em>&ldquo;So where’s the catch?&rdquo;</em> &mdash; you might ask. Everyone starts with 0 slots*, which they can increase by fulfilling requests on our website, then submitting them to the club and getting them approved. You'll get your first slot after you've fulfilled 10 requests, all of which got approved by our staff to the club gallery. After that, you will be granted an additional slot for every 10 requests you finish and we approve.</p>
			<p><small>* Staff members get an honorary slot for free</small></p>
			<br>
			<p><strong>However</strong>, there are a few things to keep in mind:</p>
			<ul>
				<li>You may only add characters made by you, for you, or characters you've purchased to your Personal Color Guide. If we're asked to remove someone else’s character from your guide we'll certainly comply.</li>
				<li>Finished requests only count toward additional slots after they have been submitted to the group and have been accepted to the gallery. This is indicated by a tick symbol (<span class="color-green typcn typcn-tick"></span>) on the post throughout the site.</li>
				<li>A finished request does not count towards additional slots if you were the one who requested it in the first place. We're not against this behaviour generally, but allowing this would defeat the purpose of this feature: encouraging members to help others.</li>
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
		$pendingRes.on('click','button.fix',function(){
			let $btn = $(this),
				$link = $btn.next(),
				_id = $link.prop('hash').substring(1).split('-'),
				type = _id[0],
				id = _id[1],
				$ImgUpdateForm = $.mk('form').attr('id', 'img-update-form').append(
					$.mk('label').append(
						$.mk('span').text('New image URL'),
						$.mk('input').attr({
							type: 'text',
							maxlength: 255,
							pattern: "^.{2,255}$",
							name: 'image_url',
							required: true,
							autocomplete: 'off',
							spellcheck: 'false',
						})
					)
				);
			$.Dialog.request('Update image of '+type+' #'+id,$ImgUpdateForm,'Update', function($form){
				$form.on('submit', function(e){
					e.preventDefault();

					let data = $form.mkData();
					$.Dialog.wait(false, 'Replacing image');

					$.post(`/post/set-image/${type}/${id}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$.Dialog.success(false, 'Image has been updated');
						$.Navigation.reload(true);
					}));
				});
			});
		});
	}

	let $signoutBtn = $('#signout'),
		$sessionList = $('.session-list'),
		name = $content.children('.briefing').find('.username').text().trim(),
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

				$.Navigation.reload(true);
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

			$.post('/da-auth/signout?everywhere',{username:name},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Navigation.reload(true);
			}));
		});
	});
	$('#unlink').on('click',function(e){
		e.preventDefault();

		let title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?', function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Removing account link');

			$.post('/da-auth/signout?unlink', $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Navigation.reload(true);
			}));
		});
	});

	const fulfillPromises = function(){
		$('.post-deviation-promise:not(.loading)').each(function(){
			const $this = $(this);
			if (!$this.isInViewport())
				return;

			const
				postid = $this.attr('data-post').replace('-','/'),
				viewonly = $this.attr('data-viewonly');
			$this.addClass('loading');

			$.get(`/post/lazyload/${postid}`,{viewonly},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail('Cannot load '+postid.replace('/',' #'), this.message);

				$.loadImages(this.html).then(function($el){
					const $li = $this.closest('li[id]');
					$li.children('.image').replaceWith($el);
					const title = $li.children('.image').find('img').attr('alt');
					if (title)
						$li.children('.label').removeClass('hidden').find('a').text(title);
				});
			}));
		});
	};
	window._UserScroll = $.throttle(400, function(){
		fulfillPromises();
	});
	$w.on('scroll mousewheel',window._UserScroll);
	window._UserScroll();

	$('.awaiting-approval').on('click', 'button.check', function(e){
		e.preventDefault();

		let $li = $(this).parents('li'),
			IDArray = $li.attr('id').split('-'),
			thing = IDArray[0],
			id = IDArray[1];

		$.Dialog.wait('Deviation acceptance status', 'Checking');

		$.post(`/post/lock/${thing}/${id}`, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$li.remove();
			$.Dialog.success(false, this.message, true);
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
					return;
				}

				$.Navigation.reload(true);
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
			case "p_avatarprov":
				const forUser = {};
				$('.avatar-wrap.provider-'+from).each(function(){
					const
						$this = $(this),
						username = $this.attr('data-for');
					if (typeof forUser[username] === 'undefined')
						forUser[username] = [];
					forUser[username].push($this);
				});
				$(`.provider-${from}:not(.avatar-wrap)`).removeClass('provider-'+from).addClass('provider-'+to_what);
				let error = false;
				$.each(forUser, (username, elements) => {
					$.post('/user/avatar-wrap/'+username, $.mkAjaxHandler(function(){
						if (!this.status){
							error = true;
							return $.Dialog.fail('Update avatar elements for '+username, false);
						}

						$.each(elements, (_, $el) => {
							$el.replaceWith(this.html);
						});
					}));
				});
				if (!error)
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
				$.Navigation.reload();
			break;
			default:
				$.Dialog.close();
		}
	}

	const $knownIps = $('section.known-ips');

	$knownIps.on('click', 'button', function(){
		const $btn = $(this);
		$btn.disable();
		$.post(`/user/known-ips/${name}`, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail('Load full list of known IPs', this.message);

			$knownIps.replaceWith(this.html);
		})).fail(function(){
			$btn.enable();
		});
	});

	const
		$settings = $('#settings'),
		$slbl = $settings.find('form > label');

	$settings.on('submit','form', function(e){
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
	});
	$slbl.children('input[type=number], select').each(function(){
		let $el = $(this);
		$el.data('orig', $el.val().trim()).on('keydown keyup change',function(){
			let $el = $(this);
			$el.siblings('.save').attr('disabled', parseInt($el.val().trim(), 10) === $el.data('orig'));
		});
	});
	$slbl.children('input[type=checkbox]').each(function(){
		let $el = $(this);
		$el.data('orig', $el.prop('checked')).on('keydown keyup change',function(){
			let $el = $(this);
			$el.siblings('.save').attr('disabled', $el.prop('checked') === $el.data('orig'));
		});
	});
	$slbl.children('select').each(function(){
		let $el = $(this);
		$el.data('orig', $el.find('option:selected').val()).on('keydown keyup change',function(){
			let $el = $(this),
				$val = $el.find('option:selected');
			$el.siblings('.save').attr('disabled', $val.val() === $el.data('orig'));
		});
	});
});
