DocReady.push(function UserManage(){
	if (typeof window.ROLES == null) return;
	var $w = $(window),
		$content = $('#content'),
		ROLES = window.ROLES,
		$name = $content.children('h1'),
		name = $name.text().trim(),
		$roleBadge = $content.find('.avatar-wrap').children('.badge'),
		$currRole = $name.next(),
		currRole = $currRole.children('span').text(),
		$RoleForm = $.mk('form').attr('id','rolemod').html('<select name=newrole required><optgroup label="Possible roles"></optgroup></select>'),
		$OptGrp = $RoleForm.find('optgroup'),
		$banToggle = $('#ban-toggle'),
		$changeRole = $('#change-role');

	$.each(ROLES,function(name,label){
		$OptGrp.append('<option value='+name+'>'+label+'</option>');
	});

	$changeRole.on('click',function(){
		var title = "Change group";
		$.Dialog.request(title,$RoleForm.clone(),'rolemod','Change',function($form){
			var $currRoleOpt = $form.find('option').filter(function(){ return this.innerHTML === currRole }).attr('selected', true);
			$form.on('submit',function(e){
				e.preventDefault();

				if ($form.children('select').val() === $currRoleOpt.attr('value'))
					return $.Dialog.close();

				var data = $form.mkData();
				$.Dialog.wait(title,'Moving user to the new group');

				$.post("newgroup/"+name, data, $.mkAjaxHandler(function(){
					if (this.already_in === true)
						return $.Dialog.close();

					if (!this.status) return $.Dialog.fail(title,this.message);
					$.Dialog.success(title, this.message);

					HandleNav(location.pathname, function(){
						$.Dialog.close();
					});
				}));
			});
		});
	});
	$banToggle.on('click',function(){
		var Action = ($banToggle.hasClass('un-banish') ? 'Un-ban' : 'Ban')+'ish',
			action = Action.toLowerCase(),
			title = Action+'ing '+name+(action == 'banish' ? ' to the moon':'');
		$.Dialog.request(
			title,
			(
				Action === 'Banish'
				? '<p>'+Action+'ing '+name+' will immediately sign them out of every session and won\'t allow them to log in again. Please, only do this if it\'s absolutely necessary.</p>'
				: '<p>'+Action+'ing '+name+' will allow them to sign in to the site again.</p>'
			) +
			'<form id='+action+'-form>' +
			'   <p>Please provide a reason (5-255 chars.) for the '+action.replace(/ish$/,'')+' which will be added to the log entry and appear in the user\'s ban history.</p>' +
			'   <input type="text" name="reason" placeholder="Enter a reason" required pattern="^.{5,255}$">' +
			'</form>'+
			(Action === 'Banish' ? '<img src="/img/pre-ban.svg" alt="Sad twilight" height=200>':''),
			action+'-form',
			Action,
			function(){
				var $form = $('#'+action+'-form'),
					$input = $form.find('input');
				$input.val(Action+'ing because ');
				$form.on('submit',function(e){
					e.preventDefault();

					var data = $(this).mkData();
					$.Dialog.wait(title, 'Gathering the Elements of Harmony');

					$.post(action+'/'+name, data, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title,this.message);

						if (action === 'banish') $.Dialog.info(title, '<p>What had to be done, has been done.</p><img src="/img/post-ban.svg">');
						else $.Dialog.success(title, this.message, true);

						$currRole.children('span').text(currRole = this.role);
						$roleBadge.text(this.badge);

						$banToggle.toggleClass('un-banish banish typcn-world typcn-weather-night');
						$changeRole.toggleClass('hidden');
					}));
				});
			}
		);
	});
});
