(function() {
  'use strict';

  let $pagination = $('.pagination'),
    $GoToPageFormTemplate = $.mk('form').attr('id', 'goto-page').html(
      `<label>
				<span>Enter page number</span>
				<input type="number" min="1" step="1" class="large-number-input">
			</label>`,
    );

  function getLimits($el) {
    if (typeof $el === 'undefined'){
      const path = window.location.pathname.split('/');
      let pageNumber = 1;
      if (path.length > 1){
        const lastItem = path[path.length - 1];
        if (!isNaN(lastItem))
          pageNumber = parseInt(lastItem, 10);
      }
      return { pageNumber, maxPages: null };
    }

    return {
      pageNumber: parseInt($el.children('li').children('strong').text(), 10),
      maxPages: parseInt($el.children(':not(.spec)').last().text(), 10),
    };
  }

  $pagination.on('click', 'a[href]', function() {
    $(this).closest('li').addClass('loading');
  }).on('click', 'li.spec', function(e) {
    e.preventDefault();

    const $this = $(this);
    const limits = getLimits($this.closest('.pagination'));
    return $.Dialog.request('Navigation', $GoToPageFormTemplate.clone(true, true), 'Go to page', function($form) {
      $form.find('.large-number-input').attr('max', limits.maxPages).val(limits.pageNumber).get(0).select();

      $form.on('submit', function(e) {
        e.preventDefault();

        let page = parseInt($form.find('.large-number-input').val(), 10);
        page = isNaN(page) ? 1 : Math.max(1, page);

        $.Dialog.wait(false, `Loading page ${page}`);
        const href = $this.attr('data-baseurl');
        console.log(href);
        $.Navigation.visit(href.replace('*', page));
      });
    });
  });

})();
