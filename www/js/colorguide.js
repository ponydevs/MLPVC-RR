$(function(){
	//noinspection JSUnusedLocalSymbols
	var Color = window.Color, color = window.color;

	var copyHash = !localStorage.getItem('leavehash'), $toggler = $('#toggle-copy-hash');
	$toggler.on('display-update',function(){
		copyHash = !localStorage.getItem('leavehash');
		$toggler
			.attr('class','typcn typcn-'+(copyHash ? 'tick' : 'times'))
			.text('Copy # with color codes: '+(copyHash ? 'En':'Dis')+'abled');
	}).trigger('display-update').on('click',function(e){
		e.preventDefault();

		if (copyHash) localStorage.setItem('leavehash', 1);
		else localStorage.removeItem('leavehash');

		$toggler.trigger('display-update');
	});

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
		var $ch = $('ul.colors').children('li').children();
		$ch.filter(':not([data-hasqtip])').qtip({
			content: {
				text: 'Click to copy HEX '+color+' code to clipboard',
				title: function(){ return $(this).attr('title') }
			},
			position: { my: 'bottom center', at: 'top center', viewport: true },
			style: { classes: 'qtip-see-thru' }
		});
		$ch.filter('span:not(:first-child)').off('click').on('click',function(e){
			e.preventDefault();
			var copy = this.innerHTML.trim();
			if (!copyHash) copy = copy.replace('#','');
			$.copy(copy);
		}).filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Copy HEX "+color+" code", icon: 'clipboard', 'default': true, click: function(){
					$.copy(this.innerHTML.trim());
				}},
			],
			function($el){ return 'Color: '+$el.attr('oldtitle') }
		)
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
