(function($){
	'use strict';

	const
		showId = window.SHOW_ID,
		showType = window.SHOW_TYPE;

	$.highlightHash = function (e){
		$('.highlight').removeClass('highlight');

		let $anchor = $(location.hash);
		if (!$anchor.length)
			return false;
		$anchor.addClass('highlight');

		setTimeout(function(){
			$.scrollTo($anchor.offset().top - $navbar.outerHeight() - 10, 500, function(){
				if (typeof e === 'object' && e.type === 'load')
					$.Dialog.close();
			});
		}, 1);
	};
	$w.on('hashchange', $.highlightHash);

	let $voting = $('#voting');
	$voting.on('click','.rate', function(e){
		e.preventDefault();

		let makeStar = function(v){
				return $.mk('label').append(
					$.mk('input').attr({
						type: 'radio',
						name: 'vote',
						value: v,
					}),
					$.mk('span')
				).on('mouseenter mouseleave', function(e){
					let $this = $(this),
						$checked = $this.parent().find('input:checked'),
						$parent = $checked.parent(),
						$strongRating = $this.closest('div').next().children('strong');

					switch (e.type){
						case "mouseleave":
							if ($parent.length === 0){
								$this.siblings().addBack().find('.typcn').attr('class', '');
								$strongRating.text('?');
								break;
							}
							$this = $parent;
						/* falls through */
						case "mouseenter":
							$this.prevAll().addBack().children('span').attr('class','active');
							$this.nextAll().children('span').attr('class','');
							$strongRating.text($this.children('input').attr('value'));
						break;
					}

					$this.siblings().addBack().removeClass('selected');
					$parent.addClass('selected');
				});
			},
			$VoteForm = $.mk('form').attr('id','star-rating').append(
				$.mk('p').text("Rate the episode on a scale of 1 to 5. This cannot be changed later."),
				$.mk('div').attr('class','rate').append(
					makeStar(1),
					makeStar(2),
					makeStar(3),
					makeStar(4),
					makeStar(5)
				),
				$.mk('p').css('font-size','1.1em').append('Your rating: <strong>?</strong>/5')
			),
			$voteButton = $voting.children('.rate');

		$.Dialog.request(`Rate this ${showType}`,$VoteForm,'Rate', function($form){
			$form.on('submit', function(e){
				e.preventDefault();

				let data = $form.mkData();

				if (typeof data.vote === 'undefined')
					return $.Dialog.fail(false, 'Please choose a rating by clicking on one of the muffins');

				$.Dialog.wait(false, 'Submitting your rating');

				$.API.post(`/show/${showId}/vote`,data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					let $section = $voteButton.closest('section');
					$section.children('h2').nextAll().remove();
					$section.append(this.newhtml);
					$voting.bindDetails();
					$.Dialog.close();
				}));
			});
		});
	});

	$voting.find('time').data('dyntime-beforeupdate',function(diff){
		if (diff.past !== true) return;

		if (!$voting.children('.rate').length){
			$.API.get(`/show/${showId}/vote?html`,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail('Display voting buttons',this.message);

				$voting.children('h2').nextAll().remove();
				$voting.append(this.html);
				$voting.bindDetails();
			}));
			$(this).removeData('dyntime-beforeupdate');
			return false;
		}
	});

	$.fn.bindDetails = function(){
		$(this).find('a.detail').on('click', function(e){
			e.preventDefault();
			e.stopPropagation();

			$.Dialog.wait('Voting details','Getting vote distribution information');

			$.API.get(`/show/${showId}/vote`, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				const $ul = $.mk('ul');
				let totalVotes = 0;
				$.each(this.data, (label, value) => {
					const
						ms = +label === 1 ? '' : 's',
						vs = +value === 1 ? '' : 's';
					$ul.append(`<li><strong>${label} muffin${ms}:</strong> ${value} vote${vs}</li>`);
					totalVotes += +value;
				});
				const $bars = $.mk('div').attr('class','bars');
				$.each(this.data, (label, value) => {
					$bars.append(`<div class="bar type-${label}" style="width:${$.roundTo(100*(value/totalVotes), 2)}%"></div>`);
				});

				$.Dialog.info(false, [
					$.mk('p').text("Here's how the votes are distributed:"),
					$.mk('div').attr('id','vote-distrib').append($ul, $bars)
				]);
			}));
		});
	};
	$voting.bindDetails();

	$.getLiTypeId = function($li){
		return {
			id: parseInt($li.attr('id').split('-').pop(), 10),
			type: $li.attr('data-type'),
		};
	};
	$.fn.rebindFluidbox = function(){
		$(this).find('.screencap > a:not(.fluidbox--initialized)')
			.fluidboxThis();
	};
	$.fn.rebindHandlers = function(){
		this.closest('section').rebindFluidbox();
		return this;
	};
	const $posts = $('.posts');
	$posts
		.on('click','li[id] .share',function(e){
			e.preventDefault();

			const
				$button = $(this),
				$li = $button.closest('li'),
				{ id } = $.getLiTypeId($li),
				url = `${window.location.href.replace(/([^:/]\/).*$/,'$1')}s/${id.toString(36)}`,
				$div = $.mk('div').attr('class','align-center').append(
					'Use the link below to link to this post directly:',
					$.mk('div').attr('class','share-link').text(url),
					$.mk('button').attr('class','blue typcn typcn-clipboard').text('Copy to clipboard').on('click', function(e){
						$.copy(url,e);
					})
				);

			$.Dialog.info(`Sharing post #${id}`, $div, () => {
				$div.find('.share-link').select();
			});
		})
		.on('pls-update',function(_, callback, silent){
			let $section = $(this),
				kinds = $section.attr('id'),
				Kinds = $.capitalize(kinds);
			if (silent !== true)
				$.Dialog.wait($.Dialog.isOpen() ? false : Kinds, `Updating list of ${kinds}`, true);
			$.API.get(`/show/${showId}/posts`, {section:kinds}, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				let $newChildren = $(this.render).filter('section').children();
				$section.empty().append($newChildren).rebindHandlers();
				$section.find('.post-form').formBind();
				$section.find('h2 > button').enable();
				Time.update();
				$.highlightHash();
				if (typeof callback === 'function')
					callback();
				else if (silent !== true)
					$.Dialog.close();
			}));
		});
	$posts.find('li[id]').each(function(){
		$(this).rebindFluidbox();
	});

	$.fn.formBind = function (){
		let $form = $(this);
		if (!$form.length)
			return;

		const
			$formImgCheck = $form.find('.check-img'),
			$submitBtn = $form.find('button.submit'),
			$formImgPreview = $form.find('.img-preview'),
			$formDescInput = $form.find('input[name=label]'),
			$formImgInput = $form.find('input[name=image_url]'),
			$formLabelInput = $form.find('input[name=label]'),
			$notice = $formImgPreview.children('.notice'),
			noticeHTML = $notice.html(),
			kind = $form.attr('data-kind'),
			Kind = $.capitalize(kind);

		let $previewIMG = $formImgPreview.children('img');
		if ($previewIMG.length === 0)
			$previewIMG = $(new Image()).appendTo($formImgPreview);
		$(`#${kind}-btn`).on('click',function(){
			if ($form.hasClass('hidden'))
				$form.removeClass('hidden');

			$.scrollTo($form.offset().top - $navbar.outerHeight() - 10, 500, () => {
				$formDescInput.focus();
			});
		});
		if (kind === 'reservation') $('#add-reservation-btn').on('click',function(){
			let $AddReservationForm = $.mk('form','add-reservation').html(
				`<div class="notice info">This feature should only be used when the vector was made before the episode was displayed here, and you just want to link the finished vector under the newly posted episode OR if this was a request, but the original image (screencap) is no longer available, only the finished vector.</div>
				<div class="notice warn">If you already posted the reservation, use the <strong class="typcn typcn-attachment">I'm done</strong> button to mark it as finished instead of adding it here.</div>
				<label>
					<span>Deviation URL</span>
					<input type="text" name="deviation">
				</label>`
			);
			$.Dialog.request('Add a reservation',$AddReservationForm,'Finish', function(){
				$AddReservationForm.on('submit', function(e){
					e.preventDefault();

					let deviation = $AddReservationForm.find('[name=deviation]').val();

					if (typeof deviation !== 'string' || deviation.length === 0)
						return $.Dialog.fail(false, 'Please enter a deviation URL');

					$.Dialog.wait(false, 'Adding reservation');


					const data = {
						deviation,
						show_id: showId,
					};
					$.API.post('/post/reservation',data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$.Dialog.success(false, this.message);
						$form.closest('.posts').trigger('pls-update', [() => {
							$.Dialog.close();
							window.location.hash = `#${this.id}`;
						}]);
					}));
				});
			});
		});
		$formImgInput.on('keyup change paste',imgCheckDisabler);
		let outgoing =  /^https?:\/\/www\.deviantart\.com\/users\/outgoing\?/;
		function imgCheckDisabler(disable){
			let prevUrl = $formImgInput.data('prev-url'),
				sameValue = typeof prevUrl === 'string' && prevUrl.trim() === $formImgInput.val().trim();
			const checkDisabled = disable === true || sameValue;
			$formImgCheck.prop('disabled', checkDisabled);
			$submitBtn.prop('disabled', !checkDisabled);
			if (checkDisabled)
				$formImgCheck.attr('title', 'You need to change the URL before checking again.');
			else $formImgCheck.removeAttr('title');

			if (disable.type === 'keyup'){
				let val = $formImgInput.val();
				if (outgoing.test(val))
					$formImgInput.val($formImgInput.val().replace(outgoing,''));
			}
		}
		let CHECK_BTN = '<strong class="typcn typcn-arrow-repeat" style="display:inline-block">Check image</strong>';
		function checkImage(){
			let image_url = $formImgInput.val(),
				title = Kind+' process';

			$formImgCheck.removeClass('red');
			imgCheckDisabler(true);
			$.Dialog.wait(title,'Checking image, this can take a bit of time');

			$.API.post('/post/check-image', { image_url }, $.mkAjaxHandler(function(){
				let data = this;
				if (!data.status){
					$notice.children('p:not(.keep)').remove();
					$notice.prepend($.mk('p').attr('class','color-red').html(data.message)).show();
					$previewIMG.hide();
					$formImgCheck.enable();
					if (typeof $formImgInput.data('prev-url') === 'string')
						$submitBtn.enable();
					else $submitBtn.disable();
					return $.Dialog.close();
				}

				function load(data, attempts){
					$.Dialog.wait(title,'Checking image availability');

					$previewIMG.attr('src',data.preview).show().off('load error').on('load',function(){
						$notice.children('p:not(.keep)').remove();

						$formImgInput.data('prev-url', image_url);

						if (!!data.title && !$formLabelInput.val().trim())
							$.Dialog.confirm(
								'Confirm '+kind+' title',
								`The image you just checked had the following title:<br><br><p class="align-center"><strong>${data.title}</strong></p><br>Would you like to use this as the ${kind}'s description?<br>Keep in mind that it should describe the thing(s) ${kind === 'request' ? 'being requested' : 'you plan to vector'}.<p>This dialog will not appear if you give your ${kind} a description before clicking the ${CHECK_BTN} button.</p>`,
								function(sure){
									if (!sure) return $form.find('input[name=label]').focus();
									$formLabelInput.val(data.title);
									$.Dialog.close();
								}
							);
						else $.Dialog.close(function(){
							$form.find('input[name=label]').focus();
						});
					}).on('error',function(){
						if (attempts < 1){
							$.Dialog.wait("Can't load image",'Image could not be loaded, retrying in 2 seconds');
							setTimeout(function(){
								load(data, attempts+1);
							}, 2000);
							return;
						}
						$.Dialog.fail(title,"There was an error while attempting to load the image. Make sure the URL is correct and try again!");
						$formImgCheck.enable();
						if (typeof $formImgInput.data('prev-url') === 'string')
							$submitBtn.enable();
						else $submitBtn.disable();
					});
				}
				load(data, 0);
			}));
		}
		$formImgCheck.on('click', function(e){
			e.preventDefault();

			checkImage();
		});
		$form.on('submit',function(e, screwChanges, sanityCheck){
			e.preventDefault();
			let title = Kind+' process';

			if (typeof $formImgInput.data('prev-url') === 'undefined')
				return $.Dialog.fail(title, 'Please click the '+CHECK_BTN+' button before submitting your '+kind+'!');

			if (!screwChanges && $formImgInput.data('prev-url') !== $formImgInput.val())
				return $.Dialog.confirm(
					title,
					'You modified the image URL without clicking the '+CHECK_BTN+' button.<br>Do you want to continue with the last checked URL?',
					function(sure){
						if (!sure) return;

						$form.triggerHandler('submit',[true]);
					}
				);

			if (!sanityCheck && kind === 'request'){
				let label = $formDescInput.val(),
					$type = $form.find('select');

				if (label.indexOf('character') > -1 && $type.val() !== 'chr')
					return $.Dialog.confirm(title, `Your request label contains the word "character", but the request type isn't set to Character.<br>Are you sure you're not requesting one (or more) character(s)?`,['Let me change the type', 'Carry on'], function(sure){
						if (!sure) return $form.triggerHandler('submit',[screwChanges, true]);

						$.Dialog.close(function(){
							$type.focus();
						});
					});
			}

			let data = $form.mkData({
				kind: kind,
				show_id: showId,
				image_url: $formImgInput.data('prev-url'),
			});

			(function submit(){
				$.Dialog.wait(title,'Submitting post');

				$.API.post('/post',data,$.mkAjaxHandler(function(){
					if (!this.status){
						if (!this.canforce)
							return $.Dialog.fail(false, this.message);
						return $.Dialog.confirm(false, this.message, ['Go ahead','Never mind'], function(sure){
							if (!sure) return;

							data.allow_nonmember = true;
							submit();
						});
					}

					$.Dialog.success(false, Kind+' posted');

					const id = this.id;
					$(`#${kind}s`).trigger('pls-update', [function(){
						$.Dialog.close();
						$.Dialog.confirm(Kind+' posted','Would you like to view it or make another?',['View','Make another'],function(view){
							$.Dialog.close();

							if (view) return;

							$(`#${kind}-btn`).trigger('click');
						});
						window.location.hash = '#'+id;
					}]);
				}));
			})();
		}).on('reset',function(){
			$formImgCheck.prop('disabled', false).addClass('red');
			$notice.html(noticeHTML).show();
			$previewIMG.hide();
			$formImgInput.removeData('prev-url');
			$form.addClass('hidden');
		});
	};
	$posts.find('.post-form').each(function(){
		$(this).formBind();
	});

	const deviationIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			deviationIO.unobserve(el);

			const { postId, viewonly } = el.dataset;

			$.API.get(`/post/${postId}/lazyload`,{viewonly},$.mkAjaxHandler(function(){
				const $el = $(el);
				if (!this.status){
					$el.trigger('error');
					return $.Dialog.fail(`Cannot load post ${postId}`, this.message);
				}

				$.loadImages(this.html).then(function(resp){
					$el.trigger(resp.e).closest('.image').replaceWith(resp.$el);
				});
			}));
		});
	});
	const screencapIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			screencapIO.unobserve(el);

			const
				$link = $.mk('a'),
				image = new Image();
			image.src = el.dataset.src;
			$link.attr('href', el.dataset.href).append(image);

			const $el = $(el);
			$(image).on('load', function(e){
				$el.trigger(e).closest('.image').html($link);
				$link.closest('li').rebindFluidbox();
			}).on('error', () => {
				$el.closest('li').reloadLi();
			});
		});
	});
	const avatarIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			avatarIO.unobserve(el);

			const image = new Image();
			image.src = el.dataset.src;
			image.classList = 'avatar';
			const $el = $(el);
			$(image).on('load', function(e){
				$el.trigger(e).replaceWith(image);
			}).on('error', function(e){
				$el.trigger(e);
			});
		});
	});
	const synopsisIO = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (!entry.isIntersecting)
				return;

			const el = entry.target;
			synopsisIO.unobserve(el);

			const { id } = el.dataset;

			$.API.get(`/show/${id}/synopsis`,$.mkAjaxHandler(function(){
				const $section = $(el).closest('section');
				if (!this.status){
					$section.remove();
					return;
				}

				$.loadImages(this.html).then(function(resp){
					$section.html(resp.$el);
					$section.find('.synopsis-image').fluidboxThis();
				});
			}));
		});
	});

	$('.post-deviation-promise').each((_, el) => deviationIO.observe(el));
	$('.post-image-promise').each((_, el) => screencapIO.observe(el));
	$('.user-avatar-promise').each((_, el) => avatarIO.observe(el));
	$('.synopsis-promise').each((_, el) => synopsisIO.observe(el));

	if (window.linkedPostURL)
		history.replaceState({}, null, window.linkedPostURL);
	let postHashRegex = /^#post-\d+$/,
		showDialog = location.hash.length > 1 && postHashRegex.test(location.hash);

	directLinkHandler();

	let reloading = {};
	$.fn.reloadLi = function(log = true, callback = undefined){
		let $li = this,
			_idAttr = $li.attr('id');
		if (typeof _idAttr !== 'string' || $li.hasClass('admin-break'))
			return this;
		if (reloading[_idAttr] === true)
			return this;
		reloading[_idAttr] = true;

		let _idAttrArr = _idAttr.split('-'),
			id = _idAttrArr[1];

		if (log)
			console.log(`[POST-FIX] Attempting to reload post #${id}`);
		$.API.get(`/post/${id}/reload`,{cache:log},$.mkAjaxHandler(function(){
			reloading[_idAttr] = false;
			if (!this.status) return;
			if (this.broken === true){
				$li.remove();
				console.log(`[POST-FIX] Hid (broken) post #${id}`);
				return;
			}

			const $newli = $(this.li);
			$li = $('#'+$newli.attr('id'));
			$li.find('.fluidbox--opened').fluidbox('close');
			$li.find('.fluidbox--initialized').fluidbox('destroy');

			if ($li.hasClass('highlight') || $newli.is(location.hash))
				$newli.addClass('highlight');
			$li.replaceWith($newli);
			$newli.rebindFluidbox();
			Time.update();
			$newli.rebindHandlers(true);
			if (!$newli.parent().is(this.section))
				$newli.appendTo(this.section);
			$newli.parent().reorderPosts();

			if (log)
				console.log(`[POST-FIX] Reloaded post #${id}`);
			$.callCallback(callback);
		}));

		return this;
	};
	$.fn.reorderPosts = function(){
		let $parent = this;
		$parent.children().sort(function(a,b){
			const
				$a = $(a),
				$b = $(b),
				$aFinAt = $a.find('.finish-date time'),
				$bFinAt = $b.find('.finish-date time');
			let diff;
			if ($aFinAt.length && $bFinAt.length)
				diff = (new Date($aFinAt.attr('datetime'))).getTime() - (new Date($bFinAt.attr('datetime'))).getTime();
			else diff = (new Date($a.find('.post-date time').attr('datetime'))).getTime() - (new Date($b.find('.post-date time').attr('datetime'))).getTime();
			if (diff === 0)
				return parseInt($a.attr('id').replace(/\D/g,''), 10) - parseInt($b.attr('id').replace(/\\D/g,''), 10);
			return diff;
		}).appendTo($parent);
	};

	function directLinkHandler(){
		let found = $.highlightHash({type:'load'});
		if (found === false && showDialog){
			const title = 'Scroll post into view';
			// Attempt to find the post as a last resort, it might be on a different episode page
			const postID = location.hash.replace(/\D/g,'');
			$.API.post(`/post/${postID}/locate`, { show_id: showId }, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.info(title, this.message);

				if (this.refresh){
					$(`#${this.refresh}s`).triggerHandler('pls-update');
					return;
				}

				const castle = this.castle;

				const $contents =
					$(`<p>Looks like the post you were linked to is in another castle. Want to follow the path?</p>
					<div id="post-road-sign">
						<div class="sign-wrap">
							<div class="sign-inner">
								<span class="sign-text"/>
								<span class="sign-arrow">\u2794</span>
							</div>
						</div>
						<div class="sign-pole"></div>
					</div>
					<div class="notice info">If you're seeing this message after clicking a link within the site please <a class="send-feedback">let us know</a>.</div>`);

				$contents.find('.sign-text').text(castle.name);

				$.Dialog.close(function(){
					$.Dialog.confirm(title, $contents, ['Take me there','Stay here'], sure => {
						if (!sure) return;

						$.Dialog.wait(false, 'Quicksaving');

						$.Navigation.visit(castle.url);
					});
				});
			}));
		}
	}

	function bindVideoButtons(){
		let $embedWrap,
			$episode = $('.episode'),
			$showPlayers = $episode.find('.showplayers').on('scroll-video-into-view',function(){
				let hh = $header.outerHeight();
				$.scrollTo($embedWrap.offset().top - (($w.height() - $footer.outerHeight() - hh - $embedWrap.outerHeight()) / 2) - hh, 500);
			}),
			$playerActions = $showPlayers.parent(),
			$partSwitch;
		if ($showPlayers.length){
			let $reportBroken = $episode.find('.reportbroken');
			$showPlayers.on('click', function(e){
				e.preventDefault();

				if (typeof $embedWrap === 'undefined'){
					$.Dialog.wait($showPlayers.text());

					$.API.get(`/show/${showId}/video-embeds`, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (this.parts === 2){
							$partSwitch = $.mk('button').attr('class','blue typcn typcn-media-fast-forward').text('Part 2').on('click',function(){
								$(this).toggleHtml(['Part 1', 'Part 2']);
								$embedWrap.children().toggleClass('hidden');
							});
							$playerActions.append($partSwitch);
						}
						$embedWrap = $.mk('div').attr('class','resp-embed-wrap').html(this.html).insertAfter($playerActions);
						$showPlayers
							.removeClass('typcn-eye green')
							.addClass('typcn-eye-outline blue')
							.text('Hide on-site player')
							.triggerHandler('scroll-video-into-view');
						$.Dialog.close();
					}));
				}
				else {
					let show = $showPlayers.hasClass('typcn-eye');
					$embedWrap[show?'show':'hide']();
					if ($partSwitch instanceof jQuery)
						$partSwitch.prop('disabled', !show);
					$showPlayers.toggleClass('typcn-eye typcn-eye-outline').toggleHtml(['Show on-site player','Hide on-site player']);

					if (show)
						$showPlayers.triggerHandler('scroll-video-into-view');
				}
			});
			$reportBroken.on('click',function(e){
				e.preventDefault();

				$.Dialog.confirm('Report broken video',
					'<p>Have any of the linked videos been removed from their respective platform?<p>'+
					'<p>Please note that availability checking is automatic, bad video quality or sound issues cannot be detected this way. You should <a class="send-feedback">tell us</a> directly if that is the case.</p>'+
					'<p>Due to technical difficulties <strong>broken Mega links cannot be detected automatically</strong>, please <a class="send-feedback">contact us</a> if you find a broken Mega link.</p>',
				['Send report','Never mind'],function(sure){
					if (!sure) return;

					$.Dialog.wait(false, 'Sending report');

					$.API.post(`/show/${showId}/broken-videos`, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (typeof this.epsection !== 'undefined'){
							if (this.epsection.length > 0){
								$episode.html(this.epsection);
								bindVideoButtons();
							}
							else $episode.remove();
						}

						$.Dialog.success(false, this.message, true);
					}));
				});
			});
		}
	}
	window.bindVideoButtons = bindVideoButtons;
	bindVideoButtons();

	$.WS.recvPostUpdates(true);
})(jQuery);
