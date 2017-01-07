/* globals DocReady,HandleNav,$content */
DocReady.push(function(){
	'use strict';

	if (typeof window.ROLES === 'undefined') return;
	let $briefing = $content.children('.briefing'),
		name = $briefing.find('.username').text().trim(),
		$currRole = $briefing.find('.rolelabel'),
		currRole = $currRole.text().trim(),
		$RoleModFormTemplate = $.mk('form').attr('id','rolemod').html('<select name="newrole" required><optgroup label="Possible roles"></optgroup></select>'),
		$OptGrp = $RoleModFormTemplate.find('optgroup'),
		$banToggle = $('#ban-toggle'),
		$changeRole = $('#change-role');

	$.each(window.ROLES, (name,label) => {
		$OptGrp.append(`<option value=${name}>${label}</option>`);
	});

	$changeRole.on('click',function(){
		$.Dialog.request('Change group',$RoleModFormTemplate.clone(),'Change', function($form){
			let $currRoleOpt = $form.find('option').filter(function(){ return this.innerHTML === currRole }).attr('selected', true);
			$form.on('submit', function(e){
				e.preventDefault();

				if ($form.children('select').val() === $currRoleOpt.attr('value'))
					return $.Dialog.close();

				let data = $form.mkData();
				$.Dialog.wait(false,'Moving user to the new group');

				$.post(`/user/setgroup/${name}`, data, $.mkAjaxHandler(function(){
					if (this.already_in === true)
						return $.Dialog.close();

					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		});
	});
	$banToggle.on('click',function(){
		let Action = ($banToggle.hasClass('un-banish') ? 'Un-ban' : 'Ban')+'ish',
			action = Action.toLowerCase(),
			title = Action+'ing '+name+(action === 'banish' ? ' to the moon':'');
		$.Dialog.request(
			title,
			$.mk('form',`${action}-form`).html(
				`<p>${Action}ing ${name} will ${
					action === 'banish'
					? "immediately sign them out of every session and won’t allow them to log in again. Please, only do this if it’s absolutely necessary."
					: "allow them to sign in to the site again."
				}</p>
				<p>You must provide a reason (5-255 chars.) for the ${action.replace(/ish$/,'')} which will be added to the log entry and appear in the user’s banishment history.</p>
				<input type="text" name="reason" placeholder="Enter a reason" required pattern="^.{5,255}$" value="${Action}ing because ">
				${action === 'banish'?'<img src="/img/pre-ban.svg" alt="Sad twilight">':''}`
			),
			Action,
			$form => {
				$form.on('submit', function(e){
					e.preventDefault();

					let data = $(this).mkData();
					$.Dialog.wait(false, 'Gathering the Elements of Harmony');

					$.post(`/user/${action}/${name}`, data, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title,this.message);

						let message = this.message;
						$.Dialog.wait(false, 'Reloading page', true);
						$.Navigation.reload(function(){
							if (action === 'banish') $.Dialog.success(title, '<p>What had to be done, has been done.</p><img src="/img/post-ban.svg">', true);
							else $.Dialog.success(title, message, true);
						});
					}));
				});
			}
		);
	});
});
