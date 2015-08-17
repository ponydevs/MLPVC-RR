$(function(){
	//noinspection JSUnusedLocalSymbols
	var Color = window.Color, color = window.color;

	function tooltips(){
		$('.tags').children().filter('[title][title!=""]').each(function(){
			var $this = $(this),
				tagstyle = $this.attr('class').match(/typ\-([a-z]+)(?:\s|$)/);

			tagstyle = tagstyle == null ? '' : ' qtip-tag-'+tagstyle[1];

			$this.qtip({
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-tag'+tagstyle }
			});
		});
		$('ul.colors').children('li').children('[title][title!=""]').qtip({
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

	$('#search-form').on('submit',function(e){
		e.preventDefault();

		var query = $(this).serialize();
		if (query === 'q=') query = '';
		else query = '?'+query;

		history.pushState({},'',window.location.pathname.replace(/\d+$/,'1')+query);
		$.toPage(false, true, true);
	});
});
