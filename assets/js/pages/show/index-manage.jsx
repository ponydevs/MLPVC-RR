(function() {
  'use strict';

  const { SHOW_TYPES, EP_TITLE_REGEX } = window;
  let $tables = $('#content').find('table');

  /*!
   * Timezone data string taken from:
   * http://momentjs.com/downloads/moment-timezone-with-data.js
   * version 0.4.1 by Tim Wood, licensed MIT
   */
  moment.tz.add('America/Los_Angeles|PST PDT PWT PPT|80 70 70 70|010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261q0 1nX0 11B0 1nX0 SgN0 8x10 iy0 5Wp0 1Vb0 3dB0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0');

  const setSat830 = ts => moment.tz(ts, 'America/Los_Angeles').set({ day: 'Saturday', h: 8, m: 30, s: 0 }).local();
  let saturday = setSat830(new Date());
  const sat_date = $.momentToYMD(saturday);
  const sat_time = $.momentToHM(saturday);
  const sat_day = saturday.format('dddd');

  function EpisodeForm(id) {
    const typeOptions = [];
    $.each(SHOW_TYPES, (type, name) => {
      if (type === 'episode')
        return '';


      typeOptions.push(`<option value="${type}">${name}</option>`);
    });
    let $form = $.mk('form').attr('id', id).append(
      `<div class="label episode-only">
				<span>Season, Episode & Overall #</span>
				<div class=input-group-3>
					<input type="number" min="1" max="9" name="season" placeholder="Season #" required>
					<input type="number" min="1" max="26" name="episode" placeholder="Episode #" required>
					<input type="number" min="1" name="no" placeholder="Overall #" required>
				</div>
			</div>
			<input class="episode-only" type="hidden" name="type" value="episode">
			<label class="episode-only"><input type="checkbox" name="twoparter"> Has two parts</label>
			<div class="notice info align-center episode-only">
				<p>If this is checked, enter the episode number of the first part</p>
			</div>
			<div class="label movie-only">
				<span>Type</span>
				<select name="type" required>
					<option value="" hidden selected>(choose one)</option>
					<optgroup label="Available types">
						${typeOptions.join('')}
					</optgroup>
				</select>
			</div>
			<div class="label movie-only">
				<span>Overall number</span>
				<input type="number" min="1" name="no" placeholder="Overall #" required>
			</div>`,
      $.mk('label').append(
        '<span>Title (5-35 chars.)</span>',
        $.mk('input').attr({
          type: 'text',
          minlength: 5,
          name: 'title',
          placeholder: 'Title',
          autocomplete: 'off',
          required: true,
        }).patternAttr(EP_TITLE_REGEX),
      ),
      `<div class="notice info align-center movie-only">
				<p>Include "Equestria Girls: " if applicable. Prefixes don't count towards the character limit.</p>
			</div>
			<div class="label">
				<span>Air date & time</span>
				<div class="input-group-2">
					<input type="date" name="airdate" placeholder="YYYY-MM-DD" required>
					<input type="time" name="airtime" placeholder="HH:MM" required>
				</div>
			</div>
			<div class="notice info align-center button-here">
				<p>Specify the <span class="episode-only">episode</span><span class="movie-only">movie</span>'s air date and time in <strong>your computer's timezone</strong>.</p>
			</div>
			<div class="label">
				<span>Notes (optional, raw HTML, 1000 chars. max)</span>
				<div class="code-editor"></div>
			</div>`,
    );

    $.mk('button').attr('class', 'episode-only').text('Set time to ' + sat_time + ' this ' + sat_day).on('click', function(e) {
      e.preventDefault();
      $(this).parents('form').find('input[name="airdate"]').val(sat_date).next().val(sat_time);
    }).appendTo($form.children('.button-here'));

    return $form;
  }

  let $AddEpFormTemplate = new EpisodeForm('addep'),
    $EditEpFormTemplate = new EpisodeForm('editep');

  $('#add-episode, #add-show').on('click', function(e) {
    e.preventDefault();

    const is_episode = /episode/.test(this.id);
    let $AddEpForm = $AddEpFormTemplate.clone(true, true);
    $AddEpForm.find(is_episode ? '.movie-only' : '.episode-only').remove();

    if (is_episode)
      $AddEpForm.prepend(
        $.mk('div').attr('class', 'align-center').html(
          $.mk('button').attr('class', 'typcn typcn-flash blue').text('Pre-fill based on last added').on('click', e => {
            const
              $this = $(e.target),
              $form = $this.closest('form');

            $this.disable();

            $.API.get('/show/prefill', function() {
              if (!this.status) return $.Dialog.fail(false, this.message);

              let airs = setSat830(this.airday);
              $.each({
                airdate: $.momentToYMD(airs),
                airtime: $.momentToHM(airs),
                episode: this.episode,
                season: this.season,
                no: this.no,
              }, (name, value) => {
                $form.find(`[name=${name}]`).val(value);
              });
            }).always(function() {
              $this.enable();
            });
          }),
        ),
      );

    $.Dialog.request($(this).text(), $AddEpForm, 'Add', function($form) {
      let notesEditor = $.renderCodeMirror({
        $el: $form.find('.code-editor'),
        mode: 'html',
      });

      $form.on('submit', function(e) {
        e.preventDefault();
        let airdate = $form.find('input[name=airdate]').disable().val(),
          airtime = $form.find('input[name=airtime]').disable().val(),
          airs = $.mkMoment(airdate, airtime).toISOString(),
          data = $(this).mkData({ airs });
        data.notes = notesEditor.getValue();

        const what = is_episode ? 'episode' : 'show entry';
        $.Dialog.wait(false, `Adding ${what} to database`);

        $.API.post('/show', data, function() {
          if (!this.status) return $.Dialog.fail(false, this.message);

          $.Dialog.wait(false, `Opening ${what} page`, true);

          $.Navigation.visit(this.url);
        });
      });
    });
  });

  function EditEp(e) {
    e.preventDefault();

    let $this = $(this),
      EpisodePage = Boolean($this.attr('id')),
      id = EpisodePage
        ? window.SHOW_ID
        : $this.closest('tr').attr('data-id');

    $.Dialog.wait(`Editing show entry #${id}`);

    const endpoint = `/show/${id}`;
    $.API.get(endpoint, function() {
      if (!this.status) return $.Dialog.fail(false, this.message);

      const { show } = this;

      const isEpisode = show.season !== null;

      let $EditEpForm = $EditEpFormTemplate.clone(true, true);
      $EditEpForm.find(isEpisode ? '.movie-only' : '.episode-only').remove();

      if (isEpisode)
        $EditEpForm.find('input[name=twoparter]').prop('checked', !!show.twoparter);
      delete show.twoparter;

      let d = moment(show.airs);
      show.airdate = $.momentToYMD(d);
      show.airtime = $.momentToHM(d);

      const notes = show.notes;
      delete show.notes;

      $.each(show, function(k, v) {
        $EditEpForm.find(`:input[name=${k}]`).val(v);
      });

      $.Dialog.request(`Editing ${show.type} #${show.id}`, $EditEpForm, 'Save', function($form) {
        let notesEditor = $.renderCodeMirror({
          $el: $form.find('.code-editor'),
          mode: 'html',
          value: notes,
        });

        $form.on('submit', function(e) {
          e.preventDefault();

          let data = $(this).mkData(),
            d = $.mkMoment(data.airdate, data.airtime);
          delete data.airdate;
          delete data.airtime;
          data.airs = d.toISOString();
          data.notes = notesEditor.getValue();

          $.Dialog.wait(false, 'Saving changes');

          $.API.put(endpoint, data, function() {
            if (!this.status) return $.Dialog.fail(false, this.message);

            $.Dialog.wait(false, 'Updating page', true);
            $.Navigation.reload();
          });
        });
      });
    });
  }

  $content.on('click', '#edit-show', EditEp);
  $tables.on('click', '.edit-show', EditEp).on('click', '.delete-show', function(e) {
    e.preventDefault();

    let $this = $(this),
      $tr = $this.closest('tr'),
      id = $tr.attr('data-id'),
      type = $tr.attr('data-type');

    $.Dialog.confirm(`Deleting ${type} #${id}`, `<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the ${type}, too.</p><p>Are you sure you want to delete it?</p>`, function(sure) {
      if (!sure) return;

      $.Dialog.wait(false, 'Removing episode');

      $.API.delete(`/show/${id}`, function() {
        if (!this.status) return $.Dialog.fail(false, this.message);

        $.Navigation.reload(true);
      });
    });
  });
})();
