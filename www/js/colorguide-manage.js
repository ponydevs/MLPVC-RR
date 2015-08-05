$(function(){
	var Color = window.Color, color = window.color, TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC, $colorGroups,
		isWebkit = 'WebkitAppearance' in document.documentElement.style;

	var $spriteUploadForm = $(document.createElement('form')).attr('id', 'sprite-img').html(
		'<p class=align-center><a href=#upload>Click here to upload a file</a> or enter a URL below.</p>' +
		'<label><input type=text name=image_url placeholder="External image URL" required></label>' +
		'<p class=align-center>The URL will be checked against the supported provider list, and if an image is found, it\'ll be downloaded to the server and set as this appearance\'s sprite image.</p>'
	);

	$('.upload-wrap').each(function(){
		var $this = $(this),
			ponyID = $this.closest('li').attr('id').substring(1);

		$this.uploadZone({
			requestKey: 'sprite',
			title: 'Upload sprite',
			accept: 'image/png',
			target: '/colorguide/setsprite/'+ponyID,
		}).on('uz-uploadstart',function(){
			$.Dialog.close();
		}).ctxmenu([
			{text: 'Open image in new tab', icon: 'arrow-forward', 'default': true, attr: {
				href: $this.find('img').attr('src'),
				target: '_blank',
			}},
			{text: 'Copy image URL', icon: 'clipboard', click: function(){
				$.copy($.urlToAbsolute($this.find('img').attr('src')));
			}},
			{text: 'Upload new sprite', icon: 'upload', click: function(){
				var title = 'Upload sprite image',
					ponyID = $this.closest('li').attr('id').substring(1),
					$uploadInput = $this.find('input[type="file"]');
				$.Dialog.request(title,$spriteUploadForm.clone(),'sprite-img','Download image',function(){
					var $form = $('#sprite-img'),
						$image_url = $form.find('input[name=image_url]');
					$form.find('a').on('click',function(e){
						e.preventDefault();
						e.stopPropagation();

						$uploadInput.trigger('click', [true]);
					});
					$form.on('submit',function(e){
						e.preventDefault();

						var image_url = $image_url.val();

						$.Dialog.wait(title, 'Downloading external image to the server');

						$.post('/colorguide/setsprite/'+ponyID,{image_url: image_url}, $.mkAjaxHandler(function(){
							if (this.status) $uploadInput.trigger('set-image', [this.path]);
							else $.Dialog.fail(title,this.message);
						}));
					});
				});
			}},
		], 'Sprite image').attr('title', isWebkit ? ' ' : '').on('click',function(e, forced){
			if (forced === true) return true;

			e.preventDefault();
			$this.data('ctxmenu-items').eq(1).children().get(0).click();
		});
	});

	var $list = $('#list');
	$list.find('button.edit').on('click',function(){
		$.Dialog.info('Edit mode triggered', 'yay');
	}).next().on('click',function(){
		var $this = $(this),
			$li = $this.closest('li'),
			ponyID = $li.attr('id').substring(1),
			ponyName = $this.parent().text().trim(),
			title = 'Deleting appearance: '+ponyName;

		$.Dialog.confirm(title,'Deleting this appearance will remove <strong>ALL</strong> of its color groups, the colors within them, and the sprite file, if any.<br>Delete anyway?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title, 'Sending removal request');

			$.post('/colorguide/delete/'+ponyID,$.mkAjaxHandler(function(){
				if (this.status){
					$li.remove();
					$.Dialog.close();
				}
				else $.Dialog.fail(title, this.message);
			}));
		})
	});

	var $tagEditForm = $(document.createElement('form')).attr('id', 'edit-tag');
	$tagEditForm
		.append('<label><span>Tag name (4-30 chars.)</span><input type=text name=name required pattern=^.{4,30}$ maxlength=30></label>');
	var $_typeSelect = $(document.createElement('div')).addClass('type-selector');
	$.each(TAG_TYPES_ASSOC,function(type, label){
		var $lbl = $(document.createElement('label')),
			$chx = $(document.createElement('input'))
				.attr({
					type: 'checkbox',
					name: 'type',
					value: type
				}).on('click',function(){
					if (this.checked)
						$(this).parent().siblings().find('input').prop('checked', false);
				});
		$lbl.append($chx, $(document.createElement('span')).addClass('tag typ-'+type).text(label)).appendTo($_typeSelect);
	});
	$tagEditForm
		.append($(document.createElement('div')).addClass('align-center').append('<span>Tag type (optional)</span><br>',$_typeSelect))
		.append($(document.createElement('label')).append('<span>Tag description (max 255 chars., optional)</span><br><textarea name=title maxlength=255></textarea>'))
		.append($(document.createElement('div')).attr('class','notice').hide().html('<p></p>'));

	var $tags = $('.tags');
	function reorder($this){
		$this.children().sort(function(a, b){
			var regex = /^.*typ-([a-z]+).*$/;
			a = [a.className.replace(regex,'$1'), a.innerHTML.trim()];
			b = [b.className.replace(regex,'$1'), b.innerHTML.trim()];

			if (a[0] === b[0])
				return a[1].localeCompare(b[1]);
			return a[0].localeCompare(b[0]);
		}).appendTo($this);
	}

	function ctxmenus(){
		$tags.children('span:not(.ctxmenu-bound)').ctxmenu([
			{text: 'Edit tag', icon: 'pencil', click: function(){
				var $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = 'Editing tag: '+tagName;

				$.Dialog.wait(title, 'Retrieveing tag details from server');

				$.post('/colorguide/gettag/'+tagID,$.mkAjaxHandler(function(){
					var tag = this;
					if (this.status) $.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Save',function(){
						var $form = $('#edit-tag'),
							$ErrorNotice = $form.children('.notice').children('p'),
							handleError = function(){
								$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
								$form.find('input, texarea').attr('disabled', false);
								$.Dialog.center();
							};
						$form.find('input[name=type][value='+tag.type+']').prop('checked', true);
						$form.find('input[type=text][name], textarea[name]').each(function(){
							var $this = $(this);
							$this.val(tag[$this.attr('name')]);
						});
						$form.on('submit', function(e){
							e.preventDefault();

							var tempdata = $(this).serializeArray(), data = {};
							$.each(tempdata,function(i,el){
								data[el.name] = el.value;
							});

							$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
							$.Dialog.center();

							$.post('/colorguide/settag/'+tagID,data,$.mkAjaxHandler(function(){
								if (this.status){
									var $affected = $('.id-'+this.tid);
									$affected.qtip('destroy', true);
									if (this.title) $affected.attr('title', this.title);
									else $affected.removeAttr('title');
									$affected
										.attr('class', 'tag id-'+this.tid+(this.type?' typ-'+this.type:''))
										.text(this.name).data('ctxmenu-items').eq(0).text('Tag: '+this.name);
									$affected.parent().each(function(){
										reorder($(this));
									});
									window.tooltips();
									$.Dialog.close();
								}
								else handleError.call(this);
							}));
						});
					});
					else $.Dialog.fail(title, this.message);
				}));
			}},
			{text: 'Remove tag', icon: 'minus', click: function(){
				var $tag = $(this),
					ponyID = $tag.closest('li').attr('id').replace(/\D/g, ''),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = 'Remove tag: '+tagName;

				$.Dialog.confirm(title,"The tag "+tagName+" will be removed from this appearance.<br>Are you sure?",['Remove it','Nope'],function(sure){
					if (!sure) return;

					$.Dialog.wait(title,'Removing tag');

					$.post('/colorguide/untag/'+ponyID,{ tag: tagID },$.mkAjaxHandler(function(){
						if (this.status){
							$tag.qtip('destroy', true);
							$tag.remove();
							$.Dialog.close();
						}
						else $.Dialog.fail(title, this.message);
					}));
				});
			}},
			{text: 'Delete tag', icon: 'trash', click: function(){
				var $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = 'Detele tag: '+tagName;

				$.Dialog.confirm(title,"By deleting this tag, it'll be removed from every appearance where it's been used.<br>Are you sure?",['Delete it','Nope'],function(sure){
					if (!sure) return;

					$.Dialog.wait(title,'Sending removal request');

					$.post('/colorguide/deltag/'+tagID,$.mkAjaxHandler(function(){
						if (this.status){
							var $affected = $('.id-'+tagID);
							$affected.qtip('destroy', true);
							$affected.remove();
							$.Dialog.close();
						}
						else $.Dialog.fail(title, this.message);
					}));
				});
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

				$.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Create',function(){
					var $form = $('#edit-tag'),
						$ErrorNotice = $form.children('.notice').children('p'),
						handleError = function(){
							$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
							$form.find('input, texarea').attr('disabled', false);
							$.Dialog.center();
						};
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

						$ErrorNotice.text('Creating tag...').parent().removeClass('fail').addClass('info').show();
						$.Dialog.center();

						$.post('/colorguide/maketag',data,$.mkAjaxHandler(function(){
							if (this.status){
								if (this.tags){
									$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
									$tagsDiv.html(this.tags);
									window.tooltips();
									ctxmenus();
								}
								$.Dialog.close();
							}
							else handleError.call(this);
						}));
					});
				})
			}},
		], function($el){ return 'Tag: '+$el.text().trim() });

		var taglist = new Bloodhound({
			datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
			queryTokenizer: Bloodhound.tokenizers.whitespace,
			remote: {
				url: '/colorguide/gettags?s=%QUERY',
				wildcard: '%QUERY'
			}
		}), insertKeys = [13, 188];
		$tags.children('.addtag').each(function(){
			var $input = $(this),
				ponyID = $input.parents('li').attr('id').substring(1);
			$input.typeahead(null, {
				name: 'tags',
				display: 'name',
				source: taglist,
				templates: {
					suggestion: Handlebars.compile('<span class="tag id-{{tid}} typ-{{type}}">{{name}}</span>')
				}
			});
			$input.on('keydown',function(e){
				if (insertKeys.indexOf(e.keyCode) !== -1){
					e.preventDefault();
					var tag_name = $input.val().trim(),
						$tagsDiv = $input.parents('.tags'),
						$ponyTags = $tagsDiv.children('.tag'),
						title = 'Adding tag: '+tag_name;

					if ($ponyTags.filter(function(){ return this.innerHTML.trim() === tag_name }).length > 0)
						return $.Dialog.fail(title, 'This appearance already has this tag');

					$input.attr('disabled', true);

					$.post('/colorguide/tag/'+ponyID,{ tag_name: $input.val() }, $.mkAjaxHandler(function(){
						if (this.status){
							$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
							$tagsDiv.html(this.tags);
							window.tooltips();
							ctxmenus();
							$('#p'+ponyID).find('.addtag').focus();
						}
						else $.Dialog.fail(title, this.message);
						$input.removeAttribute('disabled').focus();
					}));
				}
			});
			$input.nextAll('.tt-menu').on('click', '.tag', function(){
				$input.trigger({
					type: 'keydown',
					keyCode: 13,
				});
			});
		});

		$colorGroups = $('ul.colors').children('li');
		$colorGroups.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Edit "+color+" group (TBI)", icon: 'pencil', click: function(){
					$.Dialog.info('Edit '+color+' group triggered', 'yay');
					return;
					// TODO
					//noinspection UnreachableCodeJS
					var $this = $(this),
						groupID = $this.closest('li').attr('id').substring(2),
						groupName = $this.children().first().text().replace(/:\s?$/,''),
						title = 'Editing color group: '+groupName;

					$.Dialog.wait(title, 'Retrieving '+color+' group details from server');

					$.post('/colorguide/getcg/'+groupID,$.mkAjaxHandler(function(){
						if (this.status) $.Dialog.request(title,$cgEditor.clone(true, true),'cg-editor','Save',function(){
							var $form = $('#cg-editor'),
							$ErrorNotice = $form.children('.notice').children('p'),
							handleError = function(){
								$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
								$form.find('input, texarea').attr('disabled', false);
								$.Dialog.center();
							};
							$form.on('submit',function(e){
								e.preventDefault();

								var tempdata = $form.serializeArray(), data = {};
								$.each(tempdata,function(i,el){
									data[el.name] = el.value;
								});

								$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
								$.Dialog.center();

								$.post('/colorguide/setcg/'+groupID, data, $.mkAjaxHandler(function(){
									if (this.status);
									else handleError.call(this);
								}));
							});
						});
						else handleError.call(this);
					}));
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

			$.copy(this.innerHTML.trim());
		}).filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Copy "+color, icon: 'clipboard', 'default': true, click: function(){
					$.copy(this.innerHTML.trim());
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
