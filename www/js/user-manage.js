$(function(){
	if (typeof window.ROLES == null) return;
	var $w = $(window),
		ROLES = window.ROLES,
		$name = $('#content').children('h1'),
		name = $name.text(),
		$currRole = $name.next(),
		currRole = $currRole.children('span').text(),
		$RoleForm = $(document.createElement('form')).attr('id','rolemod').html('<select name=newrole required><optgroup label="Possible roles"></optgroup></select>'),
		$OptGrp = $RoleForm.find('optgroup');

	$.each(ROLES,function(name,label){
		$OptGrp.append('<option value='+name+'>'+label+'</option>');
	});
	var $selected = $OptGrp.children().filter(function(){ return $(this).text() === currRole });
	if ($selected.length === 1) $selected.attr('selected', true);
	else $OptGrp.parent().prepend('<option value="" selected style=display:none>Select a group!</option>');

	$('#change-role').on('click',function(){
		var title = "Change group";
		$.Dialog.request(title,$RoleForm.clone(),'rolemod','Change',function(){
			$('#rolemod').on('submit',function(e){
				e.preventDefault();

				var tempdata = $(this).serializeArray(), data = {};
				$.each(tempdata,function(i,el){
					data[el.name] = el.value;
				});

				$.Dialog.wait(title,'Moving user to the new group');

				$.ajax({
					method: "POST",
					url: "/u/newgroup/"+name,
					data: data,
					success: function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							$currRole.children('span').text(currRole = ROLES[data.ng]);
							$.Dialog.close();
						}
						else $.Dialog.fail(title,data.message);
					}
				});
			});
		});
	});
});