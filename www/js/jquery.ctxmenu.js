/* Context menu plugin | by @DJDavid98 | for gh:ponydevs/MLPVC-RR */
(function($){
	var $ctxmenu = $(document.createElement('div')).attr('id', 'ctxmenu');
	$ctxmenu
		.appendTo(document.body)
		.on('click',function(e){ e.stopPropagation() });
	$.ctxmenu = {};

	function setTitle($el, title){
		if (typeof title === 'function')
			title = title($el);
		if (!($el.data('ctxmenu-items') instanceof jQuery))
			$el.data('ctxmenu-items', (new jQuery()).add($(document.createElement('li')).text(title || 'Context menu')));
		else if (title) $el.data('ctxmenu-items').children().first().text(title);

		return $el;
	}
	$.ctxmenu.setTitle = function(){
		return setTitle.apply(this, arguments);
	};

	function addToItems(item, $el){
		if (!item) return;

		var $item = $(document.createElement('li'));
		if (item === true) $item.addClass('sep');
		else {
			var $action = $(document.createElement('a'));
			if (item.text) $action.text(item.text);
			if (item.icon) $action.addClass('typcn typcn-'+item.icon);
			if (item.default === true) $action.css('font-weight', 'bold');
			if (typeof item.click === 'function')
				$action.on('click',function(e){
					e.stopPropagation();
					e.preventDefault();
					item.click.call($el.get(0), e);
					$ctxmenu.hide();
				});
			$action.appendTo($item);
		}
		$el.data('ctxmenu-items', $el.data('ctxmenu-items').add($item));
	}
	$.fn.ctxmenu = function(items, title){
		return $(this).each(function(){
			var $el = $(this);

			setTitle($el, title);

			$.each(items, function(_, item){
				addToItems(item, $el);
			});

			$el.on('contextmenu',function(e){
				e.preventDefault();
				e.stopPropagation();

				$ctxmenu
					.css({ top: e.clientY, left: e.clientX })
					.html($el.data('ctxmenu-items').clone(true, true))
					.show();
			});
		});
	};
	$.ctxmenu.addItem =
	$.ctxmenu.addItems = function($el){
		var argl = arguments.length;
		if (argl < 2) throw new Error('Invalud number of arguments ('+argl+') for $.ctxmenu.addItems');

		setTitle($el);

		var items = [].slice.apply(arguments);
		items.splice(0,1);
		$.each(items,function(_, item){
			addToItems(item, $el)
		});
	};
	$.ctxmenu.triggerItem = function($el, nth){
		var $ch = $el.data('ctxmenu-items');
		if (nth < 1 || $ch.length-1 < nth) throw new Error('There\'s no such menu option: '+nth);
		$ch.eq(nth).children('a').triggerHandler('click');
	};

	$(document.body).on('click contextmenu',function(){ $ctxmenu.hide() });
	$(window).on('blur',function(){ $ctxmenu.hide() });
})(jQuery);