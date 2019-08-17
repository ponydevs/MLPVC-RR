(function() {
  'use strict';

  const
    $butwhy = $('#butwhy'),
    $thisiswhy = $('#thisiswhy');
  $butwhy.on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();

    $butwhy.addClass('hidden');
    $thisiswhy.removeClass('hidden');
  });
})();
