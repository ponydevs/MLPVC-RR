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

	var $blendWrap = $('#blend-wrap'),
		$form = $blendWrap.children('form'),
		$inputs = $form.find('input:visible'),
		$result = $blendWrap.children('.result'),
		$preview = $result.children('.preview'),
		$hex = $result.children('.hex'),
		$hexa = $result.children('.hexa'),
		$rgba = $result.children('.rgba'),
		$opacity = $result.children('.opacity');

	$inputs.on('keyup change input',function(){
		var $this = $(this),
			$cp = $this.prev(),
			valid = HEX_COLOR_PATTERN.test(this.value);
		if (valid)
			$cp.removeClass('invalid').css('background-color', this.value.toUpperCase().replace(HEX_COLOR_PATTERN, '#$1'));
		else $cp.addClass('invalid');

		$form.triggerHandler('submit');
	}).on('paste blur',function(e){
		var input = this,
			$input = $(input),
			shortHex = /^#?([A-Fa-f0-9]{3})$/,
			f = function(){
				var val = input.value;
				if (shortHex.test(val)){
					var match = val.match(shortHex)[1];
					val = '#'+match[0]+match[0]+match[1]+match[1]+match[2]+match[2];
				}
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

	function Calculate(e){
		e.stopPropagation();

		var $validInputs = $inputs.filter(':valid');
		if ($validInputs.length !== 4)
			return SetPreview(false);

		var data = {};
		$validInputs.each(function(_,el){
			data[el.name] = el.value.toUpperCase();
		});

		if (data.bg1 === data.bg2)
			return SetPreview(false);
		$.each(data,function(k,v){
			data[k] = $.hex2rgb(v);
		});

		var minDelta = 255 * 4,
			bestMatch = null;
		for (var alpha = 1; alpha <= 255; alpha++){
			var RevRGB1 = reverseRgb(data.bg1, data.blend1, alpha / 255),
				RevRGB2 = reverseRgb(data.bg2, data.blend2, alpha / 255);

			var delta = Math.abs(RevRGB1.r - RevRGB2.r)
				+ Math.abs(RevRGB1.g - RevRGB2.g)
				+ Math.abs(RevRGB1.b - RevRGB2.b);

			if (delta < minDelta){
				minDelta = delta;
				bestMatch = RevRGB1;
			}
		}

		if (bestMatch === null)
			return SetPreview(false);
		SetPreview({
			r: Math.round(bestMatch.r),
			g: Math.round(bestMatch.g),
			b: Math.round(bestMatch.b),
			a: bestMatch.a,
		});
	}

	function SetPreview(rgba){
		var hex = '',
			hexa = '',
			opacity = '';
		if (rgba){
			hex = $.rgb2hex(rgba);
			$preview.css('background-color', hex);
			hex = '#<code class="color-red">'+hex.substring(1,3)+'</code><code class="color-green">'+hex.substring(3,5)+'</code><code class="color-darkblue">'+hex.substring(5,7)+'</code>';
			hexa = hex + '<code>'+(Math.round(255*rgba.a).toString(16).toUpperCase())+'</code>';
			var alpha = $.roundTo(rgba.a, 2);
			rgba = 'rgba(<code class="color-red">'+rgba.r+'</code>, <code class="color-green">'+rgba.g+'</code>, <code class="color-darkblue">'+rgba.b+'</code>, '+alpha+')</span>';
			opacity = (alpha*100)+'% opacity';
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
