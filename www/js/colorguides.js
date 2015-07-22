$(function(){
	function tooltips(){
		$('.tags').children().ctxmenu(
			[
				{text: "Add this tag to search", click: function(){
					$.Dialog.info('Add tag to search triggered', 'yay');
				}}
			],
			function($el){ return 'Tag: '+$el.text().trim().replace(',','') }
		).filter('[title!=""]').each(function(){
			$(this).qtip({
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-tag qtip-tag-'+this.className }
			});
		});
		var $colors = $('ul.colors').find('li > span[title][title!=""]').qtip({
			content: {
				text: 'Click to copy HEX color code to clipboard',
				title: function(){ return $(this).attr('title') }
			},
			position: { my: 'bottom center', at: 'top center', viewport: true },
			style: { classes: 'qtip-see-thru' }
		});

		var client = new ZeroClipboard($colors);
		client.on( "copy", function(e){
			var clipboard = e.clipboardData,
				$target = $(e.target);
			clipboard.setData("text/plain", $target.text().trim());
		});
	}
	tooltips();
	(window[' '] = {}).tooltips = function(){tooltips()};
});