/**
 * http://blog.blakesimpson.co.uk/read/51-swipe-js-detect-touch-direction-and-distance
 */
(function($) {
  'use strict';

  $.fn.swipe = function(callback) {
    let touchDown = false,
      originalPosition = null,
      $el = $(this);

    function swipeInfo(event) {
      const source = event.originalEvent.touches ? event.originalEvent.touches[0] : event.originalEvent;

      let x = source.pageX,
        y = source.pageY,
        dx, dy;

      dx = (x > originalPosition.x) ? 'right' : 'left';
      dy = (y > originalPosition.y) ? 'down' : 'up';

      return {
        direction: {
          x: dx,
          y: dy,
        },
        offset: {
          x: x - originalPosition.x,
          y: originalPosition.y - y,
        },
      };
    }

    $el.on('touchstart mousedown', function(event) {
      touchDown = true;

      const source = event.originalEvent.touches ? event.originalEvent.touches[0] : event.originalEvent;
      originalPosition = {
        x: source.pageX,
        y: source.pageY,
      };
    });

    $el.on('touchend mouseup', function() {
      touchDown = false;
      originalPosition = null;
    });

    $el.on('touchmove mousemove', function(event) {
      if (!touchDown){
        return;
      }
      var info = swipeInfo(event);
      callback(info.direction, info.offset);
    });

    return true;
  };
})(jQuery);
