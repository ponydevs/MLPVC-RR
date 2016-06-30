/* global DocReady,HEX_COLOR_PATTERN */
DocReady.push(function Blender(){
	'use strict';

	function reverseComponent(backgroundC, blendedC, alpha){
		return (blendedC - (1 - alpha) * backgroundC) / alpha;
	}

	function reverseRgb(bg, blend, alpha){
		return {
			r: reverseComponent(bg.r, blend.r, alpha),
			g: reverseComponent(bg.g, blend.g, alpha),
			b: reverseComponent(bg.b, blend.b, alpha),
			a: alpha,
		};
	}

	let $blendWrap = $('#blend-wrap'),
		$form = $blendWrap.children('form'),
		$inputs = $form.find('input:visible'),
		$result = $blendWrap.children('.result'),
		$preview = $result.children('.preview'),
		$hex = $result.children('.hex'),
		$hexa = $result.children('.hexa'),
		$rgba = $result.children('.rgba'),
		$opacity = $result.children('.opacity'),
		$deltaWarn = $('.delta-warn');

	$inputs.on('keyup change input',function(){
		let $cp = $(this).prev(),
			value = $.hexpand(this.value).toUpperCase();
		if (HEX_COLOR_PATTERN.test(value))
			$cp.removeClass('invalid').css('background-color', value);
		else $cp.addClass('invalid');

		$form.triggerHandler('submit');
	}).on('paste blur', function(e){
		let $input = $(this),
			f = function(){
				let val = $.hexpand($input.val());
				if (HEX_COLOR_PATTERN.test(val)){
					$input.val(val.replace(HEX_COLOR_PATTERN, '#$1').toUpperCase()).trigger('change');
					if (e.type !== 'blur')
						$input.next().focus();
				}
			};
		if (e.type === 'paste') setTimeout(f, 10);
		else f();
	}).trigger('change');
	$form.on('submit',Calculate).triggerHandler('submit');
	$form.on('click','td', function(e){
		if (!e.shiftKey)
			return true;
		let $hexInput = $(this).find('.clri');
		if ($hexInput.length === 0)
			return true;
		e.preventDefault();

		let hex = $hexInput.val(),
			$prev = $.mk('div').attr('class','preview').css('background-color', hex),
			OrigRGB = $.hex2rgb(hex),
			$formInputs = $.mk('div').attr('class','input-group-3').html(
				`<input type='number' class='color-red' name='r' min='0' max='255' step='1' value='${OrigRGB.r}'>
				<input type='number' class='color-green' name='g' min='0' max='255' step='1' value='${OrigRGB.g}'>
				<input type='number' class='color-darkblue' name='b' min='0' max='255' step='1' value='${OrigRGB.b}'>`
			);

		$formInputs.children().on('keyup change input mouseup',function(){
			let $form = $(this).closest('form');
			$form.children('.preview').css('background-color', $.rgb2hex($form.mkData()));
		});
		let $EnterRGBForm = $.mk('form','enter-rgb').append($formInputs,$prev);

		$.Dialog.setFocusedElement($hexInput);
		$.Dialog.request('Enter RGB values',$EnterRGBForm,'Set', function($form){
			$form.on('submit', function(e){
				e.preventDefault();

				$hexInput.val($.rgb2hex($form.mkData())).trigger('change');
				$.Dialog.close();
			});
		});
	});

	function Calculate(e){
		e.stopPropagation();

		let $validInputs = $inputs.filter(':valid');
		if ($validInputs.length !== 4)
			return SetPreview(false);

		let data = {};
		$validInputs.each(function(_,el){
			data[el.name] = el.value.toUpperCase();
		});

		if (data.bg1 === data.bg2)
			return SetPreview(false);
		$.each(data,function(k,v){
			data[k] = $.hex2rgb(v);
		});

		let minDelta = 255 * 4,
			bestMatch = null;
		for (let alpha = 1; alpha <= 255; alpha++){
			let RevRGB1 = reverseRgb(data.bg1, data.blend1, alpha / 255),
				RevRGB2 = reverseRgb(data.bg2, data.blend2, alpha / 255);

			let delta = Math.abs(RevRGB1.r - RevRGB2.r)
			            + Math.abs(RevRGB1.g - RevRGB2.g)
			            + Math.abs(RevRGB1.b - RevRGB2.b);

			if (delta < minDelta){
				minDelta = delta;
				bestMatch = RevRGB1;
			}
		}

		if (bestMatch === null)
			return SetPreview(false);
		$deltaWarn[minDelta > 10?'show':'hide']();
		SetPreview({
			r: Math.round(bestMatch.r),
			g: Math.round(bestMatch.g),
			b: Math.round(bestMatch.b),
			a: bestMatch.a,
		});
	}

	function SetPreview(rgba){
		let hex = '',
			hexa = '',
			opacity = '';
		if (rgba){
			hex = $.rgb2hex(rgba);
			$preview.css('background-color', hex);
			hex = `#<code class="color-red">${hex.substring(1,3)}</code><code class="color-green">${hex.substring(3,5)}</code><code class="color-darkblue">${hex.substring(5,7)}</code>`;
			hexa = hex + `<code>${Math.round(255*rgba.a).toString(16).toUpperCase()}</code>`;
			let alpha = $.roundTo(rgba.a, 2);
			rgba = `rgba(<code class="color-red">${rgba.r}</code>, <code class="color-green">${rgba.g}</code>, <code class="color-darkblue">${rgba.b}</code>, ${alpha})</span>`;
			opacity = `${alpha*100}% opacity`;
		}
		else {
			rgba = '';
			$preview.removeAttr('style');
		}
		$opacity.text(opacity);
		$hexa.html(hexa);
		$hex.html(hex);
		$rgba.html(rgba);
	}
});
