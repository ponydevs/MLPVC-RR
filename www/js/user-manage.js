/* globals DocReady,HandleNav */
DocReady.push(function UserManage(){
	'use strict';
	if (typeof window.ROLES === 'undefined') return;
	var $content = $('#content'),
		$name = $content.children('h1'),
		name = $name.text().trim(),
		$currRole = $name.next(),
		currRole = $currRole.children('span').text(),
		$RoleForm = $.mk('form').attr('id','rolemod').html('<select name="newrole" required><optgroup label="Possible roles"></optgroup></select>'),
		$OptGrp = $RoleForm.find('optgroup'),
		$banToggle = $('#ban-toggle'),
		$changeRole = $('#change-role');

	$.each(window.ROLES,function(name,label){
		$OptGrp.append('<option value='+name+'>'+label+'</option>');
	});

	$changeRole.on('click',function(){
		$.Dialog.request('Change group',$RoleForm.clone(),'rolemod','Change',function($form){
			var $currRoleOpt = $form.find('option').filter(function(){ return this.innerHTML === currRole }).attr('selected', true);
			$form.on('submit',function(e){
				e.preventDefault();

				if ($form.children('select').val() === $currRoleOpt.attr('value'))
					return $.Dialog.close();

				var data = $form.mkData();
				$.Dialog.wait(false,'Moving user to the new group');

				$.post("/user/newgroup/"+name, data, $.mkAjaxHandler(function(){
					if (this.already_in === true)
						return $.Dialog.close();

					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.info(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		});
	});
	$banToggle.on('click',function(){
		var Action = ($banToggle.hasClass('un-banish') ? 'Un-ban' : 'Ban')+'ish',
			action = Action.toLowerCase(),
			title = Action+'ing '+name+(action === 'banish' ? ' to the moon':'');
		$.Dialog.request(
			title,
			(
				Action === 'Banish'
				? '<p>'+Action+'ing '+name+' will immediately sign them out of every session and won\'t allow them to log in again. Please, only do this if it\'s absolutely necessary.</p>'
				: '<p>'+Action+'ing '+name+' will allow them to sign in to the site again.</p>'
			) +
			'<form id='+action+'-form>' +
			'   <p>You must provide a reason (5-255 chars.) for the '+action.replace(/ish$/,'')+' which will be added to the log entry and appear in the user\'s banishment history.</p>' +
			'   <input type="text" name="reason" placeholder="Enter a reason" required pattern="^.{5,255}$">' +
			'</form>'+
			(Action === 'Banish' ? '<img src="/img/pre-ban.svg" alt="Sad twilight">':''),
			action+'-form',
			Action,
			function(){
				var $form = $('#'+action+'-form'),
					$input = $form.find('input');
				$input.val(Action+'ing because ');
				$form.on('submit',function(e){
					e.preventDefault();

					var data = $(this).mkData();
					$.Dialog.wait(false, 'Gathering the Elements of Harmony');

					$.post('/user/'+action+'/'+name, data, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title,this.message);

						var message = this.message;
						$.Dialog.wait(false, 'Reloading page', true);
						$.Navigation.reload(function(){
							if (action === 'banish') $.Dialog.success(title, '<p>What had to be done, has been done.</p><img src="/img/post-ban.svg">');
							else $.Dialog.success(title, message, true);
						});
					}));
				});
			}
		);
	});
});
