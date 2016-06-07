/* globals $w,$d,$content,DocReady,HandleNav */
DocReady.push(function Colorguide(){
	'use strict';
	//noinspection JSUnusedLocalSymbols
	var Color = window.Color, color = window.color, $list = $('.appearance-list'), EQG = window.EQG, AppearancePage = !!window.AppearancePage;

	var copyHash = !localStorage.getItem('leavehash'), $toggler;
	function copyHashToggler(){
		$toggler = $('#toggle-copy-hash');
		if (!$toggler.length)
			return;
		$toggler.off('display-update').on('display-update',function(){
			copyHash = !localStorage.getItem('leavehash');
			$toggler
				.attr('class','blue typcn typcn-'+(copyHash ? 'tick' : 'times'))
				.text('Copy # with '+color+' codes: '+(copyHash ? 'En':'Dis')+'abled');
		}).trigger('display-update').off('click').on('click',function(e){
			e.preventDefault();

			if (copyHash) localStorage.setItem('leavehash', 1);
			else localStorage.removeItem('leavehash');

			$toggler.triggerHandler('display-update');
		});
	}
	window.copyHashToggler = function(){copyHashToggler()};
	window.copyHashEnabled = function(){ return copyHash };

	var $SearchForm = $('#search-form');

	function tooltips(){
		var $tags = $('.tags').children('span.tag');
		$tags.each(function(){
			var $this = $(this),
				text = 'Click to quick search',
				title = $this.attr('title'),
				tagstyle = $this.attr('class').match(/typ\-([a-z]+)(?:\s|$)/);

			tagstyle = !tagstyle ? '' : ' qtip-tag-'+tagstyle[1];

			if (!title){
				var titletext = $this.text().trim();
				title = /^s\d+e\d+(-\d+)?$/i.test(titletext)
					? titletext.toUpperCase()
					: $.capitalize($this.text().trim(), true);
			}

			if (title){
				$this.qtip({
					content: {
						text: text,
						title: title
					},
					position: {my: 'bottom center', at: 'top center', viewport: true},
					style: {classes: 'qtip-tag' + tagstyle}
				});
			}
		});
		$tags.css('cursor','pointer').off('click').on('click',function(e){
			e.preventDefault();

			var query = this.innerHTML.trim();
			if ($SearchForm.length){
				$SearchForm.find('input[name="q"]').val(query);
				$SearchForm.triggerHandler('submit');
			}
			else $.Navigation.visit('/cg'+(EQG?'/eqg':'')+'/1?q='+query.replace(/ /g,'+'));
		});
		$('ul.colors').children('li').find('.valid-color').each(function(){
			var $this = $(this),
				text = 'Click to copy HEX '+color+' code to clipboard<br>Shift+Click to view RGB values',
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
		}).off('click mousedown').on('click',function(e){
			e.preventDefault();
			var $this = $(this),
				copy = $this.html().trim();
			if (e.shiftKey){
				var rgb = $.hex2rgb(copy),
					$cg = $this.closest('li'),
					path = [
						(
							!AppearancePage
							? $cg.parents('li').children().last().children('strong').text().trim()
							: $content.children('h1').text()
						),
						$cg.children().first().text().replace(/:\s+$/,''),
						$this.attr('oldtitle'),
					];
				return $.Dialog.info('RGB values for color ' + copy, '<div class="align-center">'+path.join(' &rsaquo; ')+'<br><span style="font-size:1.2em">rgb(<code class="color-red">'+rgb.r+'</code>, <code class="color-green">'+rgb.g+'</code>, <code class="color-darkblue">'+rgb.b+'</code>)</span></div>');
			}
			if (!copyHash) copy = copy.replace('#','');
			$.copy(copy);
		}).filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Copy HEX "+color+" code", icon: 'clipboard', 'default': true, click: function(){
					$(this).triggerHandler('click');
				}},
				{text: "View RGB values", icon: 'brush', click: function(){
					$(this).triggerHandler({
						type: 'click',
						shiftKey: true,
					});
				}},
			],
			function($el){ return 'Color: '+$el.attr('oldtitle') }
		).on('mousedown', function(e){
		    if (e.shiftKey)
		        e.preventDefault();
		});
		$('.cm-direction:not(.tipped)').each(function(){
			var $this = $(this),
				ponyID = $this.closest('li').attr('id').substring(1),
				base = new Image(),
				cm = new Image(),
				base_img = '/cg/v/'+ponyID+'.svg?t='+(parseInt(new Date().getTime()/1000)),
				cm_img = $this.attr('data-cm-preview');
			setTimeout(function(){
				base.src = base_img;
				cm.src = cm_img;
			}, 1);
			$this.addClass('tipped').qtip({
				content: {
					text: $.mk('span').attr('class', 'cm-dir-image').backgroundImageUrl(base_img).append(
						$.mk('div').attr('class', 'img cm-dir-'+$this.attr('data-cm-dir')).backgroundImageUrl(cm_img)
					)
				},
				position: { my: 'bottom center', at: 'top center', viewport: true },
				style: { classes: 'qtip-link' }
			});
		});
	}
	window.tooltips = function(){tooltips()};

	function Navigation(){
		$list = $('.appearance-list');
		if (!AppearancePage)
			$list.off('click','.getswatch',getswatch).on('click','.getswatch',getswatch);
		else $('.getswatch').off('click',getswatch).on('click',getswatch);
		tooltips();
		copyHashToggler();
	}
	$list.filter('#list').on('page-switch', Navigation);
	$d.on('paginate-refresh', Navigation);
	Navigation();

	$SearchForm.on('submit',function(e){
		e.preventDefault();

		var $this = $(this),
			$query = $this.find('input[name=q]'),
			orig_query = $query.val(),
			query = orig_query.trim().length === 0 ? false : $this.serialize();
		$this.find('button[type=reset]').attr('disabled', query === false);

		if (query !== false)
			$.Dialog.wait('Navigation', 'Searching for <code>'+orig_query.replace(/</g,'&lt;')+'</code>');
		else $.Dialog.success('Navigation', 'Search terms cleared');

		$.toPage.call({query:query}, window.location.pathname.replace(/\d+$/,'1'), true, true, false, function(){
			if (query !== false)
				return /^Page \d+/.test(document.title)
					? orig_query+' - '+document.title
					: document.title.replace(/^.*( - Page \d+)/, orig_query+'$1');
			else return document.title.replace(/^.* - (Page \d+)/, '$1');
		});
	}).on('reset',function(e){
		e.preventDefault();

		var $this = $(this);
		$this.find('input[name=q]').val('');
		$this.triggerHandler('submit');
	});

	function getswatch(e){
		e.preventDefault();

		//jshint -W040
		var $li = $(this).closest('[id^=p]'),
			ponyID = $li.attr('id').substring(1),
			pressAi = navigator && navigator.userAgent && /Macintosh/i.test(navigator.userAgent)
				? "<kbd>\u2318</kbd><kbd>F12</kbd>"
				: "<kbd>Ctrl</kbd><kbd>F12</kbd>",
			$instr = $.mk('div').append(
				$.mk('div').attr('class','hidden ai').append(
					"<h4>How to import swatches to Adobe Illustrator</h4>",
					$.mk('ul').append(
						"<li>Because Illustator uses a proprietary format for swatch files, you must download a script <a href='/dist/Import Swatches from JSON.jsx?v=1' download='Import Swatches from JSON.jsx' class='btn typcn typcn-download'>by clicking here</a> to be able to import them from our site. Once you downloaded it, place it in an easy to find location, because you'll need to use it every time you want to import colors.<br><small>If you place it in <code>&hellip;\\Adobe\\Adobe Illustrator *\\Presets\\*\\Scripts</code> it'll be available as one of the options in the Scripts submenu.</li>",
						$.mk('li').append(
							"Once you have the script, ",
							$.mk('a').attr({
								href: '/cg/v/'+ponyID+'.json',
								'class': 'btn blue typcn typcn-download',
							}).text('click here'),
							"to download the <code>.json</code> file that you'll need to use for the import."
						),
						"<li>Now that you have the 2 files, open Illustrator, create/open a document, then go to <strong>File &rsaquo; Scripts &rsaquo; Other Script</strong> (or press "+pressAi+") then find the file with the <code>.jsx</code> extension (the one you first downloaded). A dialog will appear telling you what to do next.</li>"
					)
				),
				$.mk('div').attr('class','hidden inkscape').append(
					"<h4>How to import swatches to Inkscape</h4>",
					$.mk('p').append(
						"Download ",
						$.mk('a').attr({
							href: '/cg/v/'+ponyID+'.gpl',
							'class': 'btn blue typcn typcn-download',
						}).text('this file'),
						" and place it in the <code>&hellip;\\Inkscape<wbr>\\<wbr>share<wbr>\\<wbr>palettes</code> folder. If you don't plan on using the other swatches, deleting them should make your newly imported swatch easier to find."
					),
					"<p>You will most likely have to restart Inkscape for the swatch to show up in the <em>Swatches</em> (<kbd>F6</kbd>) tool window's menu.</p>"
				)
			),
			$appsel = $.mk('select')
				.attr('required', true)
				.html('<option value="" selected style="display:none">Choose one</option><option value="inkscape">Inkscape</option><option value="ai">Adobe Illustrator</option>')
				.on('change',function(){
					var $sel = $(this),
						val = $sel.val(),
						$els = $sel.parent().next().children().hide();
					if (val)
						$els.filter('.'+val).show();
				}),
			$SwatchDlForm = $.mk('form').append(
				$.mk('label').attr('class','align-center').append(
					'<span>Choose your drawing program:</span>',
					$appsel
				),
				$instr
			).on('submit',function(){ return false });

		$.Dialog.info('Download swatch file',$SwatchDlForm);
	}
}, function(){
	'use strict';
	$('.qtip').each(function(){
		var $this = $(this);
		$this.data('qtip').destroy();
		$this.remove();
	});
});
