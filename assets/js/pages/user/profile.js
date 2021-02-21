(function() {
  'use strict';
  const { username, userId } = window;

  $('.personal-cg-say-what').on('click', function(e) {
    e.preventDefault();

    $.Dialog.info('About Personal Color Guides',
      `<p>The Personal Color Guide is a place where you can store and share colors for any of your OCs, similar to our <a href="/cg/">Official Color Guide</a>. You have full control over the colors and other metadata for any OCs you add to the system, and you can chose to keep your PCG publicly visible on your profile or make individual appearances private and share them with only certain people using a direct link.</p>
			<p><em>&ldquo;So where's the catch?&rdquo;</em> &mdash; you might ask. Everyone starts with 1 slot (10 points), which lets you add a single OC to your personal guide. This limit can be increased by joining <a href="https://www.deviantart.com/mlp-vectorclub">our group</a> on DeviantArt, then fulfilling requests on this website. You will be given a point for every request you finish and we approve. In order to get an additional slot, you will need 10 points, which means 10 requests.</p>
			<br>
			<p><strong>However</strong>, there are a few things to keep in mind:</p>
			<ul>
				<li>You may only add characters made by you, for you, or characters you've purchased to your Personal Color Guide. If we're asked to remove someone else's character from your guide we'll certainly comply. Please stick to species canon to the show, we're a pony community after all.</li>
				<li>Finished requests only count toward additional slots after they have been submitted to the group and have been accepted to the gallery. This is indicated by a tick symbol (<span class="color-green typcn typcn-tick"></span>) on the post throughout the site.</li>
				<li>A finished request does not count towards additional slots if you were the one who requested it in the first place. We're not against this behaviour generally, but allowing this would defeat the purpose of this feature: encouraging members to help others.</li>
				<li>Do not attempt to abuse the system in any way. Exploiting any bugs you may encounter instead of <a class="send-feedback">reporting them</a> could result in us disabling your access to this feature.</li>
			</ul>`,
    );
  });

  let $pendingRes = $('.pending-reservations');
  if ($pendingRes.length){
    $pendingRes.on('click', 'button.cancel', function() {
      let $btn = $(this),
        $link = $btn.prev();
      $.Dialog.confirm('Cancel reservation', 'Are you sure you want to cancel this reservation?', function(sure) {
        if (!sure) return;

        $.Dialog.wait(false, 'Cancelling reservation');

        let postId = $link.prop('hash').substring(1).split('-')[1];
        $.API.delete(`/post/${postId}/reservation`, { from: 'profile' }, function() {
          if (!this.status) return $.Dialog.fail(false, this.message);

          let pendingRes = this.pendingReservations;
          $btn.closest('li').fadeOut(1000, function() {
            $(this).remove();
            if (pendingRes){
              $pendingRes.html($(pendingRes).children());
              Time.update();
            }
          });
          $.Dialog.close();
        });
      });
    });
    $pendingRes.on('click', 'button.fix', function() {
      let $btn = $(this),
        $link = $btn.next(),
        id = $link.prop('hash').substring(1).split('-').pop(),
        $ImgUpdateForm = $.mk('form').attr('id', 'img-update-form').append(
          $.mk('label').append(
            $.mk('span').text('New image URL'),
            $.mk('input').attr({
              type: 'text',
              maxlength: 255,
              pattern: '^.{2,255}$',
              name: 'image_url',
              required: true,
              autocomplete: 'off',
              spellcheck: 'false',
            }),
          ),
        );
      $.Dialog.request(`Update image of post #${id}`, $ImgUpdateForm, 'update', function($form) {
        $form.on('submit', function(e) {
          e.preventDefault();

          let data = $form.mkData();
          $.Dialog.wait(false, 'Replacing image');

          $.API.put(`/post/${id}/image`, data, function() {
            if (!this.status) return $.Dialog.fail(false, this.message);

            $.Dialog.success(false, 'Image has been updated');
            $.Navigation.reload(true);
          });
        });
      });
    });
  }

  const $givePCGPoints = $('#give-pcg-points');
  if ($givePCGPoints.length){
    $givePCGPoints.on('click', function(e) {
      e.preventDefault();

      $.Dialog.wait('Giving PCG points to ' + username, 'Checking user\'s total points');

      $.API.get(`/user/${userId}/pcg/points`, function() {
        const $GiveForm = $.mk('form', 'pcg-point-give-form').append(
          $.mk('label').append(
            `<p>Choose how many <strong>points</strong> you want to give. Enter a negative number to take points. You cannot take more points than what the user has, and the free slot cannot be taken away.</p><p><strong>Remember, 10 points = 1 slot!</strong></p>`,
            $.mk('input').attr({
              type: 'number',
              name: 'amount',
              step: 1,
              min: -this.amount,
              'class': 'large-number-input',
              required: true,
            }),
          ),
          $.mk('label').append(
            `<p>Comment (optional, 2-140 chars.)</p>`,
            $.mk('textarea').attr({
              name: 'comment',
              maxlength: 255,
            }),
          ),
        );
        $.Dialog.request(false, $GiveForm, 'Continue', function($form) {
          $form.on('submit', function(e) {
            e.preventDefault();

            const data = $form.mkData();
            if (isNaN(data.amount))
              return $.Dialog.fail(false, 'Invalid amount entered');
            data.amount = parseInt(data.amount, 10);
            if (data.amount === 0)
              return $.Dialog.fail(false, 'You have to enter an integer that isn\'t 0');
            const
              absAmount = Math.abs(data.amount),
              s = absAmount === 1 ? '' : 's',
              giving = data.amount > 0,
              give = giving ? 'give' : 'take',
              to = giving ? 'to' : 'from';
            $.Dialog.confirm(false, `<p>You are about to ${give} <strong>${absAmount} point${s}</strong> ${to} <strong>${username}</strong>. The point${s} will be ${give}n ${to} them immediately, and they will not receive any notification on the site.</p><p>Are you sure?</p>`, [`${$.capitalize(give)} ${absAmount} point${s} ${to} ${username}`, 'Cancel'], sure => {
              if (!sure) return;

              $.Dialog.wait(false, 'Giving points');

              $.API.post(`/user/${userId}/pcg/points`, data, function() {
                if (!this.status) return $.Dialog.fail(false, this.message);

                $.Dialog.segway(false, this.message);
              });
            });
          });
        });
      });
    });
  }

  const deviationIO = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting)
        return;

      const el = entry.target;
      deviationIO.unobserve(el);

      const { postId, viewonly } = el.dataset;

      $.API.get(`/post/${postId}/lazyload`, { viewonly }, ({ status, message, html }) => {
        const $el = $(el);
        if (!status){
          $el.trigger('error');
          return $.Dialog.fail(`Cannot load post ${postId}`, message);
        }

        $.loadImages(html).then(function(resp) {
          const $li = $el.closest('li[id]');
          $li.children('.image').replaceWith(resp.$el);
          const title = $li.children('.image').find('img').attr('alt');
          if (title)
            $li.children('.label').removeClass('hidden').find('a').text(title);
        });
      });
    });
  });

  $('.post-deviation-promise').each((_, el) => deviationIO.observe(el));

  $('.awaiting-approval').on('click', 'button.check', function(e) {
    e.preventDefault();

    const
      $li = $(this).parents('li'),
      id = $li.attr('id').split('-').pop();

    $.Dialog.wait('Deviation acceptance status', 'Checking');

    $.API.post(`/post/${id}/approval`, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      $li.remove();
      $.Dialog.success(false, this.message, true);
    });
  });

  function settingChanged(which, from, to_what) {
    switch (which){
      case 'p_vectorapp':
        if (to_what.length === 0 && from.length !== 0){
          let className = `app-${from}`;
          $(`.${className}`).removeClass(className);
          $('.title h1 .vectorapp-logo').remove();
          $.Dialog.close();
          return;
        }

        $.Navigation.reload(true);
        break;
      case 'p_hidediscord':{
        let $discordBtn = $sidebar.find('.welcome .discord-join');
        if (to_what){
          if ($discordBtn.length)
            $discordBtn.remove();
        }
        else if (!$discordBtn.length)
          $sidebar.find('.welcome .buttons').append('<a class="btn typcn discord-join" href="http://fav.me/d9zt1wv" target="_blank">Join Discord</a>');
        $.Dialog.close();
      }
        break;
      case 'p_disable_ga':{
        if (to_what){
          $.Dialog.wait(false, 'Performing a hard reload to remove user ID from the tracking code');
          return window.location.reload();
        }
        $.Dialog.close();
      }
        break;
      case 'p_hidepcg':
        $.Dialog.wait('Navigation', 'Reloading page');
        $.Navigation.reload();
        break;
      default:
        $.Dialog.close();
    }
  }

  const
    $settings = $('#settings'),
    $slbl = $settings.find('form > label');

  $settings.on('submit', 'form', function(e) {
    e.preventDefault();

    let $form = $(this),
      endpoint = $form.attr('action'),
      data = $form.mkData(),
      $input = $form.find('[name="value"]'),
      orig = $input.data('orig');

    $.Dialog.wait('Saving setting', 'Please wait');

    $.API.put(endpoint, data, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      if ($input.is('[type=number]'))
        $input.val(this.value);
      else if ($input.is('[type=checkbox]')){
        this.value = Boolean(this.value);
        $input.prop('checked', this.value);
      }
      const newOrig = this.value === null ? '' : this.value;
      $input.data('orig', newOrig).triggerHandler('change');

      settingChanged(endpoint.split('/').pop(), orig, this.value);
    });
  });
  $slbl.children('input[type=number]').each(function() {
    let $el = $(this);
    $el.data('orig', parseInt($el.val().trim(), 10)).on('keydown keyup change', function() {
      let $el = $(this);
      $el.siblings('.save').prop('disabled', parseInt($el.val().trim(), 10) === $el.data('orig'));
    });
  });
  $slbl.children('input[type=checkbox]').each(function() {
    let $el = $(this);
    $el.data('orig', $el.prop('checked')).on('keydown keyup change', function() {
      let $el = $(this);
      $el.siblings('.save').prop('disabled', $el.prop('checked') === $el.data('orig'));
    });
  });
  $slbl.children('select').each(function() {
    let $el = $(this);
    $el.data('orig', $el.find('option:selected').val()).on('keydown keyup change', function() {
      let $el = $(this),
        $val = $el.find('option:selected');
      $el.siblings('.save').prop('disabled', $val.val() === $el.data('orig'));
    });
  });
})();
