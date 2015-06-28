$(function(){
	var $w = $(window),
		$body = $(document.body),
		$navbar = $('header nav'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;

	$('#export').on('click',function(){
		$.post('/episode/export/S'+SEASON+'E'+EPISODE,{},function(data){
			if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

			if (data.status) $.Dialog.info('Exporting posts','<p>Here\'s the code you need to paste into the journal while in<br><em>HTML editing mode</em>, replacing what was there previously.</p><textarea style="display:block;margin:0 auto;resize:none"></textarea>',function(){
				$('#dialogContent').find('textarea').val(data.export);
			});
			else $.Dialog.fail('Display voting buttons',data.message);
		});
	});

	var $voting = $('#voting'),
		$voteButton = $voting.find('button');
	$voting.on('click','button',function(e){
		e.preventDefault();
		var $this = $(this),
			$both = $this.siblings('button').addBack(),
			value = $this.hasClass('green') ? 1 : -1,
			epid = 'S'+SEASON+'E'+EPISODE,
			title = (value > 0?'Up':'Down')+'voting '+epid;

		$both.attr('disabled', true);

		$.post('/episode/vote/'+epid,{vote:value},function(data){
			if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

			if (data.status){
				$.Dialog.close();
				var $section = $this.closest('section');
				$section.children('h2').nextAll().remove();
				$section.append(data.newhtml);
			}
			else {
				$.Dialog.fail(title,data.message);
				$both.attr('disabled', false);
			}
		})
	});

	$voting.find('time').data('dyntime-beforeupdate',function(diff){
		if (diff.past !== true) return;

		if (!$voteButton.length){
			$.post('/episode/vote/S'+SEASON+'E'+EPISODE+'?html',{},function(data){
				if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

				if (data.status){
					$voting.children('h2').nextAll().remove();
					$voting.append(data.html);
				}
				else $.Dialog.fail('Display voting buttons',data.message);
			});
			$(this).removeData('dyntime-beforeupdate');
			return false;
		}
	});

	$.fn.rebindHandlers = function(){
		var $this = $(this);
		$this.find('li[id]').each(function(){
			var $li = $(this),
				id = parseInt($li.attr('id').replace(/\D/g,'')),
				type = $li.closest('section[id]').attr('id');
				
			$('section .unfinished .screencap > a')
				.fluidbox({ immediateOpen: true })
				.on('openstart',function(){
				    $body.addClass('no-distractions');
				})
				.on('closestart', function() {
				    $body.removeClass('no-distractions');
				});

			Bind($li, id, type);
		});
		return $this;
	};
	$('#requests, #reservations').rebindHandlers();
	function Bind($li, id, type){
		$li.find('button.reserve-request').off('click').on('click',function(){
			var $this = $(this),
				title = 'Reserving request';

			$.Dialog.wait(title,'Sending reservation to the server');

			$.post("/reserving/request/"+id,{},function(data){
				if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

				if (data.status){
					$.Dialog.close();
					$(data.btnhtml).insertAfter($this);
					Bind($li, id, type);
					$this.remove();
				}
				else $.Dialog.fail(title,data.message);
			});
		}).next('button.delete').on('click',function(){
			var $this = $(this),
				title = 'Deleteing request';

			$.Dialog.confirm(title, 'You are about to permanently delete this request.<br>Are you sure about this?', function(sure){
				if (!sure) return;

				$.post('/reserving/request/'+id+'?delete',{},function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.close();
						$this.closest('li').remove();
					}
					else $.Dialog.fail(title,data.message);
				})
			});
		});
		var $actions = $li.find('.reserver-actions').children();
		$actions.filter('.cancel').off('click').on('click',function(){
			var $this = $(this),
				title = 'Cancel reservation';

			$.Dialog.confirm(title,'Are you sure you want to cancel this reservation?',function(sure){
				if (!sure) return;

				$.Dialog.wait(title,'Cancelling reservation');

				$.post('/reserving/'+type+'/'+id+'?cancel',{},function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.close();
						if (data.remove === true) return $this.closest('li').remove();
						$(data.btnhtml).insertBefore($this.parent().prev());
						$this.parent().prev().remove();
						$this.parent().remove();

						Bind($li, id, type);
					}
					else $.Dialog.fail(title,data.message);
				});
			});
		});
		$actions.filter('.finish').off('click').on('click',function(){
			var title = 'Finish reservation';

			$.Dialog.request(title,'<form id="finish-res"><div class="notice fail"><label>Error</label><p></p></div><input type="text" name="deviation" placeholder="Deviation URL"></form>','finish-res','Finish',function(){
				var $form = $('#finish-res'),
					$ErrorNotice = $form.find('.notice p');
				$ErrorNotice.parent().hide();
				$form.on('submit',function(e){
					e.preventDefault();

					var deviation = $form.find('[name=deviation]').val(),
						handleError = function(e){
							$ErrorNotice.html(e.message).parent().show();
							$w.trigger('resize');
							$form.find('input').attr('disabled', false);
						};

					try {
						if (typeof deviation !== 'string' || deviation.length === 0)
							throw new Error('Please enter a deviation URL');

						$.post('/reserving/'+type+'/'+id+'?finish',{deviation:deviation},function(data){
							if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

							if (data.status){
								$.Dialog.success(title,'The '+type+' is now marked as finished');
								updateSection(type, SEASON, EPISODE);
							}
							else handleError(data);
						});
					}
					catch(e){ handleError(e) }
				});
			})
		});
		$actions.filter('.unfinish').off('click').on('click',function(){
			var $unfinishBtn = $(this),
				deleteOnly = $unfinishBtn.hasClass('delete-only'),
				title = (deleteOnly?'Delete':'Un-finish')+' reservation',
				Type = type.charAt(0).toUpperCase()+type.substring(1);

			$.Dialog.request(title,'<form id="unbind-check"><p>Are you sure you want to '+(deleteOnly?'delete this reservation':'mark this reservation as unfinished')+'?</p><hr><label><input type="checkbox" name="unbind"> Unbind reservation from user</label></form>','unbind-check','Un-finish',function(){
				var $form = $('#unbind-check'),
					$unbind = $form.find('[name=unbind]');

				if (!deleteOnly)
					$form.prepend('<div class="notice info">By removing the "finished" flag, the deviation will be moved back<br>to the "List of '+Type+'" section</div>');

				if (type === 'reservations'){
					$unbind.on('click',function(){
						$('#dialogButtons').children().first().val(this.checked ? 'Delete' : 'Un-finish')
					});
					if (deleteOnly)
						$unbind.trigger('click').off('click').on('click keydown touchstart', function(){return false}).css('pointer-events','none').parent().hide();
					$form.append('<div class="notice warn">Because this '+(!deleteOnly?'is a reservation, unbinding<br>it from the user will <strong>delete</strong> it permanently.':'reservation was added directly,<br>it cannot be marked un-finished, only deleted.')+'</div>');
				}
				else
					$form.append('<div class="notice info">If this is checked, any user will be able to reserve this request again afterwards.<br>If left unchecked, only the current reserver <em>(and Vector Inspectors)</em><br>will be able to mark it as finished until the reservation is cancelled.</div>');
				$w.trigger('resize');
				$form.on('submit',function(e){
					e.preventDefault();

					var unbind = $unbind.prop('checked');

					$.Dialog.wait(title,'Removing "finished" flag'+(unbind?' & unbinding from user':''));

					$.post('/reserving/'+type+'/'+id+'?unfinish'+(unbind?'&unbind':''),{},function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							$.Dialog.success(title, typeof data.message !== 'undefined' ? data.message : '"finished" flag removed successfully');
							updateSection(type, SEASON, EPISODE);
						}
						else $.Dialog.fail(title,data.message);
					});
				});
			});
		});
	}

	function formBind(){
		var $form = this instanceof jQuery ? this : $(this),
			$formImgCheck = $form.find('.check-img'),
			$formImgPreview = $form.find('.img-preview'),
			$formImgInput = $form.find('[name=image_url]'),
			$formTitleInput = $form.find('[name=label]'),
			$notice = $formImgPreview.children('.notice'),
			noticeHTML = $notice.html(),
			$previewIMG = $formImgPreview.children('img'),
			type = $form.data('type'), Type = type.charAt(0).toUpperCase()+type.substring(1);

		if ($previewIMG.length === 0) $previewIMG = $(new Image()).appendTo($formImgPreview);
		$('#'+type+'-btn').on('click',function(){
			if (!$form.is(':visible')){
				$form.show();
				$formImgInput.focus();
				$body.animate({scrollTop: $form.offset().top - $navbar.outerHeight() - 10 }, 500);
			}
		});
		if (type === 'reservation') $('#add-reservation-btn').on('click',function(){
			var title = 'Add a reservation';
			$.Dialog.request(title,'<form id="add-reservation"><div class="notice fail"><label>Error</label><p></p></div><div class="notice info">This feature should only be used when the vector was made before the episode was displayed here,<br>and all you want to do is link your already-made vector under the newly posted episode.</div><div class="notice warn">If you already posted the reservation, use the <strong class="typcn typcn-attachment">I\'m done</strong> button to mark it as finished instead of adding it here.</div><input type="text" name="deviation" placeholder="Deviation URL"></form>','add-reservation','Finish',function(){
				var $form = $('#add-reservation'),
					$ErrorNotice = $form.find('.notice').hide().children('p');
				$form.on('submit',function(e){
					e.preventDefault();

					var deviation = $form.find('[name=deviation]').val(),
						handleError = function(e){
							$ErrorNotice.html(e.message).parent().show();
							$w.trigger('resize');
							$form.find('input').attr('disabled', false);
						};

					try {
						if (typeof deviation !== 'string' || deviation.length === 0)
							throw new Error('Please enter a deviation URL');

						$.post('/reserving/reservation?add=S'+SEASON+'E'+EPISODE,{deviation:deviation},function(data){
							if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

							if (data.status){
								$.Dialog.success(title,data.message);
								updateSection(type, SEASON, EPISODE);
							}
							else  handleError(data);
						});
					}
					catch(e){ handleError(e) }
				});
			})
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
				if (val.test(outgoing))
					$formImgInput.val($formImgInput.val().replace(outgoing,''));
			}
		}
		$formImgCheck.on('click',function(e){
			e.preventDefault();

			$formImgCheck.removeClass('red');
			imgCheckDisabler(true);
			var url = $formImgInput.val();

			$.ajax({
				method:'POST',
				url:'/post',
				data: { image_url: url },
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$previewIMG.attr('src',data.preview).show().on('load',function(){
							$notice.hide();

							$formImgInput.data('prev-url', url);

							if (!!data.title && !$formTitleInput.val().trim()) $formTitleInput.val(data.title);
						}).on('error',function(){
							$.Dialog.fail("Can't load image","There was an error while attempting to load the image.<br>Make sure the URL is correct and try again!");
						});
					}
					else {
						$notice.html(data.message).show();
						$previewIMG.hide();
					}
				}
			})
		});
		var CHECK_BTN = '<strong class="typcn typcn-arrow-repeat">Check image</strong>';
		$form.on('submit',function(e, screwchanges){
			e.preventDefault();

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
				return $.Dialog.fail(Type, 'Please click the '+CHECK_BTN+' button before submitting your '+type+'!');

			$.Dialog.wait(Type,'Submitting '+type);

			var tempdata = $form.serializeArray(), data = {what: type, episode: EPISODE, season: SEASON};
			tempdata['image_url'] = $formImgInput.data('prev-url');
			$.each(tempdata,function(i,el){
				data[el.name] = el.value;
			});

			$.post('/post',data,function(data){
				if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

				if (data.status) updateSection(type, SEASON, EPISODE);
				else $.Dialog.fail(Type, data.message);
			})
		}).on('reset',function(){
			$formImgCheck.attr('disabled', false).addClass('red');
			$notice.html(noticeHTML).show();
			$previewIMG.hide();
		    $formImgInput.removeData('prev-url');
		    $(this).hide();
		});
	}
	function updateSection(type, SEASON, EPISODE){
		var Type = type.charAt(0).toUpperCase()+type.substring(1);
		$.Dialog.wait(Type, 'Updating list');
		$.post('/episode/'+type.replace(/([^s])$/,'$1s')+'/S'+SEASON+'E'+EPISODE,{},function(data){
			if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

			if (data.status){
				var $render = $(data.render);

				formBind.call($('#'+type.replace(/([^s])$/,'$1s')).html($render.filter('section').html()).rebindHandlers().find('.post-form').data('type',type));
				$.Dialog.close();
			}
			else window.location.reload();
		});
	}
	$('.post-form').each(function(){
		formBind.call(this);
	});
});