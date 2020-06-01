(function() {
  'use strict';

  const { AppearanceID, AppearanceColors, SpriteColorList } = window;
  let $Table = $.mk('table').appendTo($('#input-cont').empty()),
    $SVG,
    AppearanceColorObject = {},
    AppearanceColorIterator = 1,
    mapColor = function(key, val) {
      let result = [];
      for (let i = 0, l = AppearanceColors.length; i < l; i++){
        let el = AppearanceColors[i];
        if (el[key] === val){
          result.push(el);
          AppearanceColors[i].detected = true;
        }
      }
      if (!result.length)
        result.push({
          label: '(unrecognized color)',
          hex: '',
        });
      return result;
    };

  $.each(AppearanceColors, (_, color) => {
    AppearanceColorObject[color.label] = AppearanceColorIterator++;
  });

  $.ajax({
    url: `/cg/v/${AppearanceID}s.svg?t=` + Math.round(new Date().getTime() / 1000),
    dataType: 'html',
    success: function(data) {
      $SVG = $(data);
      $('#svg-cont').html($SVG);
      $SVG.children().each(function() {
        let $el = $(this),
          stroke = $el.attr('stroke');
        $el.addClass($.RGBAColor.parse(stroke).isLight() ? 'bright' : 'dark');
        $el.on('mouseenter', function() {
          $el.addClass('highlight');
          $Table.find('td.color').filter(function() {
            return this.innerHTML.trim() === stroke;
          }).parent().addClass('force-hover');
        }).on('mouseleave', function() {
          $el.removeClass('highlight');
          $Table.find('.force-hover').removeClass('force-hover');
        });
      });
    },
  });

  $.each(SpriteColorList, function(index, actual) {
    let matchingColors = mapColor('hex', actual), labels = [];
    $.each(matchingColors, (_, color) => {
      labels.push(`<li>${color.label}</li>`);
    });
    $Table.append(
      $.mk('tr').html(
        `<td class="color-preview" style="background-color:${actual}"></td>
				<td class="label"><ul>${labels.join('')}</ul></td>
				<td class="color">${actual}</td>`,
      ).on('mouseenter', function() {
        if ($SVG instanceof jQuery)
          $SVG.children().filter(`[stroke="${actual}"]`).addClass('highlight');
      }).on('mouseleave', function() {
        if ($SVG instanceof jQuery)
          $SVG.find('.highlight').removeClass('highlight');
      }),
    );
  });
  $Table.children('tr').sort(function(a, b) {
    let at = AppearanceColorObject[$(a).children('td.label li').first().text()] || -1,
      bt = AppearanceColorObject[$(b).children('td.label li').first().text()] || -1;

    return at === bt ? 0 : (at < bt ? -1 : 1);
  }).prependTo($Table);
  $.each(AppearanceColors, function(_, color) {
    if (color.detected || /^(Mannequin|Teeth & Mouth|Tears|Eyes?)\s\|/.test(color.label))
      return;
    let matchingColors = mapColor('hex', color.hex), labels = [];
    $.each(matchingColors, (_, color) => {
      labels.push(`<li>${color.label}</li>`);
    });
    $Table.prepend(
      $.mk('tr').html(
        `<td class="color-preview" style="background-color:${color.hex}"></td>
				<td class="label missing"><ul>${labels.join('')}</ul></td>
				<td class="color">${color.hex}</td>`,
      ),
    );
  });

  $('#server-side-check').on('click', e => {
    e.preventDefault();

    $.Dialog.wait('Server-side sprite color check');

    $.API.post(`/cg/appearance/${AppearanceID}/sprite/check-colors`, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      $.Dialog.segway(false, this.message);
    });
  });
})();
