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
		$('ul.colors').find('li > span[title][title!=""]').each(function(){
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
			$this.qtip({
				content: {
					text: 'Click to copy HEX color code to clipboard',
					title: function(){ return $(this).attr('title') }
				},
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-see-thru' }
			});
		});
	}
	tooltips();
	(window[' '] = {}).tooltips = function(){tooltips()};
});