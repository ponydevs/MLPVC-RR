$(function(){
	var $body = $(document.body),
		$navbar = $('header nav'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;
	$('section .unfinished .screencap').parent()
		.fluidbox()
		.on('openstart',function(){
		    $body.addClass('no-distractions');
		})
		.on('closestart', function() {
		    $body.removeClass('no-distractions');
		});

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
						$notice.hide();
						$previewIMG.attr('src',data.preview).show();

						$formImgInput.data('prev-url', url);

						if (!$formTitleInput.val().trim() && !!data.title) $formTitleInput.val(data.title);
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
		});
	});
});