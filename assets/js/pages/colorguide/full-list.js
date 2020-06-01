(function() {
  'use strict';

  let $sortBy = $('#sort-by'),
    $fullList = $('#full-list'),
    $ReorderBtn = $('#guide-reorder'),
    $ReorderCancelBtn = $('#guide-reorder-cancel');
  const { GUIDE } = window;
  $sortBy.on('change', function() {
    let baseUrl = $sortBy.data('base-url'),
      val = $sortBy.val(),
      url = `${baseUrl}?ajax&sort_by=${val}`;

    $.Dialog.wait('Changing sort order');

    $.get(url, $.mkAjaxHandler(function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      $fullList.html(this.html);
      reobserve();
      $ReorderBtn.prop('disabled', val.length > 0);
      history.replaceState(history.state, '', this.stateUrl);
      $.Dialog.close();
    }));
  });

  const io = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting)
        return;

      const el = entry.target;
      io.unobserve(el);

      (function redo(datasetKey) {
        const isFallback = datasetKey === 'fallback';
        const newUrl = el.dataset[datasetKey];
        if (typeof newUrl !== 'string') {
          console.error(`Cannot load url el.dataset[${datasetKey}] of`, el);
          return;
        }
        const img = new Image();
        img.src = newUrl;
        $(img).on('load', function() {
          if (el.classList.contains('border') || isFallback)
            img.classList.add('border');
          $(el).replaceWith(img).css('opacity', 0).animate({ opacity: 1 }, 300);
        }).on('error', () => {
          if (isFallback)
            return;
          redo('fallback');
        });
      })('src');
    });
  });

  function reobserve() {
    $fullList.find('section > ul .appearance-preview-promise').each((_, el) => io.observe(el));
  }

  reobserve();

  if (window.Sortable){
    $fullList.on('click', '.sort-alpha', function() {
      let $section = $(this).closest('section'),
        $ul = $section.children('ul');
      $ul.children().sort(function(a, b) {
        return $(a).text().trim().localeCompare($(b).text().trim());
      }).appendTo($ul);
    });

    $ReorderBtn.on('click', function() {
      if (!$ReorderBtn.hasClass('typcn-tick')){
        $ReorderBtn.removeClass('typcn-arrow-unsorted darkblue').addClass('typcn-tick green').html('Save');
        $fullList.addClass('sorting').children().each(function() {
          let $names = $(this).children('ul');
          $names.children().each(function() {
            let $li = $(this);
            $li.data('orig-index', $li.index());
          }).children().moveAttr('href', 'data-href');
          $names.sortable({ draggable: 'li' });
        });
        $('.sort-alpha').add($ReorderCancelBtn).removeClass('hidden');
      }
      else {
        $.Dialog.wait('Re-ordering appearances');

        let list = [];
        $fullList.children().children('ul').children().each(function() {
          list.push($(this).children().attr('data-href').split('/').pop().replace(/^(\d+)\D.*$/, '$1'));
        });

        const data = {
          list: list.join(','),
          ordering: $sortBy.val(),
        };
        if (GUIDE)
          data.guide = GUIDE;

        $.API.post('/cg/full/reorder', data, function() {
          if (!this.status) return $.Dialog.fail(false, this.message);

          $fullList.removeClass('sorting').html(this.html);
          reobserve();
          $ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
          $ReorderCancelBtn.addClass('hidden');
          $.Dialog.close();
        });
      }
    });

    $ReorderCancelBtn.on('click', function() {
      $ReorderBtn.removeClass('typcn-tick green').addClass('typcn-arrow-unsorted darkblue').html('Re-order');
      $fullList.removeClass('sorting').children().each(function() {
        let $names = $(this).children('ul');
        $names.children().sort(function(a, b) {
          a = $(a).data('orig-index');
          b = $(b).data('orig-index');
          return a > b ? 1 : (a < b ? -1 : 0);
        }).appendTo($names).removeData('orig-index').children().moveAttr('data-href', 'href');
        $names.sortable('destroy');
      });
      $('.sort-alpha').add($ReorderCancelBtn).addClass('hidden');
    });
  }
})();
