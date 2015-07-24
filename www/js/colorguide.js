$(function(){
	var Color = window.Color, color = window.color;

	function tooltips(){
		$('.tags').children().ctxmenu(
			[
				{text: "Add this tag to search", icon: 'zoom', click: function(){
					$.Dialog.info('Add tag to search triggered', 'yay');
				}}
			],
			function($el){ return 'Tag: '+$el.text().trim() }
		).filter('[title!=""]').each(function(){
			$(this).qtip({
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-tag qtip-tag-'+this.className }
			});
		});
		$('ul.colors').children('li').children('span[title][title!=""]').qtip({
			content: {
				text: 'Click to copy HEX '+color+' code to clipboard',
				title: function(){ return $(this).attr('title') }
			},
			position: { my: 'bottom center', at: 'top center', viewport: true },
			style: { classes: 'qtip-see-thru' }
		});
	}
	tooltips();
	window.tooltips = function(){tooltips()};
});