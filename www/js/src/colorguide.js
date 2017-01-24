/* globals $w,$d,$content,DocReady,HandleNav */
DocReady.push(function(){
	'use strict';

	//noinspection JSUnusedLocalSymbols
	let Color = window.Color, color = window.color, $list = $('.appearance-list'), EQG = window.EQG, AppearancePage = !!window.AppearancePage;

	let copyHash = !$.LocalStorage.get('leavehash'), $toggler;
	function copyHashToggler(){
		$toggler = $('#toggle-copy-hash');
		if (!$toggler.length)
			return;
		$toggler.off('display-update').on('display-update',function(){
			copyHash = !$.LocalStorage.get('leavehash');
			$toggler
				.attr('class','blue typcn typcn-'+(copyHash ? 'tick' : 'times'))
				.text(`Copy # with ${color} codes: `+(copyHash ? 'En':'Dis')+'abled');
		}).trigger('display-update').off('click').on('click', function(e){
			e.preventDefault();

			if (copyHash) $.LocalStorage.set('leavehash', 1);
			else $.LocalStorage.remove('leavehash');

			$toggler.triggerHandler('display-update');
		});
	}
	window.copyHashToggler = function(){copyHashToggler()};
	window.copyHashEnabled = function(){ return copyHash };

	let $SearchForm = $('#search-form');

	function tooltips(){
		let $tags = $('.tags').children('span.tag');
		$tags.each(function(){
			let $this = $(this),
				text = 'Click to quick search',
				title = $this.attr('title'),
				tagstyle = $this.attr('class').match(/typ\-([a-z]+)(?:\s|$)/);

			tagstyle = !tagstyle ? '' : ` qtip-tag-${tagstyle[1]}`;

			if (!title){
				let titletext = $this.text().trim();
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
					style: {classes: `qtip-tag${tagstyle}`}
				});
			}
		});
		$tags.css('cursor','pointer').off('click').on('click', function(e){
			e.preventDefault();

			let query = this.innerHTML.trim();
			if ($SearchForm.length){
				$SearchForm.find('input[name="q"]').val(query);
				$SearchForm.triggerHandler('submit');
			}
			else $.Navigation.visit('/cg'+(EQG?'/eqg':'')+`/1?q=${encodeURIComponent(query)}`);
		});
		$('ul.colors').children('li').find('.valid-color').each(function(){
			let $this = $(this);
			if ($this.hasAttr('data-hasqtip'))
				$this.data('qtip').destroy();

			let text = `Click to copy HEX ${color} code to clipboard<br>Shift+Click to view RGB values`,
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

			return true;
		}).off('mousedown touchstart click').on('click', function(e){
			e.preventDefault();
			let $this = $(this),
				copy = $this.html().trim();
			if (e.shiftKey){
				let rgb = $.hex2rgb(copy),
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
				return $.Dialog.info(`RGB values for color ${copy}`, `<div class="align-center">${path.join(' &rsaquo; ')}<br><span style="font-size:1.2em">rgb(<code class="color-red">${rgb.r}</code>, <code class="color-green">${rgb.g}</code>, <code class="color-darkblue">${rgb.b}</code>)</span></div>`);
			}
			if (!copyHash) copy = copy.replace('#','');
			$.copy(copy);
		}).filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: `Copy HEX ${color} code`, icon: 'clipboard', 'default': true, click: function(){
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
			let $this = $(this),
				ponyID = $this.closest('li').attr('id').substring(1),
				base = new Image(),
				cm = new Image(),
				base_img = `/cg/v/${ponyID}d.svg?t=`+(parseInt(new Date().getTime()/1000)),
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
		$list.find('li strong > a.btn.darkblue:not(.tipped)').add($list.find('li > .sprite:not(.tipped)')).each(function(){
			let $this = $(this),
				isSprite = this.parentNode.nodeName.toLowerCase() === 'li';
			$this.addClass('tipped').qtip({
				content: {
					text: "Most browsers display colors incorrectly. To ensure that the accuracy of the colors is preserved, "+
					      "please download or copy the image, instead of using <kbd>PrintScreen</kbd> or programs that let you grab "+
					      "colors directly from the browser."
				},
				position: { my: isSprite ? 'left center' : 'top center', at: isSprite ? 'right center' : 'bottom center', viewport: true },
				style: { classes: 'qtip-pick-warn' }
			});
		});
	}
	window.tooltips = function(){tooltips()};

	function navigation(){
		$list = $('.appearance-list');
		if (!AppearancePage)
			$list.off('click','.getswatch',getswatch).on('click','.getswatch',getswatch);
		else $('.getswatch').off('click',getswatch).on('click',getswatch);
		tooltips();
		copyHashToggler();
	}
	$list.filter('#list').on('page-switch', navigation);
	$d.on('paginate-refresh', navigation);
	navigation();

	$SearchForm.on('submit', function(e, gofast){
		e.preventDefault();

		let $this = $(this),
			$query = $this.find('input[name=q]'),
			orig_query = $query.val(),
			query = orig_query.trim().length === 0 ? false : $this.serialize();
		$this.find('button[type=reset]').attr('disabled', query === false);

		if (!gofast){
			if (query !== false)
				$.Dialog.wait('Navigation', `Searching for <code>${orig_query.replace(/</g,'&lt;')}</code>`);
			else $.Dialog.success('Navigation', 'Search terms cleared');
		}
		
		$.toPage.call({query:query,gofast:gofast}, window.location.pathname.replace(/\d+($|\?)/,'1$1'), true, true, false, function(){
			$('.qtip').each(function(){
				let $this = $(this);
				$this.data('qtip').destroy();
				$this.remove();
			});

			if (query !== false)
				return /^Page \d+/.test(document.title)
					? `${orig_query} - ${document.title}`
					: document.title.replace(/^.*( - Page \d+)/, orig_query+'$1');
			else return document.title.replace(/^.* - (Page \d+)/, '$1');
		});
	}).on('reset', function(e){
		e.preventDefault();

		let $this = $(this);
		$this.find('input[name=q]').val('');
		$this.triggerHandler('submit');
	}).on('click','.sanic-button',function(){
		$SearchForm.triggerHandler('submit', [true]);
	});

	function getswatch(e){
		e.preventDefault();

		//jshint -W040
		let $li = $(this).closest('[id^=p]'),
			ponyID = $li.attr('id').substring(1),
			ponyName = (
				!AppearancePage
				? $li.find('strong').first()
				: $content.children('h1')
			).text().trim(),
			pressAi = navigator && navigator.userAgent && /Macintosh/i.test(navigator.userAgent)
				? "<kbd>\u2318</kbd><kbd>F12</kbd>"
				: "<kbd>Ctrl</kbd><kbd>F12</kbd>",
			$instr = $.mk('div').html(
				`<div class='hidden section ai'>
					<h4>How to import swatches to Adobe Illustrator</h4>
					<ul>
						<li>Because Illustator uses a proprietary format for swatch files, you must download a script <a href='/dist/Import Swatches from JSON.jsx?v=1.4' download='Import Swatches from JSON.jsx' class='btn typcn typcn-download'>by clicking here</a> to be able to import them from our site. Once you downloaded it, place it in an easy to find location, because you'll need to use it every time you want to import colors.<br><small>If you place it in <code>&hellip;\\Adobe\\Adobe Illustrator *\\Presets\\*\\Scripts</code> it'll be available as one of the options in the Scripts submenu.</small></li>
						<li>Once you have the script, <a href="/cg/v/${ponyID}s.json" class="btn blue typcn typcn-download">click here</a> to download the <code>.json</code> file that you'll need to use for the import.</li>
						<li>Now that you have the 2 files, open Illustrator, create/open a document, then go to <strong>File &rsaquo; Scripts &rsaquo; Other Script</strong> (or press ${pressAi}) then find the file with the <code>.jsx</code> extension (the one you first downloaded). A dialog will appear telling you what to do next.</li>
					</ul>
					<div class="responsive-embed">
						<iframe src="https://www.youtube.com/embed/oobQZ2xiDB8" allowfullscreen async defer></iframe>
					</div>
				</div>
				<div class='hidden section inkscape'>
					<h4>How to import swatches to Inkscape</h4>
					<p>Download <a href='/cg/v/${ponyID}s.gpl' class='btn blue typcn typcn-download'>this file</a> and place it in the <code>&hellip;\\Inkscape<wbr>\\<wbr>share<wbr>\\<wbr>palettes</code> folder. If you don’t plan on using the other swatches, deleting them should make your newly imported swatch easier to find.</p>
					<p>You will most likely have to restart Inkscape for the swatch to show up in the <em>Swatches</em> (<kbd>F6</kbd>) tool window’s menu.</p>
					<div class="responsive-embed">
						<iframe src="https://www.youtube.com/embed/zmaJhbIKQqM" allowfullscreen async defer></iframe>
					</div>
				</div>`
			),
			$appsel = $.mk('select')
				.attr('required', true)
				.html('<option value="" selected style="display:none">Choose one</option><option value="inkscape">Inkscape</option><option value="ai">Adobe Illustrator</option>')
				.on('change',function(){
					let $sel = $(this),
						val = $sel.val(),
						$els = $sel.parent().next().children().addClass('hidden');
					console.log(val);
					if (val)
						$els.filter('.'+val).removeClass('hidden');
				}),
			$SwatchDlForm = $.mk('form').attr('id','swatch-save').append(
				$.mk('label').attr('class','align-center').append(
					'<span>Choose your drawing program:</span>',
					$appsel
				),
				$instr
			);

		$.Dialog.info(`Download swatch file for ${ponyName}`,$SwatchDlForm);
	}
}, function(){
	'use strict';
	$('.qtip').each(function(){
		let $this = $(this);
		$this.data('qtip').destroy();
		$this.remove();
	});
});
