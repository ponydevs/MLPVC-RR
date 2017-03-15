/* global DocReady */
DocReady.push(function(){
	'use strict';

	const PRINTABLE_ASCII_PATTERN = window.PRINTABLE_ASCII_PATTERN;

	let $eventEntries = $('#event-entries'),
		$entryForm = $.mk('form','new-entry').append(
		`<label>
			<span>Entry link</span>
			<input type="url" name="link" required>
		</label>
		<div class="notice info">This must point to a deviation on DeviantArt or a Sta.sh upload. Sta.sh links will not be publicly clickable on the event page, so use that if you do not want to share the source file with anyone other than the staff. You only need to submit the source file, we'll take care of the rest.</div>`,
		$.mk('label').append(
			`<span>Entry title</span>`,
			$.mk('input').attr({
				type: 'text',
				name: 'title',
				required: true,
				pattern: PRINTABLE_ASCII_PATTERN.replace('+','{2,64}'),
				minlength: 2,
				maxlength: 64,
			})
		),
		`<div class="notice info">Here you can enter the name of the character you're submitting for example.</div>
		<label>
			<span>Preview (optional)</span>
			<input type="url" name="prev_src">
		</label>
		<div class="notice info">You can link to a preview of your submission from any of the <a href="/about#supported-providers" target="_blank">suppported image providers</a>. This will show a visual preview alongside your submission on the event page.</div>`
	);

	$.fn.rebindFluidbox = function(){
		this.find('.preview > a:not(.fluidbox--initialized)')
			.fluidboxThis();

		return this;
	};

	$('#enter-event').on('click',function(e){
		e.preventDefault();

		let eventID = $(this).closest('[id^=event-]').attr('id').split('-')[1];

		$.Dialog.wait('New entry','Checking whether you can submit any more entries');

		$.post(`/event/check-entries/${eventID}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			if (this.message)
				$.Dialog.success(false, this.message);

			$.Dialog.request(false, $entryForm.clone(), 'Enter', function($form){
				$form.on('submit',function(e){
					e.preventDefault();

					let data = $form.mkData();
					$.Dialog.wait(false, 'Submitting your entry');

					$.post(`/event/entry/add/${eventID}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$eventEntries.html(this.entrylist).rebindFluidbox();
						$.Dialog.close();
					}));
				});
			});
		}));
	});

	$eventEntries.rebindFluidbox().on('click','.edit-entry', function(e){
		e.preventDefault();

		let $li = $(this).closest('[id^=entry-]'),
			entryID = $li.attr('id').split('-')[1];

		$.Dialog.wait(`Editing entry #${entryID}`,'Retrieving entry details from server');

		$.post(`/event/entry/get/${entryID}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			let data = this;

			$.Dialog.request(false, $entryForm.clone(), 'Save', function($form){
				if (data.link)
					$form.find('input[name="link"]').val(data.link);
				if (data.title)
					$form.find('input[name="title"]').val(data.title);
				if (data.prev_src)
					$form.find('input[name="prev_src"]').val(data.prev_src);
				$form.on('submit',function(e){
					e.preventDefault();

					let data = $form.mkData();
					$.Dialog.wait(false, 'Saving changes');

					$.post(`/event/entry/set/${entryID}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$li.html(this.entryhtml).rebindFluidbox();
						$.Dialog.close();
					}));
				});
			});
		}));
	});

	$eventEntries.on('click','.delete-entry', function(e){
		e.preventDefault();

		let $li = $(this).closest('[id^=entry-]'),
			entryID = $li.attr('id').split('-')[1],
			title = $li.find('.label').text();

		$.Dialog.confirm(`Withdraw entry #${entryID}`,`Are you sure you want to withdraw the entry <q>${title}</q>?`,function(sure){
			if (!sure) return;

			$.Dialog.wait(false, 'Sending deletion request');

			$.post(`/event/entry/del/${entryID}`,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.close();
				$li.fadeOut(500,function(){
					$li.remove();
				});
			}));
		});
	});
},function(){
	'use strict';

	delete $.fn.rebindFluidbox;
});
