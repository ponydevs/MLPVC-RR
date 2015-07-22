$(function(){
	$('.tags').children().each(function(){
		$.ctxmenu.addItem($(this), {text: 'Edit tag', click: function(){
			var $tag = $(this);
			$.Dialog.info('Edit triggered','Tag: '+$tag.text().replace(',',''));
		}});
	});
});