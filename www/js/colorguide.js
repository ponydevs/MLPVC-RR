DocReady.push(function Colorguide(){
	//noinspection JSUnusedLocalSymbols
	var Color = window.Color, color = window.color;

	var copyHash = !localStorage.getItem('leavehash'), $toggler;
	function copyHashToggler(){
		$toggler = $('#toggle-copy-hash');
		$toggler.off('display-update').on('display-update',function(){
			copyHash = !localStorage.getItem('leavehash');
			$toggler
				.attr('class','blue typcn typcn-'+(copyHash ? 'tick' : 'times'))
				.text('Copy # with color codes: '+(copyHash ? 'En':'Dis')+'abled');
		}).trigger('display-update').on('click',function(e){
			e.preventDefault();

			if (copyHash) localStorage.setItem('leavehash', 1);
			else localStorage.removeItem('leavehash');

			$toggler.triggerHandler('display-update');
		});
	}
	window.copyHashToggler = function(){copyHashToggler()};
	copyHashToggler();

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
		var $ch = $('ul.colors, #colors').children('li').children();
		$ch.filter(':not(:first-child):not([data-hasqtip])').each(function(){
			var $this = $(this),
				text = 'Click to copy HEX '+color+' code to clipboard',
				title = $this.attr('title');

			if ($this.is(':empty'))
				text = 'No color to copy';

			$this.qtip({
				content: {
					text: text,
					title: title
				},
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-see-thru' }
			});
		});
		$ch.filter('span:not(:first-child):not(:empty)').off('click').on('click',function(e){
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
	window.tooltips = function(){tooltips()};
	tooltips();

	$document.on('paginate-refresh',function(){
		tooltips();
		copyHashToggler();
	});

	$('#search-form').on('submit',function(e){
		e.preventDefault();

		var query = $(this).serialize();
		if (query === 'q=') query = '';
		else query = '?'+query;

		history.pushState({},'',window.location.pathname.replace(/\d+$/,'1')+query);
		$.toPage(false, true, true);
	});

	$w.on('unload',function(){
		$('.qtip').each(function(){
			var $this = $(this);
			$this.data('qtip').destroy();
			$this.remove();
		});
	});
});
