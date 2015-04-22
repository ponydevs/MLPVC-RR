$(function(){
	var $body = $(document.body),
		$navbar = $('header nav'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;
	$('section .unfinished .screencap > a')
		.fluidbox()
		.on('openstart',function(){
		    $body.addClass('no-distractions');
		})
		.on('closestart', function() {
		    $body.removeClass('no-distractions');
		});

	$('#requests').find('li[id]').each(function(){
		var $li = $(this),
			id = parseInt($li.attr('id').replace(/\D/g,'')),
			type = '';
	});
	$('#requests, #reservations').find('li[id]').each(function(){
		var $li = $(this),
			id = parseInt($li.attr('id').replace(/\D/g,'')),
			type = $li.closest('section[id]').attr('id');

		Bind($li, id, type);
	});
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
		$li.find('.reserver-actions .typcn-times').off('click').on('click',function(){
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
		var $form = $(this),
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
				$body.animate({scrollTop: $form.offset().top + $navbar.outerHeight() }, 1000, 'easeOutQuint');
			}
		});
		$formImgInput.on('keyup change paste',imgCheckDisabler);
		function imgCheckDisabler(disable){
			var prevurl = $formImgInput.data('prev-url'),
				samevalue = typeof prevurl === 'string' && prevurl === $formImgInput.val().trim();
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
						$formImgCheck.attr('disabled', false);
						$notice.html(data.message).show();
						$previewIMG.hide();
					}
				}
			})
		});
		$form.on('submit',function(e){
			e.preventDefault();

			$.Dialog.wait(Type,'Submitting '+type);

			var tempdata = $form.serializeArray(), data = {what: type, episode: EPISODE, season: SEASON};
			$.each(tempdata,function(i,el){
				data[el.name] = el.value;
			});

			$.ajax({
				method: 'POST',
				url: '/post',
				data: data,
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.success(Type, data.message);
						setTimeout(function(){
							window.location.reload();
						},1000);
					}
					else $.Dialog.fail(Type, data.message);
				}
			})
		}).on('reset',function(){
			$formImgCheck.attr('disabled', false).addClass('red');
			$notice.html(noticeHTML).show();
			$previewIMG.hide();
		    $formImgInput.removeData('prev-url');
		    $(this).hide();
		});
	});
});