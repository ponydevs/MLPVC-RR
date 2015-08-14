$(function(){
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
		$.Dialog.request(title,$RoleForm.clone(),'rolemod','Change',function(){
			$('#rolemod').on('submit',function(e){
				e.preventDefault();

				$.Dialog.wait(title,'Moving user to the new group');

				$.post("newgroup/"+name, $(this).mkData(), function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$currRole.children('span').text(currRole = ROLES[data.ng]);
						$roleBadge.text(data.badge);
						$.Dialog.close();

						$banToggle[(data.canbebanned ? 'remove' : 'add')+'Class']('hidden');
					}
					else $.Dialog.fail(title,data.message);
				});
			}).find('optgroup').children().filter(function(){ return $(this).text() === currRole }).attr('selected', true);
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
			(Action === 'Banish' ? '<img src="/img/ban-before.png" alt="Sad twilight" height=200>':''),
			action+'-form',
			Action,
			function(){
				var $form = $('#'+action+'-form'),
					$input = $form.find('input');
				$input.val(Action+'ing because ');
				$form.on('submit',function(e){
					e.preventDefault();

					$.Dialog.wait(title, 'Gathering the Elements of Harmony');

					$.post(action+'/'+name, $(this).mkData(), function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							if (action === 'banish') $.Dialog.info(title, '<p>What had to be done, has been done.</p><img src="/img/ban-after.png">');
							else $.Dialog.success(title, data.message, true);

							$currRole.children('span').text(currRole = data.role);
							$roleBadge.text(data.badge);

							$banToggle.toggleClass('un-banish banish typcn-world typcn-weather-night');
							$changeRole.toggleClass('hidden');
						}
						else $.Dialog.fail(title,data.message);
					});
				});
			}
		);
	});
});
