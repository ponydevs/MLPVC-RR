/* global DocReady */
DocReady.push(function(){
	'use strict';

	const PRINTABLE_ASCII_PATTERN = window.PRINTABLE_ASCII_PATTERN, EVENT_TYPES = window.EVENT_TYPES;

	let $addbtn = $('#add-event'),
		$eventTypeSelect = $.mk('select').attr({
			name: 'type',
			required: true,
		}).append(`<option value="" style="display:none">(choose one)</option>`),
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
			$.mk('label').append(
				`<span>Event type</span>`,
				$eventTypeSelect
			),
			$.mk('label').append(
				`<span>Who should be able to enter?</span>
				<select name="entry_role" required>
					<optgroup label="Role in the group">
						<option value="user" selected>Any DeviantArt User</option>
						<option value="member">Club Members</option>
						<option value="staff">Staff Members</option>
					</optgroup>
					<optgroup label="Special">
						<option value="spec_discord">Discord Server Members</option>
						<option disabled value="spec_ai">Illustrator Users</option>
						<option disabled value="spec_inkscape">Inkscape Users</option>
						<option disabled value="spec_ponyscape">Ponyscape Users</option>
					</optgroup>
				</select>`
			)
		);
	$addbtn.on('click',function(e){
		e.preventDefault();

		$.Dialog.request('Add new event',$AddForm.clone(),'Add',function($form){
			$form.on('submit',function(e){
				e.preventDefault();

				let data = $form.mkData();
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
