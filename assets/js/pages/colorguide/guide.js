(function() {
  'use strict';

  //noinspection JSUnusedLocalSymbols
  let $list = $('.appearance-list');
  const { GUIDE, AppearancePage } = window;

  let copyHash = !$.LocalStorage.get('leavehash'),
    $toggler = $('#toggle-copy-hash'),
    $togglerLabel;
  window.copyHashToggler = function() {
    if (!$toggler.length)
      return;
    $togglerLabel = $toggler.next();
    $toggler.on('click', function() {
      copyHash = $toggler.prop('checked');

      if (!copyHash)
        $.LocalStorage.set('leavehash', 1);
      else $.LocalStorage.remove('leavehash');
    });
    $toggler.prop('checked', copyHash).triggerHandler('click');
    $toggler.enable().parent().removeClass('disabled');
  };
  window.copyHashEnabled = function() {
    return copyHash;
  };

  $('ul.colors').children('li').find('.valid-color').off('mousedown touchstart click').on('click', function(e) {
    e.preventDefault();
    let $this = $(this),
      copy = $this.html().trim();
    if (e.shiftKey){
      let rgb = $.RGBAColor.parse(copy),
        $cg = $this.closest('li'),
        path = [
          (
            !AppearancePage
              ? $cg.parents('li').children().last().children('strong').text().trim()
              : $content.children('h1').text()
          ),
          $cg.children().first().html().replace(/(:\s+)?(<span.*)?$/, ''),
          $this.next().find('.label').html(),
        ];
      return $.Dialog.info(`RGB values for color ${copy}`, `<div class="align-center">${path.join(' &rsaquo; ')}<br><span style="font-size:1.2em">rgb(<code class="color-red">${rgb.red}</code>, <code class="color-green">${rgb.green}</code>, <code class="color-darkblue">${rgb.blue}</code>)</span></div>`);
    }
    if (!copyHash) copy = copy.replace('#', '');
    $.copy(copy, e);
  }).filter(':not(.ctxmenu-bound)').ctxmenu(
    [
      {
        text: `Copy HEX color code`, icon: 'clipboard', 'default': true, click: function() {
          $(this).triggerHandler('click');
        },
      },
      {
        text: 'View RGB values', icon: 'brush', click: function() {
          $(this).triggerHandler({
            type: 'click',
            shiftKey: true,
          });
        },
      },
    ],
    function($el) {
      return `Color: ${$el.attr('title') || $el.next().children('.label').text()}`;
    },
  ).on('mousedown', function(e) {
    if (e.shiftKey)
      e.preventDefault();
  });

  $('.get-swatch').off('click').on('click', getSwatch);
  window.copyHashToggler();

  function getSwatch(e) {
    e.preventDefault();

    let token = window.location.search.match(/token=[a-f\d-]+(&|$)/i);
    if (token)
      token = '?' + token[0];
    else token = '';

    //jshint -W040
    let $li = $(this).closest('[id^=p]'),
      appearanceID = $li.attr('id').substring(1),
      ponyName = (
        !AppearancePage
          ? $li.find('.appearance-name').first()
          : $content.children('h1')
      ).text().trim(),
      pressAi = navigator && navigator.userAgent && /Macintosh/i.test(navigator.userAgent)
        ? '<kbd>fn</kbd><kbd>\u2318</kbd><kbd>F12</kbd> for Big Sur, previous versions use <kbd>\u2318</kbd><kbd>F12</kbd>'
        : '<kbd>Ctrl</kbd><kbd>F12</kbd>',
      $instr = $.mk('div').html(
        `<div class='hidden section ai'>
					<h4>How to import swatches to Adobe Illustrator</h4>
					<ul>
						<li>Because Illustrator uses a proprietary format for swatch files, you must download a script <a href='/dist/Import%20Swatches%20from%20JSON.jsx?v=1.5' download='Import Swatches from JSON.jsx' class='btn typcn typcn-download'>by clicking here</a> to be able to import them from our site. Be sure to save it to an easy to find location because you'll need to browser for it every time you want to import colors.</li>
						<li>Once you have the script, <a href="/cg/v/${appearanceID}s.json${token}" class="btn blue typcn typcn-download">click here</a> to download the <code>.json</code> file that you'll need to use for the import.</li>
						<li>Now that you have the 2 files, open Illustrator, create/open a document, then go to <strong>File &rsaquo; Scripts &rsaquo; Other Script</strong> (or press ${pressAi}) then find the file with the <code>.jsx</code> extension (the one you first downloaded). A dialog will appear telling you what to do next.</li>
						<li>Optional: If you'd like to make using the script faster, you can place it in the <code>&hellip;\\Adobe<wbr>\\Adobe Illustrator *<wbr>\\Presets<wbr>\\*<wbr>\\Scripts</code> folder, and after an Illustrator restart it'll be available as one of the options in the <strong>File &rsaquo; Scripts</strong> submenu.</li>
					</ul>
					<div class="responsive-embed">
						<iframe src="https://www.youtube.com/embed/oobQZ2xiDB8" allowfullscreen async defer></iframe>
					</div>
				</div>
				<div class='hidden section inkscape'>
					<h4>How to import swatches to Inkscape</h4>
					<p>Download <a href='/cg/v/${appearanceID}s.gpl${token}' class='btn blue typcn typcn-download'>this file</a> and place it in the <code>&hellip;\\Inkscape<wbr>\\<wbr>share<wbr>\\<wbr>palettes</code> folder. If you don't plan on using the other swatches, deleting them should make your newly imported swatch easier to find.</p>
					<p>You will most likely have to restart Inkscape for the swatch to show up in the <em>Swatches</em> (<kbd>F6</kbd>) tool window's menu.</p>
					<div class="responsive-embed">
						<iframe src="https://www.youtube.com/embed/zmaJhbIKQqM" allowfullscreen async defer></iframe>
					</div>
				</div>`,
      ),
      $appSelect = $.mk('select')
        .attr('required', true)
        .html('<option value="" selected style="display:none">Choose one</option><option value="inkscape">Inkscape</option><option value="ai">Adobe Illustrator</option>')
        .on('change', function() {
          let $sel = $(this),
            val = $sel.val(),
            $els = $sel.parent().next().children().addClass('hidden');
          if (val)
            $els.filter('.' + val).removeClass('hidden');
        }),
      $SwatchDlForm = $.mk('form').attr('id', 'swatch-save').append(
        $.mk('label').attr('class', 'align-center').append(
          '<span>Choose your drawing program:</span>',
          $appSelect,
        ),
        $instr,
      );

    $.Dialog.info(`Download swatch file for ${ponyName}`, $SwatchDlForm);
  }


  const $searchForm = $('#search-form');
  $searchForm.on('reset', function(e) {
    e.preventDefault();

    let $this = $(this);
    $this.find('input[name=q]').val('');
    $this.trigger('submit');
  });
  const $searchInput = $searchForm.children('input');
  const appearanceAutocompleteCache = new window.KeyValueCache();
  if ($searchInput.length){
    const focused = $searchInput.is(':focus');
    $searchInput.autocomplete(
      {
        hint: false,
        minLength: 1,
      },
      [
        {
          name: 'appearance',
          debounce: 200,
          source: (q, callback) => {
            if (appearanceAutocompleteCache.has(q))
              return callback(appearanceAutocompleteCache.get(q));
            $.API.get(`/cg/appearances`, { q, guide: GUIDE }, data => {
              callback(appearanceAutocompleteCache.set(q, data));
            });
          },
          templates: {
            header: () => `<div class="aa-header">
							Highlight suggestions with <kbd><span class="typcn typcn-arrow-up"/></kbd>
							<kbd><span class="typcn typcn-arrow-down"/></kbd>, then press <kbd>Enter</kbd> to accept.
							Pressing <kbd>Enter</kbd> without highlighting a result will take you to the search results page.
						</div>`,
            suggestion: data => {
              return $('<a>').attr('href', data.url).append(
                `<img src="${data.image}" alt="search suggestion" class="ac-appearance-image">`,
                $('<div class="ac-appearance-label" />').text(data.label),
              ).prop('outerHTML');
            },
          },
        },
      ],
    );
    if (focused)
      $searchInput.focus();
    $searchInput.on('autocomplete:selected', function(event, suggestion, dataset, context) {
      if (context && context.selectionMethod === 'click'){
        return;
      }
      if (suggestion.url){
        $searchForm.find(':input').prop('disabled', true);
        window.location.href = suggestion.url;
      }
    });
  }
})();
