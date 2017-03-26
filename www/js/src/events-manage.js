/* global DocReady,ace,$content,moment */
DocReady.push(function(){
	'use strict';

	const PRINTABLE_ASCII_PATTERN = window.PRINTABLE_ASCII_PATTERN, EVENT_TYPES = window.EVENT_TYPES, EventPage = Boolean(window.EventPage);

	let $eventTypeSelect = $.mk('select').attr({
			name: 'type',
			required: true,
		}).append(`<option value="" style="display:none">(choose event type)</option>`).on('change',function(){
			const
				$this = $(this),
				show = $this.val() === 'contest';
			$this.parent().siblings('.who-vote')[show?'removeClass':'addClass']('hidden').find('select')[show?'enable':'disable']('hidden');
		}),
		$etsOptgroup = $.mk('optgroup').attr('label','Available types').appendTo($eventTypeSelect);
	$.each(EVENT_TYPES, (value, text)=>{
		$etsOptgroup.append(`<option value="${value}">${text}</option>`);
	});
	let $EventEditorFormTemplate = $.mk('form','event-editor').append(
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
				<span>Description (1-3000 chars.)<br>Uses <a href="https://help.github.com/articles/basic-writing-and-formatting-syntax/" target="_blank">Markdown</a> formatting</span>
				<div class="ace_editor"></div>
			</div>`,
			$.mk('label').append(
				`<span>Event type (cannot ba changed later)</span>`,
				$eventTypeSelect
			),
			$.mk('label').attr('class','who-vote hidden').append(
				`<span>Who can vote on the entries?</span>`,
				`<select name="vote_role" required>
					<optgroup label="Roles">
						<option value="user" selected>Any DeviantArt User</option>
						<option value="member">Club Members</option>
						<option value="staff">Staff Members</option>
					</optgroup>
				</select>`
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
							<option value="spec_discord">Discord Server Members</option>
							<option value="spec_illustrator">Illustrator Users</option>
							<option value="spec_inkscape">Inkscape Users</option>
							<option value="spec_ponyscape">Ponyscape Users</option>
						</optgroup>
					</select>`,
					`<input type="text" name="max_entries" pattern="^(0*[1-9]\\d*|[Uu]nlimited|0)$" list="max_entries-list" value="1">
					<datalist id="max_entries-list" required>
						<option value="Unlimited">
						<option value="1">
					</datalist>`
				)
			),
			$.mk('div').attr('class','notice info align-center').html('Enter <q>0</q> or <q>Unlimited</q> to remove the number of entries cap.')
		),
		mkEventEditor = function($this, title, data){
			let editing = !!data,
				$eventName;
			if (EventPage){
				if (!editing)
					return;
				$eventName = $content.children('h1');
			}
			else $eventName = $this.siblings().first();

			$.Dialog.request(title,$EventEditorFormTemplate.clone(true,true),'Save', function($form){
				let eventID, session;

				$.getAceEditor(false, 'markdown', function(mode){
					try {
						let div = $form.find('.ace_editor').get(0),
							editor = ace.edit(div);
						session = $.aceInit(editor, mode);
						session.setMode(mode);
						session.setUseWrapMode(true);

						if (editing && data.desc_src)
							session.setValue(data.desc_src);
					}
					catch(e){ console.error(e) }
				});

				if (editing){
					eventID = data.eventID;

					$form.find('input[name=name]').val(data.name);
					$form.find('[name=type]').parent().remove();
					$form.find('[name=entry_role]').val(data.entry_role);
					$form.find('[name=max_entries]').val(data.max_entries ? data.max_entries : 'Unlimited');

					if (data.starts_at){
						let starts = moment(data.starts_at);
						$form.find('input[name="start_date"]').val($.momentToYMD(starts));
						$form.find('input[name="start_time"]').val($.momentToHM(starts));
					}
					if (data.ends_at){
						let ends = moment(data.ends_at);
						$form.find('input[name="end_date"]').val($.momentToYMD(ends));
						$form.find('input[name="end_time"]').val($.momentToHM(ends));
					}

				}

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
					$.Dialog.wait(false, 'Saving changes');
					if (EventPage)
						data.EVENT_PAGE = true;

					$.post(`/event/${editing?'set/'+eventID:'/add'}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						data = this;
						if (editing){
							if (!EventPage){
								$eventName.text(data.name);
								if (data.newurl)
									$eventName.attr('href',(_, oldhref) => {
										return oldhref.replace(/\/[^\/]+$/, '/'+data.newurl);
									});
								$.Dialog.close();
							}
							else {
								$.Dialog.wait(false, 'Reloading page', true);
								$.Navigation.reload(function(){
									$.Dialog.close();
								});
							}
						}
						else {
							$.Dialog.success(title, 'Event added');
							$.Dialog.wait(title, 'Loading event page');
							$.Navigation.visit(data.goto, function(){
								if (data.info)
									$.Dialog.info(title, data.info);
								else $.Dialog.close();
							});
						}
					}));
				});
			});
		};
	$('#add-event').on('click',function(e){
		e.preventDefault();

		mkEventEditor($(this),'Add new event');
	});

	$content.on('click','[id^=event-] .edit-event',function(e){
		e.preventDefault();

		let $this = $(this),
			$li = $this.closest('[id^=event-]'),
			eventID = $li.attr('id').split('-')[1],
			title = 'Editing event #'+eventID;

		$.Dialog.wait(title, 'Retrieving event details from server');

		$.post(`/event/get/${eventID}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			let data = this;
			data.eventID = eventID;
			mkEventEditor($this, title, data);
		}));
	});
	$content.on('click','[id^=event-] .delete-event',function(e){
		e.preventDefault();

		let $li = $(this).closest('[id^=event-]'),
			eventid = $li.attr('id').split('-')[1],
			eventname = !EventPage
				? $li.find('.event-name').html()
				: $content.children('h1').text();

		$.Dialog.confirm('Delete event #'+eventid,`Are you <strong class="color-red"><em>ABSOLUTELY</em></strong> sure you want to delete &ldquo;${eventname}&rdquo; along with all submissions?`,function(sure){
			if (!sure) return;

			$.Dialog.wait(false);

			$.post(`/event/del/${eventid}`,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				if (EventPage){
					$.Dialog.wait('Navigation', 'Loading page 1');
					$.Navigation.visit(`/events/1`,function(){
						$.Dialog.close();
					});
					return;
				}

				$.Dialog.wait(false, 'Reloading page', true);
				$.Navigation.reload(function(){
					$.Dialog.close();
				});
			}));
		});
	});
});
