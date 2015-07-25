/* Context menu plugin | by @DJDavid98 | for gh:ponydevs/MLPVC-RR | utilizes: http://stackoverflow.com/a/30255040/1344955 */
(function($){
	var $ctxmenu = $(document.createElement('div')).attr('id', 'ctxmenu');
	$ctxmenu
		.appendTo(document.body)
		.on('click',function(e){ e.stopPropagation() })
		.on('contextmenu', function(e){ $ctxmenu.hide(); return false });
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
			if (item.default === true) $action.addClass('default');
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
					.html($el.data('ctxmenu-items').clone(true, true))
					.css({ top: e.pageY, left: e.pageX, opacity: 0 })
					.show();

				var w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0),
					h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
					d = $(document).scrollTop(),
					p = $ctxmenu.position(),
					top = (p.top > h + d || p.top > h - d) ? e.pageY-$ctxmenu.outerHeight() : false,
					left = (p.left < 0 - $ctxmenu.width() || p.left > w) ? e.pageX-$ctxmenu.outerWidth() : false;

				if (top !== false) $ctxmenu.css('top', top);
				if (left !== false) $ctxmenu.css('left', left);

				$ctxmenu.css('opacity', 1);
			});
		});
	};
	$.ctxmenu.addItem =
	$.ctxmenu.addItems = function($el){
		var argl = arguments.length;
		if (argl < 2) throw new Error('Invalud number of arguments ('+argl+') for $.ctxmenu.addItems');

		var items = [].slice.apply(arguments);
		items.splice(0,1);

		if (typeof $el.data('ctxmenu-items') === 'undefined') return $el.ctxmenu(items);
		setTitle($el);

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
	$(window).on('blur resize',function(){ $ctxmenu.hide() });
})(jQuery);
