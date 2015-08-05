/* Image upload plugin w/ drag'n'drop support | by @DJDavid98 | for gh:ponydevs/MLPVC-RR */
(function(){
	var defaults = {
		requestKey: 'file',
		title: 'Image upload',
		accept: 'image/*',
		target: '',
		helper: true,
	};

	$.fn.uploadZone = function(opt){
		opt = $.extend(true,{},defaults,opt);

		var title = opt.title,
			$this = $(this).first(),
			$input = $(document.createElement('input')).attr({
				'type': 'file',
				'name': opt.requestKey,
				'accept': opt.accept,
			});

		if (opt.helper) var $helper = $(document.createElement('div')).addClass('helper');

		$input.on('set-image',function(_, path){
			$.Dialog.close(function(){
				$input.prev().attr('href', path).children('img').fadeTo(200,0,function(){
					var $this = $(this);
					$this.attr('src',path).on('load',function(){
						$this.fadeTo(200,1);
					});
				});
			});
		});
		$input.on('dragenter dragleave',function(e){
			e.stopPropagation();
			e.preventDefault();

			$this[e.type === 'dragenter' ? 'addClass' : 'removeClass']('drop');
		});
		$input.on('change drop',function(e){
			var files = e.target.files || e.originalEvent.dataTransfer.files;

			if (typeof files[0] === 'undefined' || !(files[0] instanceof File))
				return true;

			$this.trigger('uz-uploadstart').removeClass('drop').addClass('uploading');

			var fd = new FormData();
			fd.append('sprite', files[0]);
			fd.append('CSRF_TOKEN', $.getCSRFToken());

			var ajaxOpts = {
				url: opt.target,
				type: "POST",
				contentType: false,
				processData: false,
				cache: false,
				data: fd,
				success: $.mkAjaxHandler(function(){
					$this.removeClass('uploading');
					$helper.removeAttr('data-progress');
					$input.val('');
					if (this.status)
						$input.trigger('set-image', [this.path]);
					else $.Dialog.fail(title,this.message);
				}),
				error: function(xhr){
					if (xhr.status === 500 || xhr.status === 401) return;
					$.Dialog.fail(title,'Upload failed (HTTP '+xhr.status+')');
				}
			};
			if (opt.helper) ajaxOpts['xhr'] = function () {
				var xhrobj = $.ajaxSettings.xhr();
				if (xhrobj.upload)
					xhrobj.upload.addEventListener('progress', function (event) {
						if (!event.lengthComputable) return true;

						var complete = event.loaded || event.position,
							total = event.total;
						$helper.attr('data-progress', Math.round(complete / total * 100));
					}, false);

				return xhrobj;
			};
			$.ajax(ajaxOpts);
		});

		$this.append($input);
		if (opt.helper) $this.append($helper);

		return $this;
	};
})(jQuery);
