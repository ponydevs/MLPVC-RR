$(function(){
	var $w = $(window),
		$body = $(document.body),
		$navbar = $('header nav'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;
	$('section .unfinished .screencap > a')
		.fluidbox({ immediateOpen: true })
		.on('openstart',function(){
		    $body.addClass('no-distractions');
		})
		.on('closestart', function() {
		    $body.removeClass('no-distractions');
		});

	$.fn.rebindHandlers = function(){
		var $this = $(this);
		$this.find('li[id]').each(function(){
			var $li = $(this),
				id = parseInt($li.attr('id').replace(/\D/g,'')),
				type = $li.closest('section[id]').attr('id');

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

			$.ajax({
				method: "POST",
				url: "/reserving/request/"+id,
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.close();
						$(data.btnhtml).insertAfter($this);
						Bind($li, id, type);
						$this.remove();
					}
					else $.Dialog.fail(title,data.message);
				}
			})
		});
		$li.find('.reserver-actions').children('.cancel').off('click').on('click',function(){
			var $this = $(this),
				title = 'Cancel reservation';

			$.Dialog.confirm(title,'Are you sure you want to cancel this reservation?',function(sure){
				if (!sure) return;

				$.Dialog.wait(title,'Cancelling reservation');

				$.ajax({
					method: "POST",
					url: '/reserving/'+type+'/'+id+'?cancel',
					success: function(data){
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
					}
				})
			});
		});
	}

	$('.post-form').each(function(){
		(function formBind($form){
			var $formImgCheck = $form.find('.check-img'),
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
			$formImgInput.on('keyup change paste',imgCheckDisabler);
			function imgCheckDisabler(disable){
				var prevurl = $formImgInput.data('prev-url'),
					samevalue = typeof prevurl === 'string' && prevurl.trim() === $formImgInput.val().trim();
				$formImgCheck.attr('disabled',disable === true || samevalue);
				if (disable === true || samevalue) $formImgCheck.attr('title', 'You need to change the URL before chacking again.');
				else $formImgCheck.removeAttr('title');
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
						'You modified the image URL without clicking the '+CHECK_BTN+' button.<br>Do you want to continue with the last checked URL?',
						function(sure){
							if (!sure) return;

							$form.trigger('submit',[true]);
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

					if (data.status){
						$.Dialog.wait(Type, 'Updating list');
						$.post('/episode/'+type+'s/S'+SEASON+'E'+EPISODE,{},function(data){
							if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

							if (data.status){
								var $render = $(data.render);
								console.log($render);

								formBind($('#'+type+'s').html($render.filter('section').html()).rebindHandlers().find('.post-form'));
								$.Dialog.close();
							}
							else window.location.reload();
						});
					}
					else $.Dialog.fail(Type, data.message);
				})
			}).on('reset',function(){
				$formImgCheck.attr('disabled', false).addClass('red');
				$notice.html(noticeHTML).show();
				$previewIMG.hide();
			    $formImgInput.removeData('prev-url');
			    $(this).hide();
			});
		})($(this));
	});
});