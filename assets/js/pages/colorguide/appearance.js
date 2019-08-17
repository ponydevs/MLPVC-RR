(function() {
  'use strict';

  window.copyHashToggler();

  const $colors = $('.colors');
  $('.color-list').on('click', '.reorder-cgs', function(e) {
    e.preventDefault();
    $.ctxmenu.triggerItem($colors, 1);
  }).on('click', '.create-cg', function(e) {
    e.preventDefault();
    $.ctxmenu.triggerItem($colors, 2);
  }).on('click', '.apply-template', function(e) {
    e.preventDefault();
    $.ctxmenu.triggerItem($colors, 3);
  });
  $colors.on('click', 'button.edit-cg', function() {
    $.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 1);
  }).on('click', 'button.delete-cg', function() {
    $.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 2);
  });

  $('button.share').on('click', function() {
    let $button = $(this),
      priv = $button.attr('data-private'),
      url = $button.attr('data-url');

    $.Dialog.info(`Sharing appearance`, $.mk('div').attr('class', 'align-center').append(
      (
        priv
          ? 'This appearance is private, but by using this link you can give anyone access to the colors'
          : 'You can use the link below to share this appearance with the world'
      ),
      $.mk('div').attr('class', 'share-link').text(url),
      $.mk('button').attr('class', 'blue typcn typcn-clipboard').text('Copy to clipboard').on('click', e => {
        $.copy(url, e);
      }),
    ), function() {
      $('#dialogContent').find('.share-link').select();
    });
  });
})();
