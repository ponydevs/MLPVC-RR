/* Image upload plugin w/ drag'n'drop support | by @SeinopSys | for gh:ponydevs/MLPVC-RR */
/* global File */
(function(){
	'use strict';
	let defaults = {
		requestKey: 'file',
		title: 'Image upload',
		accept: 'image/*',
		target: '',
		helper: true,
	};

	$.fn.uploadZone = function(opt){
		opt = $.extend(true,{},defaults,opt);

		let title = opt.title,
			$this = $(this).first(),
			$input = $.mk('input').attr({
				'type': 'file',
				'name': opt.requestKey,
				'accept': opt.accept,
			}),
			$helper;

		if (opt.helper)
			$helper = $.mk('div').addClass('helper');

		$input.on('set-image',function(_, path){
			$.Dialog.close(function(){
				$this.removeClass('uploading');
				$input.prev().attr('href', path).children('img').fadeTo(200,0,function(){
					let $image = $(this);
					$image.attr('src',path).on('load',function(){
						$image.fadeTo(200,1);
					});
					$this.trigger('uz-uploadfinish');
				});
			});
		});
		$input.on('dragenter dragleave', function(e){
			e.stopPropagation();
			e.preventDefault();

			$this[e.type === 'dragenter' ? 'addClass' : 'removeClass']('drop');
		});
		$input.on('change drop', function(e){
			let files = e.target.files || e.originalEvent.dataTransfer.files;

			if (typeof files[0] === 'undefined' || !(files[0] instanceof File))
				return true;

			$this.trigger('uz-uploadstart').removeClass('drop').addClass('uploading');

			let fd = new FormData();
			fd.append('sprite', files[0]);
			fd.append('CSRF_TOKEN', $.getCSRFToken());

			let ajaxOpts = {
				url: opt.target,
				type: "POST",
				contentType: false,
				processData: false,
				cache: false,
				data: fd,
				success: $.mkAjaxHandler(function(){
					$helper.removeAttr('data-progress');
					$input.val('');
					if (this.status)
						$input.trigger('set-image', [this.path]);
					else {
						$.Dialog.fail(title,this.message);
						$this.trigger('uz-uploadfinish').removeClass('uploading');
					}
				}),
				error: function(xhr){
					if (xhr.status === 500 || xhr.status === 401) return;
					$.Dialog.fail(title,'Upload failed (HTTP '+xhr.status+')');
				}
			};
			if (opt.helper) ajaxOpts.xhr = function () {
				let xhrobj = $.ajaxSettings.xhr();
				if (xhrobj.upload)
					xhrobj.upload.addEventListener('progress', event => {
						if (!event.lengthComputable) return true;

						let complete = event.loaded || event.position,
							total = event.total;
						$helper.attr('data-progress', Math.round(complete / total * 100));
					}, false);

				return xhrobj;
			};
			$.ajax(ajaxOpts);
		});

		$this.append($input);
		if (opt.helper)
			$this.append($helper);

		return $this;
	};
})(jQuery);
