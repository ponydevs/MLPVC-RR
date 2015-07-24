$(function(){
	var Color = window.Color, color = window.color;

	function copyToClipboard(text){
		if (!document.queryCommandSupported('copy')){
			prompt('Copy with Ctrl+C, close with Enter', text);
			return true;
		}

		var $helper = $(document.createElement('textarea')),
			success = false;
		$helper
			.css({
				opacity: 0,
				width: 0,
				height: 0,
				position: 'absolute',
				left: '-10px',
				top: '-10px',
				display: 'block',
			})
			.text(text)
			.appendTo('body')
			.focus();
		$helper.get(0).select();

		try {
			success = document.execCommand('copy');
		} catch(e){}

		if (!success)
			$.Dialog.fail('Copy to clipboard', 'Copying text to clipboard failed!');
		setTimeout(function(){
			$helper.remove();
		}, 1);
	}

	$('.tags').children().each(function(){
		$.ctxmenu.addItem($(this), {text: 'Edit tag', icon: 'pencil', click: function(){
			var $tag = $(this);
			$.Dialog.info('Edit triggered','Tag: '+$tag.text().replace(',',''));
		}});
	});

	$('ul.colors').children('li').ctxmenu(
		[
			{text: "Edit "+color+" group", icon: 'pencil', click: function(){
				$.Dialog.info('Edit '+color+' group triggered', 'yay');
			}},
			{text: "Delete "+color+" group", icon: 'trash', click: function(){
				// TODO Confirmation
				$.Dialog.info('Delete '+color+' group triggered', 'yay');
			}},
			{text: "Add new group", icon: 'folder-add', click: function(){
				$.Dialog.info('Add new group triggered', 'yay');
			}},
			{text: "Add new "+color, icon: 'plus', click: function(){
				$.Dialog.info('Add new color triggered', 'yay');
			}}
		],
		function($el){ return Color+' group: '+$el.children().first().text().trim().replace(':','') }
	).children('span:not(:first-child)').off('click').on('click',function(e){
		e.preventDefault();

		copyToClipboard(this.innerHTML.trim());
	}).ctxmenu(
		[
			{text: "Copy "+color, icon: 'clipboard', 'default': true, click: function(){
				copyToClipboard(this.innerHTML.trim());
			}},
			{text: "Edit "+color, icon: 'pencil', click: function(){
				$.Dialog.info('Edit '+color+' triggered', 'yay');
			}},
			true,
			{text: "Edit "+color+" group", icon: 'pencil', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 1);
			}},
			{text: "Delete "+color+" group", icon: 'trash', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 2);
			}},
			{text: "Add new group", icon: 'folder-add', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 3);
			}},
			{text: "Add new "+color, icon: 'plus', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 4);
			}}
		],
		function($el){ return 'Color: '+$el.attr('oldtitle') }
	);
});