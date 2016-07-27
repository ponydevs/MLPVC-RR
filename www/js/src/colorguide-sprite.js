/* global DocReady,$content,SHORT_HEX_COLOR_PATTERN */
DocReady.push(function ColorguideSpriteedit(){
	'use strict';

	let Map = window.SpriteColorMap,
		AppearanceColors = window.AppearanceColors,
		HEX_COLOR_REGEX = window.HEX_COLOR_REGEX,
		$InputList = $('#input-cont').empty(),
		$ColorSelect = $.mk('select').disable().append('<option value="" style="display:none">(unrecognized color)</option>'),
		$SVG = $('#svg-cont').children();

	$.each(AppearanceColors, function(_, color){
	    let bgcolor = ($.yiq(color.hex) >= 128) ? 'black' : 'white';
		$ColorSelect.append(`<option value="${color.hex}">${color.label}</option>`);
	});
	$ColorSelect.append(
		`<optgroup label="Universal colors">
			<option value="#FFFFFF">Eye | Shines</option>
			<option value="#000000">Eye | Pupil</option>
		</optgroup>
		<optgroup label="Uniform mannequin">
			<option value="#D8D8D8">Mannequin | Outline</option>
			<option value="#E6E6E6">Mannequin | Fill</option>
			<option value="#BFBFBF">Mannequin | Shadow Outline</option>
			<option value="#CCCCCC">Mannequin | Shdow Fill</option>
		</optgroup>`
	);

	$SVG.find('rect').each(function(){
		let $rect = $(this);
		$rect.addClass($.yiq($rect.attr('fill')) > (0xFF/2) ? 'bright' : 'dark');
	});

	$.each(Map, function(placeholder, actual){
		let $select = $ColorSelect.clone();
		$select.find(`option[value="${actual}"]`).first().attr('selected', true);
		$select.on('change',function(){
			let $this = $(this),
				val = $this.find('option:selected').val();
			if (val.length)
				$this.siblings('input').val(val).triggerHandler('change', [true]);
		});
		$InputList.append(
			$.mk('div').attr('data-ph', placeholder).append(
				`<span class="color-preview" style="background-color:${actual}"></span>`,
				$select,
				$.mk('input').attr({
					type: 'text',
					value: actual,
					readonly: true,
				})
			).on('mouseenter',function(){
				let $rect = $SVG.find('rect').filter(function(){
					let attrval = this.getAttribute('data:ph');
					return typeof attrval === 'string' && attrval === placeholder;
				});
				$rect.addClass('highlight');
			}).on('mouseleave',function(){
				$SVG.find('.highlight').removeClass('highlight');
			})
		);
	});
	$InputList.children('div').sort(function(a,b){
		let at = $(a).children('select').children('option:selected').text() || '',
			bt = $(b).children('select').children('option:selected').text() || '';

		return at.localeCompare(bt);
	}).prependTo($InputList);
});
