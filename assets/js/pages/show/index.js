(function() {
  'use strict';

  let $eptableBody = $('#content').children('table').children('tbody');
  $eptableBody.on('updatetimes', function() {
    $eptableBody.children().children(':last-child').children('time.nodt').each(function() {
      this.innerHTML = moment($(this).attr('datetime')).format('D-MMMM-YYYY H:mm:ss').replace(/:00$/, '');
    });
  }).trigger('updatetimes');
})();
