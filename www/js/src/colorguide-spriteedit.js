/* global DocReady,$content,SHORT_HEX_COLOR_PATTERN */
DocReady.push(function ColorguideSpriteedit(){
	'use strict';

	let Map = window.SpriteColorMap,
		AppearanceColors = window.AppearanceColors,
		HEX_COLOR_REGEX = window.HEX_COLOR_REGEX,
		$InputList = $('#form-cont').empty(),
		$ColorSelect = $.mk('select').append('<option value="" style="display:none">(foreign color)</option>'),
		$SVG = $('#svg-cont').children();

	$.each(AppearanceColors, function(_, color){
	    let bgcolor = ($.yiq(color.hex) >= 128) ? 'black' : 'white';
		$ColorSelect.append(`<option value="${color.hex}">${color.label}</option>`);
	});
	$ColorSelect.append(
		`<optgroup label="Universal colors">
			<option value="#FFFFFF">Eye | Shines</option>
			<option value="#000000">Eye | Pupil</option>
		</optgroup>`
	);

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
					required: true,
					value: actual,
					spellcheck: 'false',
					autocomplete: 'off',
					title: 'Hexadecimal color',
				}).patternAttr(HEX_COLOR_REGEX).on('keyup change input',function(e, skipselect, override){
					let $this = $(this),
						$cp = $this.siblings().first(),
						$select = $this.siblings('select'),
						color = (typeof override === 'string' ? override : this.value).trim(),
						valid = HEX_COLOR_REGEX.test(color);
					if (valid){
						$cp.removeClass('invalid').css('background-color', color.replace(HEX_COLOR_REGEX, '#$1'));

						if (skipselect !== true){
							if ($select.find(`option[value="${color}"]`).length)
								$select.val(color);
							else $select.val('');
						}
						let $rect = $SVG.find('rect').filter(function(){
							let attrval = this.getAttribute('data:ph');
							return typeof attrval === 'string' && attrval === placeholder;
						}).attr('fill', color);
					}
					else {
						$cp.addClass('invalid');
						$select.val('');
					}
				}).on('paste blur keyup',function(e, skipselect){
					let input = this,
						f = function(){
							let val = $.hexpand(input.value);
							if (!HEX_COLOR_REGEX.test(val))
								return;

							val = val.replace(HEX_COLOR_REGEX, '#$1').toUpperCase();
							let $input = $(input);
							switch (e.type){
								case 'paste':
								case 'blur':
									$input.val(val);
							}
							$input.trigger('change',[skipselect, val]).patternAttr(
								SHORT_HEX_COLOR_PATTERN.test(input.value)
								? SHORT_HEX_COLOR_PATTERN
								: HEX_COLOR_REGEX
							);
						};
					if (e.type === 'paste') setTimeout(f, 10);
					else f();
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
	$InputList.append('<button class="green typcn typcn-tick">Save</button>').on('submit',function(e){
		e.preventDefault();

		// TODO Implement
		return $.Dialog.info('Save sprite colors', 'This feature is not yet fully implemented, saving is not yet possible. Sorry.<div class="align-center"><span class="sideways-smiley-face">:\\</div>');

		/*let $form = $(this),
			Map = {};

		$form.find('input').each(function(i){
			let $this = $(this),
				val = $this.val();
			if (!$this.is(':valid'))
				return $.Dialog.fail('Save sprite colors', `Invalid color value (${val}) in row #${i}`);
			Map[$this.parent().attr('data-ph')] = val;
		});*/
	});
});
