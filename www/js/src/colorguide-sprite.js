/* global DocReady,$content,SHORT_HEX_COLOR_PATTERN */
DocReady.push(function ColorguideSpriteedit(){
	'use strict';

	let AppearanceColors = window.AppearanceColors,
		SpriteColorList = window.SpriteColorList,
		$InputList = $('#input-cont').empty(),
		$ColorSelect = $.mk('select').disable().append('<option value="" style="display:none">(unrecognized color)</option>'),
		$SVG = $('#svg-cont').children(),
		AppearanceColorObject = {},
		AppearanceColorIterator = 1;

	$.each(AppearanceColors, (_, color) => {
		AppearanceColorObject[color.label] = AppearanceColorIterator++;
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

	$.each(SpriteColorList, function(index, actual){
		let $select = $ColorSelect.clone();
		$select.find(`option[value="${actual}"]`).first().attr('selected', true);
		$select.on('change',function(){
			let $this = $(this),
				val = $this.find('option:selected').val();
			if (val.length)
				$this.siblings('input').val(val).triggerHandler('change', [true]);
		});
		$InputList.append(
			$.mk('div').append(
				`<span class="color-preview" style="background-color:${actual}"></span>`,
				$select,
				$.mk('input').attr({
					type: 'text',
					value: actual,
					readonly: true,
				})
			).on('mouseenter',function(){
				$SVG.find(`rect[fill="${actual}"]`).addClass('highlight');
			}).on('mouseleave',function(){
				$SVG.find('.highlight').removeClass('highlight');
			})
		);
	});
	$InputList.children('div').sort(function(a,b){
		let at = AppearanceColorObject[$(a).children('select').children('option:selected').text()] || -1,
			bt = AppearanceColorObject[$(b).children('select').children('option:selected').text()] || -1;

		return at === bt ? 0 : (at < bt ? -1 : 1);
	}).prependTo($InputList);
});
