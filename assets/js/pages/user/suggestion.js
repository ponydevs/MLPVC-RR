(function() {
  'use strict';

  let $pendingReservations = $('.pending-reservations'),
    already_loaded = [];
  $pendingReservations.on('click', '#suggestion', function(e) {
    e.preventDefault();

    $.Dialog.info(
      'Request Roulette™',
      `<p>If you feel like making a vector but don't have any specific screencap in mind then you are in the right place.</p>
			<p>With this tool you can get a random request from the site instantly delivered straight to your screen. Anyone can ask for suggestions, and Club Members are given the option to reserve any suggestions they feel like completing with a single click.</p>
			<p>Once you dismiss a post here by clicking the button repeatedly it will not be suggested to you again until you reload the page. If you are persistent enough you may find that the site runs out of requests to suggest eventually.</p>
			<div class="align-center"><button id="suggestion-press" class="btn large orange typcn typcn-lightbulb">Give me a suggestion</button></button>`,
      function() {
        let $btn = $('#suggestion-press'),
          $output = $.mk('ul', 'suggestion-output').insertAfter($btn),
          $loadNotice = $.mk('div').addClass('notice fail').hide().text('The image failed to load - just click the button again to get a different suggestion.').insertAfter($output);
        $btn.on('click', function(e) {
          e.preventDefault();

          $btn.disable();

          $.API.get('/post/request/suggestion', { already_loaded: already_loaded.join(',') }, function() {
            if (!this.status){
              $btn.enable();
              return $.Dialog.fail(false, this.message);
            }

            let $result = $(this.suggestion),
              postID = parseInt($result.attr('id').split('-')[1], 10);

            already_loaded.push(postID);

            $result.find('img').on('load', function() {
              let $this = $(this);
              $this.parents('.image').addClass('loaded');
              $this.parents('a').fluidboxThis();
            }).on('error', function() {
              $loadNotice.show();
              $(this).parents('.image').hide();
            });
            $result.find('.reserve-request').on('click', function() {
              let $this = $(this);
              $.API.post(`/post/${postID}/reservation`, { from: 'suggestion' }, function() {
                if (!this.status) return $.Dialog.fail(false, this.message);

                $this.replaceWith(this.button);
                $pendingReservations.html($(this.pendingReservations).children());
              });
            });
            $output.html($result);
            $btn.enable();
          }).fail(function() {
            $btn.enable();
          });
        });
      },
    );
  });
})();
