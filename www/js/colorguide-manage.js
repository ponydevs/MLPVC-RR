$(function(){
	var Color = window.Color, color = window.color, TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC, $colorGroups;

	function copyToClipboard(text){
		if (!document.queryCommandSupported('copy')){
			prompt('Copy with Ctrl+C, close with Enter', text);
			return true;
		}

		var $helper = $(document.createElement('textarea')),
			success = false;
		$helper
			.css({
				opacity: 0,
				width: 0,
				height: 0,
				position: 'absolute',
				left: '-10px',
				top: '-10px',
				display: 'block',
			})
			.text(text)
			.appendTo('body')
			.focus();
		$helper.get(0).select();

		try {
			success = document.execCommand('copy');
		} catch(e){}

		if (!success)
			$.Dialog.fail('Copy to clipboard', 'Copying text to clipboard failed!');
		setTimeout(function(){
			$helper.remove();
		}, 1);
	}

	$('#list').find('button.edit').on('click',function(){
		$.Dialog.info('Edit mode triggered', 'yay');
	}).next().on('click',function(){
		// TODO Confirmation
		$.Dialog.info('Delete pony triggered', 'yay');
	});

	var $tagEditForm = $(document.createElement('form')).attr('id', 'edit-tag');
	$tagEditForm
		.append('<label><span>Tag name (4-30 chars.)</span><input type=text name=name required pattern=^.{4,30}$ maxlength=30></label>');
	var $_typeSelect = $(document.createElement('select')).attr('name', 'type');
	$_typeSelect.append('<option value="" selected>No special type</option>');
	$.each(TAG_TYPES_ASSOC,function(type, label){
		$_typeSelect.append('<option value='+type+'>'+label+'</option>');
	});
	$tagEditForm.append($(document.createElement('label')).append('<span>Tag type</span><br>',$_typeSelect));
	$tagEditForm.append($(document.createElement('label')).append('<span>Tag description (max 255 chars., optional)</span><br><textarea name=title maxlength=255></textarea>'));

	function ctxmenus(){
		$('.tags').children(':not(.ctxmenu-bound)').ctxmenu([
			{text: 'Edit tag', icon: 'pencil', click: function(){
				var $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/tag\-(\d+)(?:\s|$)/)[1],
					title = 'Editing tag: '+tagName;

				$.Dialog.wait(title, 'Retrieveing tag details from server');

				$.post('/colorguide/gettag/'+tagID,{},function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.request(title,$tagEditForm.clone(),'edit-tag','Save',function(){
							var $form = $('#edit-tag');
							$form.find('input[name], select[name], textarea[name]').each(function(){
								var $this = $(this);
								$this.val(data[$this.attr('name')]);
							});
							$form.on('submit', function(e){
								e.preventDefault();

								var tempdata = $(this).serializeArray(), data = {};
								$.each(tempdata,function(i,el){
									data[el.name] = el.value;
								});

								$.Dialog.wait(title, 'Saving changes');

								$.post('/colorguide/settag/'+tagID,data,function(data){
									if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

									if (data.status){
										var $affected = $('.tag-'+data.tid);
										console.log($affected);
										$affected.qtip('destroy', true);
										if (data.title) $affected.attr('title', data.title);
										else $affected.removeAttr('title');
										$affected
											.attr('class', 'tag-'+data.tid+(data.type?' typ-'+data.type:''))
											.text(data.name);
										window.tooltips();
										$.Dialog.close();
									}
									else $.Dialog.fail(title, data.message);
								});
							});
						});
					}
					else $.Dialog.fail(title, data.message);
				})
			}},
			{text: 'Delete tag (TBI)', icon: 'trash', click: function(){
				$.Dialog.info('Delete tag triggred', 'yay');
			}},
			true,
			{text: 'Create new tag', icon: 'plus', click: function(){
				var title = 'Create new tag',
					$tag = $(this),
					$li = $tag.closest('li'),
					$div = $tag.closest('div:not([class])'),
					$tagsDiv = $div.children('.tags'),
					ponyID = $li.attr('id').replace(/\D/g, ''),
					ponyName = $div.children('strong').text().trim();

				$.Dialog.request(title,$tagEditForm.clone(),'edit-tag','Create',function(){
					var $form = $('#edit-tag');
					$form.append(
						$(document.createElement('label'))
							.append('<input type=checkbox name=addto value='+ponyID+'> Add this tag to the appearance "'+ponyName+'" after creation')
					);
					$.Dialog.center();
					$form.on('submit', function(e){
						e.preventDefault();

						var tempdata = $form.serializeArray(), data = {};
						$.each(tempdata,function(i,el){
							data[el.name] = el.value;
						});

						$.Dialog.wait(title, 'Creating tag');

						$.post('/colorguide/maketag',data,function(data){
							if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

							if (data.status){
								if (data.tags){
									$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
									$tagsDiv.html(data.tags);
									window.tooltips();
									ctxmenus();
								}
								$.Dialog.success(title, data.message, true);
							}
							else $.Dialog.fail(title, data.message);
						});
					});
				})
			}},
			{text: 'Enable edit mode (TBI)', icon: 'edit', click: function(){
				$(this).parent().prevAll('strong').children('button.edit').triggerHandler('click');
			}}
		]);

		$colorGroups = $('ul.colors').children('li');
		$colorGroups.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Edit "+color+" group (TBI)", icon: 'pencil', click: function(){
					$.Dialog.info('Edit '+color+' group triggered', 'yay');
				}},
				{text: "Delete "+color+" group (TBI)", icon: 'trash', click: function(){
					// TODO Confirmation
					$.Dialog.info('Delete '+color+' group triggered', 'yay');
				}},
				{text: "Add new group (TBI)", icon: 'folder-add', click: function(){
					$.Dialog.info('Add new group triggered', 'yay');
				}},
				{text: "Add new "+color+' (TBI)', icon: 'plus', click: function(){
					$.Dialog.info('Add new color triggered', 'yay');
				}}
			],
			function($el){ return Color+' group: '+$el.children().first().text().trim().replace(':','') }
		);
		$colorGroups.children('span:not(:first-child)').off('click').on('click',function(e){
			e.preventDefault();

			copyToClipboard(this.innerHTML.trim());
		}).filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Copy "+color, icon: 'clipboard', 'default': true, click: function(){
					copyToClipboard(this.innerHTML.trim());
				}},
				{text: "Edit "+color+' (TBI)', icon: 'pencil (TBI)', click: function(){
					$.Dialog.info('Edit '+color+' triggered', 'yay');
				}},
				true,
				{text: "Edit "+color+" group (TBI)", icon: 'pencil', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 1);
				}},
				{text: "Delete "+color+" group (TBI)", icon: 'trash', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 2);
				}},
				{text: "Add new group (TBI)", icon: 'folder-add', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 3);
				}},
				{text: "Add new "+color+' (TBI)', icon: 'plus', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 4);
				}}
			],
			function($el){ return 'Color: '+$el.attr('oldtitle') }
		);
	}
	ctxmenus();
	window.ctxmenus = function(){ctxmenus()};
});
