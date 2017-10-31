/* globals DocReady,HandleNav,$content */
$(function(){
	'use strict';

	if (typeof window.ROLES === 'undefined') return;
	let $briefing = $content.children('.briefing'),
		name = $briefing.find('.username').text().trim(),
		$currRole = $briefing.find('.rolelabel'),
		currRole = $currRole.text().trim(),
		$RoleModFormTemplate = $.mk('form').attr('id','rolemod').html('<select name="newrole" required><optgroup label="Possible roles"></optgroup></select>'),
		$OptGrp = $RoleModFormTemplate.find('optgroup'),
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

					$.Navigation.reload(true);
				}));
			});
		});
	});
});
