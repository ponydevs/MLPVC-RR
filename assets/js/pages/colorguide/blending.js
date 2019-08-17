(function() {
  'use strict';

  function reverseComponent(backgroundC, blendedC, alpha) {
    return (blendedC - (1 - alpha) * backgroundC) / alpha;
  }

  function reverseRgb(bg, blend, alpha) {
    return {
      red: reverseComponent(bg.red, blend.red, alpha),
      green: reverseComponent(bg.green, blend.green, alpha),
      blue: reverseComponent(bg.blue, blend.blue, alpha),
      alpha,
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

  $inputs.on('keyup change input', function() {
    let $cp = $(this).prev(),
      value = $.RGBAColor.parse(this.value);
    if (value !== null)
      $cp.removeClass('invalid').css('background-color', value.toHex());
    else $cp.addClass('invalid');

    $form.triggerHandler('submit');
  }).on('paste blur', function(e) {
    let $input = $(this),
      f = function() {
        let val = $.RGBAColor.parse($input.val());
        if (val !== null){
          $input.val(val.toHex()).trigger('change');
          if (e.type !== 'blur')
            $input.next().focus();
        }
      };
    if (e.type === 'paste') setTimeout(f, 10);
    else f();
  }).trigger('change');
  $form.on('submit', Calculate).triggerHandler('submit');
  $form.on('click', 'td', function(e) {
    if (!e.shiftKey)
      return true;
    let $hexInput = $(this).find('.clri');
    if ($hexInput.length === 0)
      return true;
    e.preventDefault();

    let hex = $hexInput.val(),
      $prev = $.mk('div').attr('class', 'preview').css('background-color', hex),
      OrigRGB = $.RGBAColor.parse(hex),
      $formInputs = $.mk('div').attr('class', 'input-group-3').html(
        `<input type='number' class='color-red' name='r' min='0' max='255' step='1' value='${OrigRGB.red}'>
				<input type='number' class='color-green' name='g' min='0' max='255' step='1' value='${OrigRGB.green}'>
				<input type='number' class='color-darkblue' name='b' min='0' max='255' step='1' value='${OrigRGB.blue}'>`,
      );

    $formInputs.children().on('keyup change input mouseup', function() {
      let $form = $(this).closest('form');
      $form.children('.preview').css('background-color', $.rgb2hex($form.mkData()));
    });
    let $EnterRGBForm = $.mk('form', 'enter-rgb').append($formInputs, $prev);

    $.Dialog.setFocusedElement($hexInput);
    $.Dialog.request('Enter RGB values', $EnterRGBForm, 'Set', function($form) {
      $form.on('submit', function(e) {
        e.preventDefault();

        $hexInput.val($.rgb2hex($form.mkData())).trigger('change');
        $.Dialog.close();
      });
    });
  });

  function Calculate(e) {
    e.stopPropagation();

    let $validInputs = $inputs.filter(':valid');
    if ($validInputs.length !== 4)
      return setPreview(false);

    let data = {};
    $validInputs.each(function(_, el) {
      data[el.name] = el.value.toUpperCase();
    });

    if (data.bg1 === data.bg2)
      return setPreview(false);
    $.each(data, function(k, v) {
      data[k] = $.RGBAColor.parse(v);
    });

    let minDelta = 255 * 4,
      bestMatch = null;
    for (let alpha = 1; alpha <= 255; alpha++){
      let RevRGB1 = reverseRgb(data.bg1, data.blend1, alpha / 255),
        RevRGB2 = reverseRgb(data.bg2, data.blend2, alpha / 255);

      let delta = Math.abs(RevRGB1.red - RevRGB2.red)
        + Math.abs(RevRGB1.green - RevRGB2.green)
        + Math.abs(RevRGB1.blue - RevRGB2.blue);

      if (delta < minDelta){
        minDelta = delta;
        bestMatch = RevRGB1;
      }
    }

    if (bestMatch === null)
      return setPreview(false);
    $deltaWarn[minDelta > 10 ? 'show' : 'hide']();
    setPreview({
      red: Math.round(bestMatch.red),
      green: Math.round(bestMatch.green),
      blue: Math.round(bestMatch.blue),
      alpha: bestMatch.alpha,
    });
  }

  function setPreview(rgba) {
    let hex = '',
      hexa = '',
      opacity = '';
    if (rgba !== false){
      hex = $.rgb2hex(rgba);
      $preview.css('background-color', hex);
      hex = `#<code class="color-red">${hex.substring(1, 3)}</code><code class="color-green">${hex.substring(3, 5)}</code><code class="color-darkblue">${hex.substring(5, 7)}</code>`;
      hexa = hex + `<code>${Math.round(255 * rgba.alpha).toString(16).toUpperCase()}</code>`;
      let alpha = $.roundTo(rgba.alpha, 2);
      rgba = `rgba(<code class="color-red">${rgba.red}</code>, <code class="color-green">${rgba.green}</code>, <code class="color-darkblue">${rgba.blue}</code>, ${alpha})</span>`;
      opacity = `${alpha * 100}% opacity`;
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
})();
