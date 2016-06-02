/* global DocReady,$content,$body,$w,$footer,$header,$navbar,moment,Chart */
DocReady.push(function Episode(){
	'use strict';
	var SEASON = window.SEASON,
		EPISODE = window.EPISODE,
		USERNAME_REGEX = window.USERNAME_REGEX,
		FULLSIZE_MATCH_REGEX = window.FULLSIZE_MATCH_REGEX,
		EpID = 'S'+SEASON+'E'+EPISODE,
		$epSection = $content.children('section.episode');

	$('#video').on('click',function(){
		$.Dialog.wait('Set video links', 'Requesting links from the server');

		$.post('/episode/getvideos/'+EpID,$.mkAjaxHandler(function(){
			var data = this;

			if (!data.status) return $.Dialog.fail(false, data.message);

			var $yt_input = $.mk('input').attr({type:'url','class':'yt',name:'yt_1',placeholder:'YouTube',spellcheck:'false',autocomplete:'off'}),
				$dm_input = $.mk('input').attr({type:'url','class':'dm',name:'dm_1',placeholder:'Dailymotion',spellcheck:'false',autocomplete:'off'}),
				$form = $.mk('form').attr('id','vidlinks').attr('class','align-center').append(
					$.mk('p').text('Enter vido links below, leave any input blank to remove that video from the episode page.'),
					$yt_input.clone(),
					$dm_input.clone()
				);
			if (data.twoparter){
				$.mk('p').html('<strong>~ Part 1 ~</strong>').insertBefore($form.children('input').first());
				var pt2 = {
					$yt: $yt_input.clone().attr('name', 'yt_2'),
					$dm: $dm_input.clone().attr('name', 'dm_2')
				};
				$form.append(
					$.mk('p').text('Check below if either link contains the full episode instead of just one part'),
					$.mk('div').append(
						"<label><input type='checkbox' name='yt_1_full'> YouTube</label> &nbsp; "+
						"<label><input type='checkbox' name='dm_1_full'> Dailymotion</label>"
					),
					$.mk('p').html('<strong>~ Part 2 ~</strong>'),
					pt2.$yt,
					pt2.$dm
				);
				$form.find('input[type="checkbox"]').on('change',function(){
					pt2['$'+($(this).attr('name').replace(/^([a-z]+)_.*$/,'$1'))].attr('disabled', this.checked);
				});
				if (data.fullep.length > 0)
					$.each(data.fullep,function(_,prov){
						$form
							.find('input[type="checkbox"]')
							.filter('[name="'+prov+'_1_full"]')
							.prop('checked', true)
							.trigger('change');
					});
			}
			if (Object.keys(data.vidlinks).length > 0){
				var $inputs = $form.children('input');
				$.each(data.vidlinks,function(k,v){
					$inputs.filter('[name='+k+']').val(v);
				});
			}
			$.Dialog.request(false,$form,'vidlinks','Save',function($form){
				if (data.airs && new Date(data.airs).getTime() > new Date().getTime()){
					var $lsnotice = $.mk('div').addClass('notice warn').text('If you add this video now, it will be shown as a livestream link!');
					$form.append($lsnotice);

					$form.on('change keydown','input',function(){
						setTimeout(function(){
							var state = $form.mkData(),
								shownotice = state.yt_1 && state.yt_1_full && !(state.dm_1 || state.dm_2);

							$lsnotice[shownotice ? 'show' : 'hide']();
						},1);
					}).triggerHandler('change');
				}
				$form.on('submit',function(e){
					e.preventDefault();

					var data = $form.mkData();
					$.Dialog.wait(false, 'Saving links');
					
					$.post('/episode/setvideos/'+EpID,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (this.epsection){
							if (!$epSection.length)
								$epSection = $.mk('section')
									.addClass('episode')
									.insertBefore($content.children('section').first());
							$epSection.html($(this.epsection).filter('section').html());
							BindVideoButtons();
						}
						else if ($epSection.length){
							$epSection.remove();
							$epSection = {length:0};
						}
						$.Dialog.close();
					}));
				});
			});
		}));
	});

	var $cgRelations = $content.children('section.appearances');
	$('#cg-relations').on('click',function(){
		$.Dialog.wait('Guide relation editor', 'Retrieving relations from server');

		$.post('/episode/getcgrelations/'+EpID,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			var data = this,
				$form = $.mk('form').attr('id','guide-relation-editor'),
				$selectLinked = $.mk('select').attr({name:'listed',multiple:true}),
				$selectUnlinked = $.mk('select').attr('multiple', true);

			if (data.linked && data.linked.length)
				$.each(data.linked,function(_, el){
					$selectLinked.append($.mk('option').attr('value', el.id).text(el.label));
				});
			if (data.unlinked && data.unlinked.length)
				$.each(data.unlinked,function(_, el){
					$selectUnlinked.append($.mk('option').attr('value', el.id).text(el.label));
				});

			$form.append(
				$.mk('div').attr('class','split-select-wrap').append(
					$.mk('div').attr('class','split-select').append("<span>Linked</span>",$selectLinked),
					$.mk('div').attr('class','buttons').append(
						$.mk('button').attr({'class':'typcn typcn-chevron-left green',title:'Link selected'}).on('click',function(e){
							e.preventDefault();

							$selectLinked.append($selectUnlinked.children(':selected').prop('selected', false)).children().sort(function(a,b){
								return a.innerHTML.localeCompare(b.innerHTML);
							}).appendTo($selectLinked);
						}),
						$.mk('button').attr({'class':'typcn typcn-chevron-right red',title:'Unlink selected'}).on('click',function(e){
							e.preventDefault();

							$selectUnlinked.append($selectLinked.children(':selected').prop('selected', false)).children().sort(function(a,b){
								return a.innerHTML.localeCompare(b.innerHTML);
							}).appendTo($selectUnlinked);
						})
					),
					$.mk('div').attr('class','split-select').append("<span>Available</span>",$selectUnlinked)
				)
			);

			$.Dialog.request(false,$form,'guide-relation-editor','Save',function($form){
				$form.on('submit',function(e){
					e.preventDefault();

					var ids = [];
					$selectLinked.children().each(function(_, el){ ids.push(el.value) });
					$.Dialog.wait(false, 'Saving changes');

					$.post('/episode/setcgrelations/'+EpID,{ids:ids.join(',')},$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (this.section){
							if (!$cgRelations.length)
								$cgRelations = $.mk('section')
									.addClass('appearances')
									.insertBefore($content.children('.admin'));
							$cgRelations.html($(this.section).filter('section').html());
						}
						else if ($cgRelations.length){
							$cgRelations.remove();
							$cgRelations = {length:0};
						}
						$.Dialog.close();
					}));
				});
			});
		}));
	});

	$('#edit-about_reservations, #edit-reservation_rules').on('click',function(e){
		e.preventDefault();

		var $h2 = $(this).parent(),
			$h2c = $h2.clone(),
			endpoint = this.id.replace(/^.*-/, '');
		$h2c.children().remove();
		var text = $h2c.text().trim();

		$.Dialog.wait('Editing "'+text+'"',"Retrieving setting's value");
		$.post('/setting/get/'+endpoint,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			var $Form = $.mk('form').attr('id', endpoint+'-editor').append(
				$.mk('label').append(
					$.mk('span').text(text),
					$.mk('textarea').attr({
						name: 'value',
					}).val(this.value)
				)
			);

			$.Dialog.request(false, $Form, $Form.attr('id'), 'Save', function($form){
				$form.on('submit', function(e){
					e.preventDefault();

					var data = $form.mkData();
					$.Dialog.wait(false, 'Saving');

					$.post('/setting/set/'+endpoint, data, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$h2.siblings().remove();
						$h2.parent().append(this.value);
						$.Dialog.close();
					}));
				});
			});
		}));
	});

	function BindVideoButtons(){
		var $showPlayers = $('.episode').find('.showplayers').on('scroll-video-into-view',function(){
				var hh = $header.outerHeight();
				$.scrollTo($embedWrap.offset().top - (($w.height() - $footer.outerHeight() - hh - $embedWrap.outerHeight()) / 2) - hh, 500);
			}),
			$playerActions = $showPlayers.parent(),
			$partSwitch,
			$embedWrap;
		if ($showPlayers.length){
			$showPlayers.on('click',function(e){
				e.preventDefault();

				if (typeof $embedWrap === 'undefined'){
					$.Dialog.wait($showPlayers.text());

					$.post('/episode/videos/'+EpID, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (this[0] === 2){
							$partSwitch = $.mk('button').attr('class','blue typcn typcn-media-fast-forward').text('Part 2').on('click',function(){
								$(this).toggleHtml(['Part 1', 'Part 2']);
								$embedWrap.children().toggle();
							});
							$playerActions.append($partSwitch);
						}
						$embedWrap = $.mk('div').attr('class','resp-embed-wrap').html(this[1]).insertAfter($playerActions);
						$showPlayers
							.removeClass('typcn-eye green')
							.addClass('typcn-eye-outline blue')
							.text('Hide on-site player')
							.triggerHandler('scroll-video-into-view');
						$.Dialog.close();
					}));
				}
				else {
					var show = $showPlayers.hasClass('typcn-eye');
					$embedWrap[show?'show':'hide']();
					if ($partSwitch instanceof jQuery)
						$partSwitch.attr('disabled', !show);
					$showPlayers.toggleClass('typcn-eye typcn-eye-outline').toggleHtml(['Show on-site player','Hide on-site player']);

					if (show)
						$showPlayers.triggerHandler('scroll-video-into-view');
				}
			});
		}
	}
	BindVideoButtons();

	var $voting = $('#voting');
	$voting.on('click','.rate',function(e){
		e.preventDefault();

		var makeStar = function(v){
				return $.mk('label').append(
					$.mk('input').attr({
						type: 'radio',
						name: 'vote',
						value: v,
					}),
					$.mk('span')
				).on('mouseenter mouseleave',function(e){
					var $this = $(this),
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

		$.Dialog.request('Rating '+EpID,$VoteForm,'star-rating','Rate',function($form){
			$form.on('submit',function(e){
				e.preventDefault();

				var data = $form.mkData();

				if (typeof data.vote === 'undefined')
					return $.Dialog.fail(false, 'Please choose a rating by clicking on one of the muffins');

				$.Dialog.wait(false, 'Submitting your rating');

				$.post('/episode/vote/'+EpID,data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					var $section = $voteButton.closest('section');
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
			$.post('/episode/vote/'+EpID+'?html',$.mkAjaxHandler(function(){
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
		$(this).find('a.detail').on('click',function(e){
			e.preventDefault();

			$.Dialog.wait('Voting details','Getting vote distribution information');

			$.post('/episode/vote/'+EpID+'?detail', $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				var $chart = $.mk('canvas').css({
						width: 200,
						height: 200,
						display: 'block',
						margin: '0 auto',
					}),
					ctx = $chart.get(0).getContext("2d"),
					$tooltip = $.mk('p').attr('class','tooltip').html('&nbsp;');
				$.Dialog.info(false, [
					$.mk('p').text("Here's a chart showing how the votes are distributed."),
					$.mk('div').attr('id','vote-distrib').append($chart, $tooltip)
				]);
				                   //-- 0 ---,--- 1 ---,--- 2 ---,--- 3 ---,--- 4 ---,--- 5 ---
				var LegendColors = [undefined,"#FF5454","#FFB554","#FFFF54","#8CD446","#4DC742"],
					data = this.data,
					totalVotes = 0;

				$.each(data,function(k,v){
					$.extend(data[k],{ color: LegendColors[parseInt(v.label, 10)] });
					totalVotes += parseInt(v.value, 10);
				});

				if (totalVotes === 0){
					$chart.remove();
					$tooltip.text('There are no votes for this episode yet');
					return;
				}

				new Chart(ctx).Pie(data,{
					animationEasing: 'easeInOutExpo',
					customTooltips: function(tooltip){
						if (!tooltip){
							$tooltip.css('color','').html('&nbsp;');
							return;
						}

						var dataArray = tooltip.text.split(': '),
								votePerc = Math.round((parseInt(dataArray[1],10)/totalVotes)*1000)/10;
						$tooltip.css('color',LegendColors[parseInt(dataArray[0], 10)]).empty().append(
								$.mk('span').text(dataArray[1]+' ×'),
								$.mk('span').attr('class','muffins cnt-'+dataArray[0]),
								$.mk('span').text('('+votePerc+'%)')
						);
					}
				});
			}));
		});
	};
	$voting.bindDetails();

	$.fn.rebindFluidbox = function(){
		$(this).find('.screencap > a')
			.fluidbox({
				immediateOpen: true,
				loader: true,
			})
			.on('openstart.fluidbox',function(){
				$body.addClass('no-distractions');
			})
			.on('closestart.fluidbox', function() {
				$body.removeClass('no-distractions');
			});
	};
	$.fn.rebindHandlers = function(){
		 this.find('li[id]').each(function(){
			var $li = $(this),
				id = parseInt($li.attr('id').replace(/\D/g,'')),
				type = $li.closest('section[id]').attr('id').replace(/s$/,'');

			Bind($li, id, type);
		});
		this.closest('section').find('.unfinished').rebindFluidbox();
		return this;
	};
	$('#requests, #reservations').rebindHandlers();
	function Bind($li, id, type){
		$li.children('button.reserve-request').off('click').on('click',function(e){
			e.preventDefault();

			var title = 'Reserving request',
				send = function(data){
					$.Dialog.wait(title, 'Sending reservation to the server');

					$.post("/post/reserve-request/" + id, data, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						var $newli = $(this.li);
						if ($li.hasClass('highlight'))
							$newli.addClass('highlight');
						$li.replaceWith($newli);
						$newli.rebindFluidbox();
						window.updateTimes();
						Bind($newli, id, type);
						$.Dialog.close();
					}));
				};

			if (typeof USERNAME_REGEX === 'undefined' || !e.shiftKey) send({});
			else {
				var $ReserveAsForm = $.mk('form').attr('id','reserve-as').append(
					$.mk('label').append(
						"<span>Reserve as</span>",
						$.mk('input').attr({
							type: 'text',
							name: 'post_as',
							placeholder: 'Username',
						}).patternAttr(USERNAME_REGEX)
					)
				);
				$.Dialog.request(title, $ReserveAsForm,'reserve-as','Reserve',function($form){
					$form.on('submit',function(e){
						e.preventDefault();

						send($form.mkData());
					});
				});
			}
		});
		$li.children('em').children('a:not(.da-userlink)').on('click',function(e){
			e.preventDefault();
			e.stopPropagation();

			history.replaceState(history.state,'',this.href);
			HighlightHash();
		});
		var $actions = $li.find('.actions').children();
		$actions.filter('.cancel').off('click').on('click',function(){
			$.Dialog.confirm('Cancel reservation','Are you sure you want to cancel this reservation?',function(sure){
				if (!sure) return;

				$.Dialog.wait(false, 'Cancelling reservation');

				$.post('/post/unreserve-'+type+'/'+id,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.remove === true){
						$.Dialog.close();
						return $li.remove();
					}

					var $newli = $(this.li);
					if ($li.hasClass('highlight'))
						$newli.addClass('highlight');
					$li.replaceWith($newli);
					$newli.rebindFluidbox();
					window.updateTimes();
					Bind($newli, id, type);
					$.Dialog.close();
				}));
			});
		});
		$actions.filter('.finish').off('click').on('click',function(){
			$.Dialog.request('Finish reservation','<form id="finish-res"><input type="text" name="deviation" placeholder="Deviation URL" required></form>','finish-res','Finish',function($form){
				$form.on('submit',function(e){
					e.preventDefault();

					var deviation = $form.find('[name=deviation]').val();

					if (typeof deviation !== 'string' || deviation.length === 0)
						return $.Dialog.fail(false, 'Please enter a deviation URL');

					$.Dialog.wait(false, 'Marking reservation as finished');

					var request_url = '/post/finish-'+type+'/'+id;
					$.post(request_url,{deviation:deviation},$.mkAjaxHandler(function(){
						var data = this,
							success = function(){
								$.Dialog.success(false, 'Reservation has been marked as finished');

								updateSection(type, SEASON, EPISODE, function(){
									if (typeof data.message === 'string')
										$.Dialog.success(false, data.message, true);
									else $.Dialog.close();
								});
							};
						if (data.status) success();
						else if (data.retry){
							$.Dialog.confirm(false, data.message, ["Continue","Cancel"], function(sure){
								if (!sure) return;

								$.post(request_url,{deviation:deviation,allow_overwrite_reserver:true}, $.mkAjaxHandler(function(){
									if (!this.status) return $.Dialog.fail(false, this.message);

									data = this;
									success();
								}));
							});
						}
						else $.Dialog.fail(false, data.message);
					}));
				});
			});
		});
		$actions.filter('.unfinish').off('click').on('click',function(){
			var $unfinishBtn = $(this),
				deleteOnly = $unfinishBtn.hasClass('delete-only'),
				Type = $.capitalize(type),
				what = type.replace(/s$/,'');

			$.Dialog.request((deleteOnly?'Delete':'Unfinish')+' '+what,'<form id="unbind-check"><p>Are you sure you want to '+(deleteOnly?'delete this reservation':'mark this '+what+' as unfinished')+'?</p><hr><label><input type="checkbox" name="unbind"> Unbind '+what+' from user</label></form>','unbind-check','Unfinish',function(){
				var $form = $('#unbind-check'),
					$unbind = $form.find('[name=unbind]');

				if (!deleteOnly)
					$form.prepend('<div class="notice info">By removing the "finished" flag, the post will be moved back to the "List of '+Type+'" section</div>');

				if (type === 'reservation'){
					$unbind.on('click',function(){
						$('#dialogButtons').children().first().val(this.checked ? 'Delete' : 'Unfinish');
					});
					if (deleteOnly)
						$unbind.trigger('click').off('click').on('click keydown touchstart', function(){return false}).css('pointer-events','none').parent().hide();
					$form.append('<div class="notice warn">Because this '+(!deleteOnly?'is a reservation, unbinding it from the user will <strong>delete</strong> it permanently.':'reservation was added directly, it cannot be marked unfinished, only deleted.')+'</div>');
				}
				else
					$form.append('<div class="notice info">If this is checked, any user will be able to reserve this request again afterwards. If left unchecked, only the current reserver <em>(and Vector Inspectors)</em> will be able to mark it as finished until the reservation is cancelled.</div>');
				$w.trigger('resize');
				$form.on('submit',function(e){
					e.preventDefault();

					var unbind = $unbind.prop('checked');

					$.Dialog.wait(false, 'Removing "finished" flag'+(unbind?' & unbinding from user':''));

					$.post('/post/unfinish-'+type+'/'+id+(unbind?'?unbind':''),$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$.Dialog.success(false, typeof this.message !== 'undefined' ? this.message : '"finished" flag removed successfully');
						updateSection(type, SEASON, EPISODE);
					}));
				});
			});
		});
		$actions.filter('.check').off('click').on('click',function(e){
			e.preventDefault();

			$.Dialog.wait('Submission approval status','Checking');

			$.post('/post/lock-'+type+'/'+id, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				var message = this.message;
				updateSection(type, SEASON, EPISODE,function(){
					$.Dialog.success(false, message, true);
				});
			}));
		});
		$actions.filter('.unlock').off('click').on('click',function(e){
			e.preventDefault();

			$.Dialog.wait('Unlocking post');

			$.post('/post/unlock-'+type+'/'+id, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				updateSection(type, SEASON, EPISODE);
			}));
		});
		$actions.filter('.delete').on('click',function(){
			var $this = $(this);

			$.Dialog.confirm('Deleteing request', 'You are about to permanently delete this request.<br>Are you sure about this?', function(sure){
				if (!sure) return;

				$.Dialog.wait(false, 'Sending deletion request');

				$.post('/post/delete-request/'+id,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.close();
					$this.closest('li').fadeOut(1000,function(){
						$(this).remove();
					});
				}));
			});
		});
		$actions.filter('.edit').on('click',function(){
			var $button = $(this),
				$li = $button.parents('li'),
				_split = $li.attr('id').split('-'),
				id = _split[1],
				type = _split[0];

			$.Dialog.wait('Editing '+type+' #'+id, 'Retrieving '+type+' details');

			$.post('/post/get-'+type+'/'+id,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				var postdata = this,
					$Form = $.mk('form').attr('id', 'post-edit-form').append(
						$.mk('label').append(
							$.mk('span').text('Description (3-255 chars.'+(type==='reservation'?', optional':'')+')'),
							$.mk('input').attr({
								type: 'text',
								maxlength: 255,
								pattern: "^.{3,255}$",
								name: 'label',
								required: type !== 'reservation',
							})
						)
					);

				if (type === 'request')
					$Form.append(
						$.mk('label').append(
							$.mk('span').text('Request type'),
							$.mk('select').attr({
								name: 'type',
								required: true,
							}).append(
								$.mk('option').attr('value','chr').text('Character'),
								$.mk('option').attr('value','obj').text('Object'),
								$.mk('option').attr('value','bg').text('Backgound')
							)
						)
					);

				if (typeof postdata.posted === 'string')
					$Form.append(
						$.mk('label').append(
							$.mk('span').text('Post timestamp'),
							$.mk('input').attr({
								type: 'datetime',
								name: 'date',
								required: true,
								spellcheck: false,
							})
						)
					);
				if (typeof postdata.reserved_at === 'string')
					$Form.append(
						$.mk('label').append(
							$.mk('span').text('Reserved at'),
							$.mk('input').attr({
								type: 'datetime',
								name: 'reserved_at',
								required: true,
								spellcheck: false,
							})
						)
					);
				if (typeof postdata.finished_at === 'string')
					$Form.append(
						$.mk('label').append(
							$.mk('span').text('Finished at'),
							$.mk('input').attr({
								type: 'datetime',
								name: 'finished_at',
								required: true,
								spellcheck: false,
							})
						)
					);

				var show_img_update_btn = $li.children('.image').find('.typcn-tick').length === 0,
					finished = $li.closest('div').attr('class') === 'finished',
					$fullsize_link = finished ? $li.children('.original') : $li.children('.image').children('a'),
					fullsize_url = $fullsize_link.attr('href'),
					show_stash_fix_btn = !FULLSIZE_MATCH_REGEX.test(fullsize_url) && /deviantart\.net\//.test(fullsize_url);

				if (show_img_update_btn || show_stash_fix_btn){
					$Form.append(
						$.mk('label').append(
							(
								show_img_update_btn
								? $.mk('a').text('Update Image').attr({
									'href':'#update',
									'class':'btn darkblue typcn typcn-pencil',
								}).on('click',function(e){
									e.preventDefault();

									$.Dialog.close();
									var $img = $li.children('.image').find('img'),
										$ImgUpdateForm = $.mk('form').attr('id', 'img-update-form').append(
											$.mk('div').attr('class','oldimg').append(
												$.mk('span').text('Current image'),
												$img.clone()
											),
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
									$.Dialog.request('Update image of '+type+' #'+id,$ImgUpdateForm, 'img-update-form','Update',function($form){
										$form.on('submit', function(e){
											e.preventDefault();

											var data = $form.mkData();
											$.Dialog.wait(false, 'Replacing image');

											$.post('/post/set-'+type+'-image/'+id,data,$.mkAjaxHandler(function(){
												if (!this.status) return $.Dialog.fail(false, this.message);

												$.Dialog.success(false, 'Image has been updated');

												updateSection(type, SEASON, EPISODE);
											}));
										});
									});
								})
								: undefined
							),
							(
								show_stash_fix_btn
								? $.mk('a').text('Sta.sh fullsize fix').attr({
									'href':'#fix-stash-fullsize',
									'class':'btn orange typcn typcn-spanner',
								}).on('click',function(e){
									e.preventDefault();
									$.Dialog.close();
									$.Dialog.wait('Fix Sta.sh fullsize URL','Fixing Sta.sh full size image URL');

									$.post('/post/fix-'+type+'-stash/'+id,$.mkAjaxHandler(function(){
										if (!this.status){
											if (this.rmdirect){
												if (!finished){
													$li.find('.post-date').children('a').first().triggerHandler('click');
													return $.Dialog.fail(false, this.message+'<br>The post might be broken because of this, please check it for any issues.');
												}
												$li.children('.original').remove();
											}
											return $.Dialog.fail(false, this.message);
										}

										$fullsize_link.attr('href', this.fullsize);
										$.Dialog.success(false, 'Fix successful', true);
									}));
								})
								: undefined
							)
						)
					);
				}

				$.Dialog.request(false, $Form, 'post-edit-form', 'Save', function($form){
					var $label = $form.find('[name=label]'),
						$type = $form.find('[name=type]'),
						$date, $reserved_at, $finished_at;
					if (postdata.label)
						$label.val(postdata.label);
					if (postdata.type)
						$type.children('option').filter(function(){
							return this.value === postdata.type;
						}).attr('selected', true);
					if (typeof postdata.posted === 'string'){
						$date = $form.find('[name=date]');

						var posted = moment(postdata.posted);
						$date.val(posted.format('YYYY-MM-DD\THH:mm:ssZ'));
					}
					if (typeof postdata.reserved_at === 'string'){
						$reserved_at = $form.find('[name=reserved_at]');

						var reserved = moment(postdata.reserved_at);
						$reserved_at.val(reserved.format('YYYY-MM-DD\THH:mm:ssZ'));
					}
					if (typeof postdata.finished_at === 'string'){
						$finished_at = $form.find('[name=finished_at]');

						var finished = moment(postdata.finished_at);
						$finished_at.val(finished.format('YYYY-MM-DD\THH:mm:ssZ'));
					}
					$form.on('submit',function(e){
						e.preventDefault();

						var data = { label: $label.val() };
						if (type === 'request')
							data.type = $type.val();
						if (typeof postdata.posted === 'string'){
							data.posted = new Date($date.val());
							if (isNaN(data.posted.getTime()))
								return $.Dialog.fail(false, 'Post timestamp is invalid');
							data.posted = data.posted.toISOString();
						}
						if (typeof postdata.reserved_at === 'string'){
							data.reserved_at = new Date($reserved_at.val());
							if (isNaN(data.reserved_at.getTime()))
								return $.Dialog.fail(false, '"Reserved at" timestamp is invalid');
							data.reserved_at = data.reserved_at.toISOString();
						}
						if (typeof postdata.finished_at === 'string'){
							data.finished_at = new Date($finished_at.val());
							if (isNaN(data.finished_at.getTime()))
								return $.Dialog.fail(false, '"Finished at" timestamp is invalid');
							data.finished_at = data.finished_at.toISOString();
						}

						$.Dialog.wait(false, 'Saving changes');

						$.post('/post/set-'+type+'/'+id,data, $.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							if (this.li){
								var $newli = $(this.li);
								if ($li.hasClass('highlight'))
									$newli.addClass('highlight');
								$li.replaceWith($newli);
								$newli.rebindFluidbox();
								window.updateTimes();
								Bind($newli, id, type);
							}

							$.Dialog.close();
						}));
					});
				});
			}));
		});
		$actions.filter('.share').on('click',function(){
			var $button = $(this),
				url = $button.parents('li').children('.post-date').children('a').first().prop('href');

			$.Dialog.info('Sharing '+type+' #'+id, $.mk('div').attr('class','align-center').append(
				'Use the link below to link to this post directly:',
				$.mk('div').attr('class','share-link').text(url),
				$.mk('button').attr('class','blue typcn typcn-clipboard').text('Copy to clipboard').on('click',function(e){
					$.copy(url,e);
				})
			));
		});
	}

	$.fn.formBind = function (){
		var $form = $(this),
			$formImgCheck = $form.find('.check-img'),
			$formImgPreview = $form.find('.img-preview'),
			$formDescInput = $form.find('[name=label]'),
			$formImgInput = $form.find('[name=image_url]'),
			$formTitleInput = $form.find('[name=label]'),
			$notice = $formImgPreview.children('.notice'),
			noticeHTML = $notice.html(),
			$previewIMG = $formImgPreview.children('img'),
			type = $form.attr('data-type'), Type = $.capitalize(type);

		if ($previewIMG.length === 0) $previewIMG = $(new Image()).appendTo($formImgPreview);
		$('#'+type+'-btn').on('click',function(){
			if (!$form.is(':visible')){
				$form.show();
				$formDescInput.focus();
				$.scrollTo($form.offset().top - $navbar.outerHeight() - 10, 500);
			}
		});
		if (type === 'reservation') $('#add-reservation-btn').on('click',function(){
			$.Dialog.request('Add a reservation','<form id="add-reservation"><div class="notice info">This feature should only be used when the vector was made before the episode was displayed here, and all you want to do is link your already-made vector under the newly posted episode.</div><div class="notice warn">If you already posted the reservation, use the <strong class="typcn typcn-attachment">I\'m done</strong> button to mark it as finished instead of adding it here.</div><input type="text" name="deviation" placeholder="Deviation URL"></form>','add-reservation','Finish',function($form){
				$form.on('submit',function(e){
					e.preventDefault();

					var deviation = $form.find('[name=deviation]').val();

					if (typeof deviation !== 'string' || deviation.length === 0)
						return $.Dialog.fail(false, 'Please enter a deviation URL');

					$.Dialog.wait(false, 'Adding reservation');

					$.post('/post/add-reservation',{deviation:deviation,epid:EpID},$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$.Dialog.success(false, this.message);
						updateSection(type, SEASON, EPISODE);
					}));
				});
			});
		});
		$formImgInput.on('keyup change paste',imgCheckDisabler);
		var outgoing =  /^https?:\/\/www\.deviantart\.com\/users\/outgoing\?/;
		function imgCheckDisabler(disable){
			var prevurl = $formImgInput.data('prev-url'),
				samevalue = typeof prevurl === 'string' && prevurl.trim() === $formImgInput.val().trim();
			$formImgCheck.attr('disabled',disable === true || samevalue);
			if (disable === true || samevalue) $formImgCheck.attr('title', 'You need to change the URL before chacking again.');
			else $formImgCheck.removeAttr('title');

			if (disable.type === 'keyup'){
				var val = $formImgInput.val();
				if (outgoing.test(val))
					$formImgInput.val($formImgInput.val().replace(outgoing,''));
			}
		}
		function checkImage(){
			var url = $formImgInput.val(),
				title = Type+' process';

			$formImgCheck.removeClass('red');
			imgCheckDisabler(true);
			$.Dialog.wait(title,'Checking image');

			$.post('/post', { image_url: url }, $.mkAjaxHandler(function(){
				var data = this;
				if (!data.status){
					$notice.children('p:not(.keep)').remove();
					$notice.prepend($.mk('p').attr('class','color-red').html(data.message)).show();
					$previewIMG.hide();
					return $.Dialog.close();
				}

				function load(data, attempts){
					$.Dialog.wait(title,'Checking image availability');

					$previewIMG.attr('src',data.preview).show().off('load error').on('load',function(){
						$notice.children('p:not(.keep)').remove();

						$formImgInput.data('prev-url', url);

						if (!!data.title && !$formTitleInput.val().trim())
							$.Dialog.confirm(
								'Confirm '+type+' title',
								'The image you just checked had the following title:<br><br><p class="align-center"><strong>'+data.title+'</strong></p>'+
								'<br>Would you like to use this as the '+type+'\'s description?<br>Keep in mind that it should describe the thing(s) '+
								(type==='request'?'being requested':'you plan to vector')+'.'+
								'<p>This dialog will not appear if you give your '+type+' a description before clicking the '+CHECK_BTN+' button.</p>',
								function(sure){
									if (!sure) return $form.find('input[name=label]').focus();
									$formTitleInput.val(data.title);
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
					});
				}
				load(data, 0);
			}));
		}
		var CHECK_BTN = '<strong class="typcn typcn-arrow-repeat" style="display:inline-block">Check image</strong>';
		$formImgCheck.on('click',function(e){
			e.preventDefault();

			checkImage();
		});
		$form.on('submit',function(e, screwchanges, sanityCheck){
			e.preventDefault();
			var title = Type+' process';

			if (!screwchanges && $formImgInput.data('prev-url') !== $formImgInput.val())
				return $.Dialog.confirm(
					title,
					'You modified the image URL without clicking the '+CHECK_BTN+' button.<br>Do you want to continue with the last checked URL?',
					function(sure){
						if (!sure) return;

						$form.triggerHandler('submit',[true]);
					}
				);

			if (typeof $formImgInput.data('prev-url') === 'undefined')
				return $.Dialog.fail(title, 'Please click the '+CHECK_BTN+' button before submitting your '+type+'!');

			if (!sanityCheck && type === 'request'){
				var label = $formDescInput.val(),
					$type = $form.find('select');

				if (label.indexOf('character') > -1 && $type.val() !== 'chr')
					return $.Dialog.confirm(title, 'Your request label contains the word "character", but the request type isn\'t set to Character.<br>Are you sure you\'re not requesting one (or more) character(s)?',['Let me change the type', 'Carray on'],function(sure){
						if (!sure) return $form.triggerHandler('submit',[screwchanges, true]);

						$.Dialog.close(function(){
							$type.focus();
						});
					});
			}

			var data = $form.mkData({
				what: type,
				episode: EPISODE,
				season: SEASON,
				image_url: $formImgInput.data('prev-url'),
			});

			(function submit(){
				$.Dialog.wait(title,'Submitting '+type);

				$.post('/post',data,$.mkAjaxHandler(function(){
					if (!this.status){
						if (!this.canforce)
							return $.Dialog.fail(false, this.message);
						return $.Dialog.confirm(false, this.message, ['Go ahead','Nevermind'], function(sure){
							if (!sure) return;

							data.allow_nonmember = true;
							submit();
						});
					}

					var id = this.id;
					updateSection(type, SEASON, EPISODE, function(){
						$.Dialog.close();
						$('#'+type+'-'+id).find('em.post-date').children('a').triggerHandler('click');
					});
				}));
			})();
		}).on('reset',function(){
			$formImgCheck.attr('disabled', false).addClass('red');
			$notice.html(noticeHTML).show();
			$previewIMG.hide();
			$formImgInput.removeData('prev-url');
			$(this).hide();
		});
	};
	function updateSection(type, SEASON, EPISODE, callback){
		var Type = $.capitalize(type),
			typeWithS = type.replace(/([^s])$/,'$1s');
		$.Dialog.wait($.Dialog.isOpen() ? false : Type, 'Updating list', true);
		$.post('/episode/'+typeWithS+'/S'+SEASON+'E'+EPISODE,$.mkAjaxHandler(function(){
			if (!this.status) return window.location.reload();

			var $section = $('#'+typeWithS),
				$newChilds = $(this.render).filter('section').children();
			$section.empty().append($newChilds).rebindHandlers();
			$section.find('.post-form').attr('data-type',type).formBind();
			window.updateTimes();
			if (typeof callback === 'function') callback();
			else $.Dialog.close();
		}));
	}
	$('.post-form').each($.fn.formBind);

	function HighlightHash(e){
		$('.highlight').removeClass('highlight');

		var $anchor = $(location.hash);
		if (!$anchor.length)
			return;
		$anchor.addClass('highlight');

		setTimeout(function(){
			$.scrollTo($anchor.offset().top - $navbar.outerHeight() - 10, 500, function(){
				if (typeof e === 'object' && e.type === 'load')
					$.Dialog.close();
			});
		}, 1);
	}
	$w.on('hashchange', HighlightHash);
	if (location.hash.length){
		var $imgs = $content.find('img'),
			total = $imgs.length, loaded = 0,
			postHashRegex = /^#(request|reservation)-\d+$/;

		if (total > 0 && $(location.hash).length > 0){
			$.Dialog.wait('Scroll post into view','Waiting for page to load');
			var $progress = $.mk('progress').attr({max:total,value:0}).css({display:'block',width:'100%',marginTop:'5px'});
			$('#dialogContent').children('div:not([id])').last().addClass('align-center').append($progress);
			$content.imagesLoaded()
				.progress(function(_, img){
					if (img.isLoaded)
						$progress.attr('value', ++loaded);
					else $progress.attr('max', --total);
				})
				.always(function(){
					HighlightHash({type:'load'});
				});
		}
		else if (postHashRegex.test(location.hash))
			$.Dialog.info('Scroll post into view',"The "+(location.hash.replace(postHashRegex,'$1'))+" you were linked to has either been deleted or didn't exist in the first place. Sorry.<div class='align-center'><span class='sideways-smiley-face'>:\\</div>");
	}
},function(){
	'use strict';
	$body.removeClass('no-distractions');
	$('.fluidbox--opened').fluidbox('close');
});
