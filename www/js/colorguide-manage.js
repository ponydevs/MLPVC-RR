$(function(){
	var Color = window.Color, color = window.color;

	$('.tags').children().ctxmenu(
		[
			{text: "Add this tag to search", icon: 'zoom', click: function(){
				$.Dialog.info('Add tag to search triggered', 'yay');
			}}
		],
		function($el){ return 'Tag: '+$el.text().trim() }
	).each(function(){
		$.ctxmenu.addItem($(this), {text: 'Edit tag', icon: 'pencil', click: function(){
			var $tag = $(this);
			$.Dialog.info('Edit triggered','Tag: '+$tag.text().replace(',',''));
		}});
	});

	$('ul.colors').children('li').each(function(){
		$(this).ctxmenu(
			[
				{text: "Edit "+color+" group", icon: 'pencil', click: function(){
					$.Dialog.info('Edit '+color+' group triggered', 'yay');
				}},
				{text: "Add new group", icon: 'folder-add', click: function(){
					$.Dialog.info('Add new group triggered', 'yay');
				}},
				{text: "Add new "+color, icon: 'plus', click: function(){
					$.Dialog.info('Add new color triggered', 'yay');
				}}
			],
			function($el){ return Color+' group '+$el.children().first().text().trim().replace(':','') }
		);
	}).children('span[title][title!=""]').each(function(){
		var $this = $(this);
		$this.on('click',function(e){
			e.preventDefault();
			if (!document.queryCommandSupported('copy')){
				prompt('Copy with Ctrl+C, close with Enter');
				return true;
			}

			var $helper = $(document.createElement('textarea')),
				success = false;
			$helper
				.css({
					opacity: 0,
					visibility: 'hidden',
					width: 0,
					height: 0,
					position: 'absolute',
					left: '-10px',
					top: '-10px',
					display: 'block',
				})
				.text($this.text().trim())
				.appendTo('body');
			$helper.get(0).focus();
			$helper.get(0).select();

			try {
				success = document.execCommand('copy');
			} catch(e){}

			if (!success)
				$.Dialog.fail('Copy to clipboard', 'Copying text to clipboard failed!');
			$helper.remove();
		});
	}).ctxmenu(
		[
			{text: "Copy "+color, icon: 'pencil', click: function(){
				$.Dialog.info('Edit '+color+' triggered', 'yay');
			}},
			{text: "Edit "+color, icon: 'pencil', click: function(){
				$.Dialog.info('Edit '+color+' triggered', 'yay');
			}},
			true,
			{text: "Edit "+color+" group", icon: 'pencil', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 1);
			}},
			{text: "Add new group", icon: 'folder-add', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 2);
			}},
			{text: "Add new "+color, icon: 'plus', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 3);
			}}
		],
		function($el){ return $el.siblings().first().text().trim().replace(':','')+' '+$el.attr('title') }
	);
});