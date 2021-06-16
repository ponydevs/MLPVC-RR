(function() {
  'use strict';

  const { PRINTABLE_ASCII_PATTERN } = window;

  let $eventEntries = $('#event-entries'),
    $entryForm = $.mk('form', 'new-entry').append(
      `<label>
			<span>Entry link</span>
			<input type="url" name="link" required>
		</label>
		<div class="notice info">This must point to a deviation on DeviantArt or a Sta.sh upload. A Sta.sh link will not be visible to the public, so use that if you do not want to share the source file with anyone other than the staff. You only need to submit the source file, we'll take care of the rest.</div>`,
      $.mk('label').append(
        `<span>Entry title</span>`,
        $.mk('input').attr({
          type: 'text',
          name: 'title',
          required: true,
          pattern: PRINTABLE_ASCII_PATTERN.replace('+', '{2,64}'),
          minlength: 2,
          maxlength: 64,
        }),
      ),
      `<div class="notice info">Here you can enter the name of the character you're submitting for example.</div>
		<label>
			<span>Preview (optional)</span>
			<input type="url" name="prev_src">
		</label>
		<div class="notice info">You can link to a preview of your submission from any of the <a href="/about#supported-providers" target="_blank">supported image providers</a>. This will be displayed alongside your submission on the event page. You should only use this if your submission doesn't have a preview of its own.</div>`,
    );

  $.fn.rebindFluidbox = function() {
    this.find('.preview > a:not(.fluidbox--initialized)')
      .fluidboxThis();

    return this;
  };

  $eventEntries.rebindFluidbox().on('click', '.edit-entry', function(e) {
    e.preventDefault();

    let $li = $(this).closest('[id^=entry-]'),
      entryID = $li.attr('id').split('-')[1];

    $.Dialog.wait(`Editing entry #${entryID}`, 'Retrieving entry details from server');

    $.API.get(`/event/entry/${entryID}`, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      let data = this;

      $.Dialog.request(false, $entryForm.clone(), 'Save', function($form) {
        if (data.link)
          $form.find('input[name="link"]').val(data.link);
        if (data.title)
          $form.find('input[name="title"]').val(data.title);
        if (data.prev_src)
          $form.find('input[name="prev_src"]').val(data.prev_src);
        $form.on('submit', function(e) {
          e.preventDefault();

          let data = $form.mkData();
          $.Dialog.wait(false, 'Saving changes');

          $.API.put(`/event/entry/${entryID}`, data, function() {
            if (!this.status) return $.Dialog.fail(false, this.message);

            $li.html(this.entryhtml).rebindFluidbox();
            $.Dialog.close();
          });
        });
      });
    });
  });

  $eventEntries.on('click', '.delete-entry', function(e) {
    e.preventDefault();

    let $li = $(this).closest('[id^=entry-]'),
      entryID = $li.attr('id').split('-')[1],
      title = $li.find('.label').text().trim();

    $.Dialog.confirm(`Withdraw entry #${entryID}`, `Are you sure you want to withdraw the entry <q>${title}</q>?`, function(sure) {
      if (!sure) return;

      $.Dialog.wait(false, 'Sending deletion request');

      $.API.delete(`/event/entry/${entryID}`, function() {
        if (!this.status) return $.Dialog.fail(false, this.message);

        $.Dialog.close();
        $li.fadeOut(500, function() {
          $li.remove();
        });
      });
    });
  });

  const entryIO = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting)
        return;

      const el = entry.target;
      entryIO.unobserve(el);

      const { entryid } = el.dataset;

      $.API.get(`/event/entry/${entryid}/lazyload`, function() {
        if (!this.status) return $.Dialog.fail(`Failed to load preview for entry #${entryid}`, this.message);

        $.loadImages(this.html).then(function(resp) {
          const $el = $(el);
          const $parent = $el.closest('li[id]');
          $el.replaceWith(resp.$el);
          $parent.rebindFluidbox();
        });
      });
    });
  });

  $('.entry-deviation-promise').each((_, el) => entryIO.observe(el));
})();
