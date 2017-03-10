/* global DocReady,ace */
DocReady.push(function(){
	'use strict';

	const PRINTABLE_ASCII_PATTERN = window.PRINTABLE_ASCII_PATTERN, EVENT_TYPES = window.EVENT_TYPES;

	let $addbtn = $('#add-event'),
		$eventTypeSelect = $.mk('select').attr({
			name: 'type',
			required: true,
		}).append(`<option value="" style="display:none">(choose event type)</option>`),
		$etsOptgroup = $.mk('optgroup').attr('label','Available types').appendTo($eventTypeSelect);
	$.each(EVENT_TYPES, (value, text)=>{
		$etsOptgroup.append(`<option value="${value}">${text}</option>`);
	});
	let $AddForm = $.mk('form','add-event-form').append(
			$.mk('label').append(
				`<span>Event name (2-64 chars.)</span>`,
				$.mk('input').attr({
					type: 'text',
					name: 'name',
					minlength: 2,
					maxlength: 64,
					required: true,
				}).patternAttr(PRINTABLE_ASCII_PATTERN)
			),
			`<div class="label">
				<span>Description (3000 chars. max, optional)</span>
				<div class="ace_editor"></div>
			</div>`,
			$.mk('label').append(
				`<span>Event type</span>`,
				$eventTypeSelect
			),
			$.mk('div').attr('class','label').append(
				`<span>Start date & time</span>`,
				$.mk('div').attr('class','input-group-2').append(
					`<input type="date" name="start_date">`,
					`<input type="time" name="start_time">`
				)
			),
			$.mk('div').attr('class','notice info align-center').html('Leave <q>Start date & time</q> blank if you want the event to start immediately after you press Add. Always specify times in your computer\'s timezone.'),
			$.mk('div').attr('class','label').append(
				`<span>End date & time</span>`,
				$.mk('div').attr('class','input-group-2').append(
					`<input type="date" name="end_date" required>`,
					`<input type="time" name="end_time" required>`
				)
			),
			$.mk('div').attr('class','label').append(
				`<span>Who can enter & how many times?</span>`,
				$.mk('div').attr('class','input-group-2').append(
					`<select name="entry_role" required>
						<optgroup label="Role in the group">
							<option value="user" selected>Any DeviantArt User</option>
							<option value="member">Club Members</option>
							<option value="staff">Staff Members</option>
						</optgroup>
						<optgroup label="Special">
							<option disabled value="spec_discord">Discord Server Members</option>
							<option disabled value="spec_ai">Illustrator Users</option>
							<option disabled value="spec_inkscape">Inkscape Users</option>
							<option disabled value="spec_ponyscape">Ponyscape Users</option>
						</optgroup>
					</select>`,
					`<input type="text" name="max_entries" pattern="^(0*[1-9]\\d*|[Uu]nlimited|0)$" list="max_entries-list" value="1">
					<datalist id="max_entries-list" required>
						<option value="Unlimited">
						<option value="1">
						<option value="2">
						<option value="5">
						<option value="10">
					</datalist>`
				)
			),
			$.mk('div').attr('class','notice info align-center').html('Enter <q>0</q> or <q>Unlimited</q> to remove the number of entries cap.')
		);
	$addbtn.on('click',function(e){
		e.preventDefault();

		$.Dialog.request('Add new event',$AddForm.clone(),'Add',function($form){
			let session;

			$.getAceEditor(false, 'html', function(mode){
				try {
					let div = $form.find('.ace_editor').get(0),
						editor = ace.edit(div);
					session = $.aceInit(editor, mode);
					session.setMode(mode);
					session.setUseWrapMode(true);
				}
				catch(e){ console.error(e) }
			});

			$form.on('submit',function(e){
				e.preventDefault();

				let data = $form.mkData();
				data.description = session.getValue();
				if (data.start_date && data.start_time){
					let start = $.mkMoment(data.start_date, data.start_time);
					data.starts_at = start.toISOString();
				}
				let end = $.mkMoment(data.end_date, data.end_time);
				data.ends_at = end.toISOString();
				delete data.start_date;
				delete data.start_time;
				delete data.end_date;
				delete data.end_time;
				$.Dialog.wait(false);

				$.post('/event/add',data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.success(false, 'Event added');
					$.Dialog.wait(false, 'Loading event page');
					window.location.href = this.url;
				}));
			});
		});
	});
});
