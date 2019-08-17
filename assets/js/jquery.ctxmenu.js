/* Context menu plugin | by @SeinopSys | for gh:ponydevs/MLPVC-RR | utilizes: http://stackoverflow.com/a/30255040/1344955 */
(function($) {
  'use strict';
  let $ctxmenu = $.mk('div').attr('id', 'ctxmenu');
  $ctxmenu
    .appendTo($body)
    .on('click', function(e) {
      e.stopPropagation();
      $ctxmenu.hide();
    })
    .on('contextmenu', function(e) {
      let $target = $(e.target);
      switch (e.target.nodeName.toLowerCase()){
        case 'li':
          $target.children('a').trigger('click');
          break;
        case 'a':
          $target.trigger('click');
          break;
      }
      return false;
    });
  $.ctxmenu = { separator: true };

  function setTitle($el, title) {
    if (typeof title === 'function')
      title = title($el);
    if (!($el.data('ctxmenu-items') instanceof jQuery))
      $el.data('ctxmenu-items', $($.mk('li').text(title || 'Context menu')));
    else if (title) $el.data('ctxmenu-items').children().first().text(title);

    return $el;
  }

  $.ctxmenu.setTitle = (...args) => setTitle(...args);

  function addToItems(item, $el) {
    if (!item) return;

    let $item = $.mk('li');
    if (item === $.ctxmenu.separator) $item.addClass('sep');
    else {
      let $action = $.mk('a');
      if (item.text) $action.text(item.text);
      if (item.icon) $action.addClass('typcn typcn-' + item.icon);
      if (item.default === true) $action.addClass('default');
      if (typeof item.attr !== 'undefined'){
        if (typeof item.attr === 'string')
          $action.attr(item.attr, true);
        else $action.attr(item.attr);
      }
      if (typeof item.click === 'function')
        $action.on('click', function(e) {
          e.stopPropagation();
          e.preventDefault();
          item.click.call($el.get(0), e);
          $ctxmenu.hide();
        });
      $action.appendTo($item);
    }
    $el.data('ctxmenu-items', $el.data('ctxmenu-items').add($item));
  }

  $.fn.ctxmenu = function(items, title) {
    return $(this).each(function() {
      let $el = $(this);

      setTitle($el, title);

      $.each(items, (_, item) => {
        addToItems(item, $el);
      });

      $el.on('contextmenu', function(e) {
        if (e.shiftKey)
          return;
        e.preventDefault();
        e.stopPropagation();

        $el.trigger('mouseleave');

        $ctxmenu
          .html($el.data('ctxmenu-items').clone(true, true))
          .css({ top: e.pageY, left: e.pageX, opacity: 0 })
          .show();

        let w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0),
          h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
          d = $(document).scrollTop(),
          p = $ctxmenu.position(),
          top = (p.top > h + d || p.top > h - d) ? e.pageY - $ctxmenu.outerHeight() : false,
          left = (p.left < 0 - $ctxmenu.width() || p.left > w) ? e.pageX - $ctxmenu.outerWidth() : false;

        if (top !== false) $ctxmenu.css('top', top);
        if (left !== false) $ctxmenu.css('left', left);

        $ctxmenu.css('opacity', 1);
      }).addClass('ctxmenu-bound');
    });
  };
  $.ctxmenu.addItem =
    $.ctxmenu.addItems = function($el) {
      let argLength = arguments.length;
      if (argLength < 2) throw new Error(`Invalid number of arguments (${argLength}) for $.ctxmenu.addItems`);

      let items = $.toArray(arguments);
      items.splice(0, 1);

      $el.each(function() {
        let $el = $(this);

        if (typeof $el.data('ctxmenu-items') === 'undefined') return $el.ctxmenu(items);

        setTitle($el);

        $.each(items, (_, item) => addToItems(item, $el));
      });
    };
  $.ctxmenu.triggerItem = ($el, nth) => {
    let $ch = $el.data('ctxmenu-items').filter(':not(.sep)');
    if (nth < 1 || $ch.length - 1 < nth) throw new Error(`There's no such menu option: ${nth}`);
    $ch.eq(nth).children('a').triggerHandler('click');
  };
  $.ctxmenu.setDefault = ($el, nth) => {
    let $ch = $el.data('ctxmenu-items').filter(':not(.sep)');
    $ch.find('a').removeClass('default');
    $ch.eq(nth).children('a').addClass('default');
  };
  $.ctxmenu.runDefault = $el => $el.data('ctxmenu-items').find('a.default').trigger('click');

  $body.on('click contextmenu', function() {
    $ctxmenu.hide();
  });
  $w.on('blur resize', function() {
    $ctxmenu.hide();
  });
})(jQuery);
