/* global DocReady,moment,HandleNav,$content */
DocReady.push(function EpisodesManage(){
	'use strict';
	let $eptableBody = $('#episodes').children('tbody'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;
	Bind.call({init:true});

	/*!
	 * Timezone data string taken from:
	 * http://momentjs.com/downloads/moment-timezone-with-data.js
	 * version 0.4.1 by Tim Wood, licensed MIT
	 */
	moment.tz.add("America/Los_Angeles|PST PDT PWT PPT|80 70 70 70|010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261q0 1nX0 11B0 1nX0 SgN0 8x10 iy0 5Wp0 1Vb0 3dB0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0");

	let saturday = moment.tz(new Date(), "America/Los_Angeles").set({
		day: 'Saturday',
		h: 8, m: 30, s: 0,
	}).local();
	function mkDate(datestr, timestr, utc){
		return moment(datestr+'T'+timestr+(utc?'Z':''));
	}
	const DateToAirDate = date => date.format('YYYY-MM-DD');
	const DateToAirTime = date => date.format('HH:mm');
	const sat_date = DateToAirDate(saturday);
	const sat_time = DateToAirTime(saturday);
	const sat_day = saturday.format('dddd');

	let EP_TITLE_REGEX = window.EP_TITLE_REGEX,
		$pageTitle = $content.children('h1').first();

	function EpisodeForm(id){
		let $form = $.mk('form').attr('id', id).append(
			`<div class="label">
				<span>Season, Episode & Overall #</span>
				<div class=input-group-3>
					<input type="number" min="1" max="8" name="season" placeholder="Season #" required>
					<input type="number" min="1" max="26" name="episode" placeholder="Episode #" required>
					<input type="number" min="1" max="255" name="no" placeholder="Overall #" required>
				</div>
			</label>`,
			$.mk('label').append(
				'<span>Title (5-35 chars.)</span>',
				$.mk('input').attr({
					type: 'text',
					minlength: 5,
					maxlength: 35,
					name: 'title',
					placeholder: 'Title',
					autocomplete: 'off',
					required: true,
				}).patternAttr(EP_TITLE_REGEX)
			),
			`<div class="label">
				<span>Air Date</span>
				<div class="input-group-2">
					<input type="date" name="airdate" placeholder="YYYY-MM-DD" required>
					<input type="time" name="airtime" placeholder="HH:MM" required>
				</div>
			</div>
			<div class="notice info align-center button-here">
				<p>Specify when the episode will air, in <strong>your computer's timezone</strong>.</p>
			</div>
			<label><input type="checkbox" name="twoparter"> Has two parts</label>
			<div class="notice info align-center">
				<p>If this is checked, only specify the episode number of the first part</p>
			</div>`
		);

		$.mk('button').text('Set time to '+sat_time+' this '+sat_day).on('click', function(e){
			e.preventDefault();
			$(this).parents('form').find('input[name="airdate"]').val(sat_date).next().val(sat_time);
		}).appendTo($form.children('.button-here'));

		return $form;
	}
	let $AddEpFormTemplate = new EpisodeForm('addep'),
		$EditEpFormTemplate = new EpisodeForm('editep');

	$('#add-episode').on('click', function(e){
		e.preventDefault();

		$.Dialog.request('Add Episode',$AddEpFormTemplate.clone(true, true),'Add', function($form){
			$form.on('submit', function(e){
				e.preventDefault();
				let airdate = $form.find('input[name=airdate]').attr('disabled',true).val(),
					airtime = $form.find('input[name=airtime]').attr('disabled',true).val(),
					airs = mkDate(airdate, airtime).toISOString(),
					data = $(this).mkData({airs:airs});

				$.Dialog.wait(false, 'Adding episode to database');

				$.post('/episode/add', data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Opening episode page', true);

					$.Navigation.visit('/episode/'+this.epid,function(){
						$.Dialog.close();
					});
				}));
			});
		});
	});

	function Bind(tbody){
		if (typeof tbody === 'string'){
			$eptableBody.html(tbody);
			if ($eptableBody.children('.empty').length)
				$pageTitle.html($pageTitle.data('none')).next().show();
			else $pageTitle.html($pageTitle.data('list')).next().hide();
			$eptableBody.trigger('updatetimes');
		}
		$eptableBody.find('tr[data-epid]').each(function(){
			let $this = $(this),
				epid = $this.attr('data-epid');

			$this.removeAttr('data-epid').data('epid', epid);
		});
		$eptableBody.find('.edit-episode').add('#edit-ep').off('click').on('click', function(e){
			e.preventDefault();

			let $this = $(this),
				EpisodePage = $this.attr('id') === 'edit-ep',
				epid = EpisodePage ? 'S'+SEASON+'E'+EPISODE : $this.closest('tr').data('epid');

			$.Dialog.wait(`Editing ${epid}`, 'Getting episode details from server');

			$.post(`/episode/${epid}`, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false,this.message);

				let $EditEpForm = $EditEpFormTemplate.clone(true, true);

				$EditEpForm.find('input[name=twoparter]').prop('checked',!!this.ep.twoparter);
				delete this.ep.twoparter;

				if (!this.caneditid || (EpisodePage && $('#reservations, #requests').find('li').length))
					$EditEpForm.find('input').filter('[name="season"],[name="episode"]').disable();

				let d = moment(this.ep.airs);
				this.ep.airdate = DateToAirDate(d);
				this.ep.airtime = DateToAirTime(d);

				let epid = this.epid;
				delete this.epid;

				$.each(this.ep,function(k,v){
					$EditEpForm.find('input[name='+k+']').val(v);
				});

				$.Dialog.request(false, $EditEpForm,'Save', function($form){
					$form.on('submit', function(e){
						e.preventDefault();

						let data = $(this).mkData(),
							d = mkDate(data.airdate, data.airtime);
						delete data.airdate;
						delete data.airtime;
						data.airs = d.toISOString();

						$.Dialog.wait(false, 'Saving changes');

						$.post(`/episode/edit/${epid}`, data, $.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							$.Dialog.wait(false, 'Updating page', true);
							$.Navigation.reload(function(){
								$.Dialog.close();
							});
						}));
					});
				});
			}));
		});

		$eptableBody.find('.delete-episode').off('click').on('click', function(e){
			e.preventDefault();

			let $this = $(this),
				epid = $this.closest('tr').data('epid');

			$.Dialog.confirm('Deleting '+epid,'<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>', function(sure){
				if (!sure) return;

				$.Dialog.wait(false, 'Removing episode');

				$.post(`/episode/delete/${epid}`, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		});
	}

	$eptableBody.on('page-switch',function(){
		Bind();
	});
});
