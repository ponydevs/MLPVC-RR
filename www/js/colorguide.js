/* globals $w,$d,DocReady */
DocReady.push(function Colorguide(){
	'use strict';
	//noinspection JSUnusedLocalSymbols
	var Color = window.Color, color = window.color, $list = $('#list');

	var copyHash = !localStorage.getItem('leavehash'), $toggler;
	function copyHashToggler(){
		$toggler = $('#toggle-copy-hash');
		if ($toggler.length) $toggler.off('display-update').on('display-update',function(){
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

	function tooltips(){
		$('.tags').children().filter('[title][title!=""]').each(function(){
			var $this = $(this),
				tagstyle = $this.attr('class').match(/typ\-([a-z]+)(?:\s|$)/);

			tagstyle = !tagstyle ? '' : ' qtip-tag-'+tagstyle[1];

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
					$(this).triggerHandler('click');
				}},
			],
			function($el){ return 'Color: '+$el.attr('oldtitle') }
		);
	}
	window.tooltips = function(){tooltips()};

	function Navigation(){
		$list = $('#list');
		tooltips();
		copyHashToggler();
	}
	$list.on('page-switch', Navigation);
	$d.on('paginate-refresh', Navigation);
	Navigation();

	$('#search-form').on('submit',function(e){
		e.preventDefault();

		var $this = $(this),
			query = $this.serialize();
		if (query === 'q=') query = false;
		$this.find('button[type=reset]').attr('disabled', query === false);

		$.toPage.call({query:query}, window.location.pathname.replace(/\d+$/,'1'), true, true);
	}).on('reset',function(e){
		e.preventDefault();

		var $this = $(this);
		$this.find('input[name=q]').val('');
		$this.triggerHandler('submit');
	});

	$w.on('unload',function(){
		$('.qtip').each(function(){
			var $this = $(this);
			$this.data('qtip').destroy();
			$this.remove();
		});
	});
});
