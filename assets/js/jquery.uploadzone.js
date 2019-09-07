/* Image upload plugin w/ drag'n'drop support | by @SeinopSys | for gh:MLP-VectorClub/Winterchilla */
/* global File */
(function() {
  'use strict';
  let defaults = {
    requestKey: 'file',
    title: 'Image upload',
    accept: 'image/*',
    target: '',
    helper: true,
  };

  $.fn.uploadZone = function(opt) {
    opt = $.extend(true, {}, defaults, opt);

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

    $input.on('set-image', function(_, response) {
      const actions = function() {
        $this.removeClass('uploading');
        if (response.path){
          $input.prev().attr('href', response.path).children('img').fadeTo(200, 0, function() {
            let $image = $(this);
            $this.addClass('loading');
            $image.attr({ src: response.path, alt: '' }).on('load', function() {
              $this.removeClass('loading');
              $image.fadeTo(200, 1);
            });
            $this.trigger('uz-uploadfinish', [response]);
          });
          return;
        }

        $this.trigger('uz-uploadfinish', [response]);
      };
      if (response.keep_dialog === true)
        actions();
      else $.Dialog.close(actions);
    });
    $input.on('dragenter dragleave', function(e) {
      e.stopPropagation();
      e.preventDefault();

      $this[e.type === 'dragenter' ? 'addClass' : 'removeClass']('drop');
    });
    $input.on('change drop', function(e) {
      let files = e.target.files || e.originalEvent.dataTransfer.files;

      if (typeof files[0] === 'undefined' || !(files[0] instanceof File))
        return true;

      $this.trigger('uz-uploadstart').removeClass('drop').addClass('uploading');

      let fd = new FormData();
      fd.append(opt.requestKey, files[0]);

      let ajaxOpts = {
        url: opt.target,
        type: 'POST',
        contentType: false,
        processData: false,
        cache: false,
        data: fd,
        success: $.mkAjaxHandler(function() {
          if (this.status)
            $input.trigger('set-image', [this]);
          else {
            $.Dialog.fail(title, this.message);
            $this.trigger('uz-uploadfinish');
          }
        }),
        complete: function() {
          $this.removeClass('uploading');
          if (opt.helper)
            $helper.removeAttr('data-progress');
          $input.val('');
        },
      };
      if (opt.helper) ajaxOpts.xhr = function() {
        let xhrobj = $.ajaxSettings.xhr();
        if (xhrobj.upload)
          xhrobj.upload.addEventListener('progress', event => {
            if (!event.lengthComputable || !opt.helper) return true;

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
