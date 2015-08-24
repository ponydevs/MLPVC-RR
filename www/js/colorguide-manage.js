$(function(){
	var Color = window.Color, color = window.color, TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC, $colorGroups,
		MAX_SIZE = window.MAX_SIZE, PRINTABLE_ASCII_REGEX = window.PRINTABLE_ASCII_REGEX,
		HEX_COLOR_PATTERN = window.HEX_COLOR_PATTERN, isWebkit = 'WebkitAppearance' in document.documentElement.style;

	var $spriteUploadForm = $.mk('form').attr('id', 'sprite-img').html(
		'<p class=align-center><a href=#upload>Click here to upload a file</a> (max. '+MAX_SIZE+') or enter a URL below.</p>' +
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
				$.Dialog.request(title,$spriteUploadForm.clone(),'sprite-img','Download image',function($form){
					var $image_url = $form.find('input[name=image_url]');
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

	var $list = $('#list'),
		$ponyEditor = $.mk('form').attr('id','pony-editor')
			.append(
				$.mk('label').append(
					$.mk('span').text('Name (4-70 chars.)'),
					$.mk('input').attr({
						name: 'label',
						placeholder: 'Enter a name',
						pattern: PRINTABLE_ASCII_REGEX.replace('+', '{4,70}'),
						required: true,
						maxlength: 70
					})
				),
				$.mk('label').append(
					$.mk('span').text('Additional notes (255 chars. max, optional)'),
					$.mk('textarea').attr({
						name: 'notes',
						maxlength: 255
					})
				),
				$.mk('div').attr('class','notice').hide().html('<p></p>')
			),
		mkPonyEditor = function($this, title, data){
			var $ponyLabel = $this.parent(),
				$ponyNotes = $ponyLabel.next();

			$.Dialog.request(title,$ponyEditor.clone(),'pony-editor','Save',function($form){
				var $ErrorNotice = $form.children('.notice').children('p'),
					handleError = function(){
						$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
						$form.find('input, texarea').attr('disabled', false);
						$.Dialog.center();
					},
					editing = !!data;
				if (editing){
					$form.find('input').val(data.label);
					$form.find('textarea').val(data.notes);
				}
				$form.on('submit',function(e){
					e.preventDefault();

					$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
					$.Dialog.center();

					$.post('/colorguide/'+(editing?'set/'+data.ponyID:'make'),$form.mkData(),$.mkAjaxHandler(function(){
						if (this.status){
							if (editing){
								$ponyLabel.contents().first().replaceWith(this.label);
								$ponyNotes.html(this.notes);
								$.Dialog.close();
							}
							else {
								$.Dialog.success(title, this.message, true);
								$.toPage(window.location.pathname.replace(/(\d+)?$/,this.page),true,true);
								var id = this.id;
								$list.one('page-switch',function(){
									var $pony = $('#p'+id);
									$(document.body).scrollTop($pony.offset().top - ($pony.outerHeight()/2));
								});
							}
						}
						else handleError.call(this);
					}));
				})
			});
		},
		$cgReordering = $.mk('form').attr('id','cg-reorder').append($.mk('div').attr('class','notice').hide().html('<p></p>'));

	$('#new-appearance-btn').on('click',function(){
		mkPonyEditor($(this),'Add new appearance');
	});

	var $tagEditForm = $.mk('form').attr('id', 'edit-tag');
	$tagEditForm
		.append('<label><span>Tag name (4-30 chars.)</span><input type=text name=name required pattern=^.{4,30}$ maxlength=30></label>');
	var $_typeSelect = $.mk('div').addClass('type-selector');
	$.each(TAG_TYPES_ASSOC,function(type, label){
		var $lbl = $.mk('label'),
			$chx = $.mk('input')
				.attr({
					type: 'checkbox',
					name: 'type',
					value: type
				}).on('click keyup',function(){
					if (this.checked)
						$(this).parent().siblings().find('input').prop('checked', false);
				});
		$lbl.append($chx, $.mk('span').addClass('tag typ-'+type).text(label)).appendTo($_typeSelect);
	});
	$tagEditForm.append(
		$.mk('div').addClass('align-center').append('<span>Tag type (optional)</span><br>',$_typeSelect),
		$.mk('label').append('<span>Tag description (max 255 chars., optional)</span><br><textarea name=title maxlength=255></textarea>'),
		$.mk('div').attr('class','notice').hide().html('<p></p>')
	);
	function reorder($this){
		$this.children('.tag').sort(function(a, b){
			var regex = /^.*typ-([a-z]+).*$/;
			a = [a.className.replace(regex,'$1'), a.innerHTML.trim()];
			b = [b.className.replace(regex,'$1'), b.innerHTML.trim()];

			if (a[0] === b[0])
				return a[1].localeCompare(b[1]);
			return a[0].localeCompare(b[0]);
		}).appendTo($this);
	}

	function createNewTag($tag, name){
		var title = 'Create new tag',
			$li = $tag.closest('li'),
			$div = $tag.closest('div:not([class])'),
			$tagsDiv = $div.children('.tags'),
			ponyID = $li.attr('id').replace(/\D/g, ''),
			ponyName = $div.children('strong').text().trim();

		$.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Create',function($form){
			var $ErrorNotice = $form.children('.notice').children('p'),
				handleError = function(){
					$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
					$form.find('input, texarea').attr('disabled', false);
					$.Dialog.center();
				};
			$form.append(
				$.mk('label').append(
					$.mk('input').attr({type:'checkbox',name:'addto'}).val(ponyID).prop('checked', typeof name === 'string'),
					document.createTextNode(' Add this tag to the appearance "'+ponyName+'" after creation')
				)
			);
			if (typeof name === 'string') $form.find('input[name=name]').val(name);
			$.Dialog.center();
			$form.on('submit', function(e){
				e.preventDefault();

				$ErrorNotice.text('Creating tag...').parent().removeClass('fail').addClass('info').show();
				$.Dialog.center();

				$.post('/colorguide/maketag',$form.mkData(),$.mkAjaxHandler(function(){
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
		});
	}

	var $cgEditor = $.mk('form').attr('id','cg-editor'),
		$colorPreview = $.mk('span').attr('class','clrp'),
		$colorInput =
			$.mk('input').attr({
				'class': 'clri',
				pattern: HEX_COLOR_PATTERN.toString().replace(/\//g,''),
				autocomplete: 'off',
				spellcheck: 'false',
			}).on('keyup change input',function(){
				var $this = $(this),
					$cp = $this.prev(),
					valid = HEX_COLOR_PATTERN.test(this.value);
				if (valid)
					$cp.removeClass('invalid').css('background-color', this.value.replace(HEX_COLOR_PATTERN, '#$1'));
				else $cp.addClass('invalid');

				$this.next().attr('required', valid);
			}).on('paste blur',function(e){
				var input = this,
					$input = $(input),
					shortHex = /^#?([A-Fa-f0-9]{3})$/,
					f = function(){
						var val = input.value;
						if (shortHex.test(val)){
							var match = val.match(shortHex)[1];
							val = '#'+match[0]+match[0]+match[1]+match[1]+match[2]+match[2];
						}
						if (HEX_COLOR_PATTERN.test(val)){
							$input.val(val.replace(HEX_COLOR_PATTERN, '#$1').toUpperCase()).trigger('change');
							if (e.type !== 'blur')
								$input.next().focus();
						}
					};
				if (e.type === 'paste') setTimeout(f, 10);
				else f();
			}),
		$colorLabel = $.mk('input').attr({ 'class': 'clrl', pattern: PRINTABLE_ASCII_REGEX.replace('+', '{3,30}') }),
		$colorActions = $.mk('div').attr('class','clra')
			.append($.mk('span').attr('class','typcn typcn-minus remove red').on('click',function(){
				$(this).closest('.clr').remove();
				$.Dialog.center();
			}))
			.append($.mk('span').attr('class','typcn typcn-arrow-move move blue')),
		mkClrDiv = function(color){
			var $cp = $colorPreview.clone(),
				$ci = $colorInput.clone(true, true),
				$cl = $colorLabel.clone(),
				$ca = $colorActions.clone(true, true),
				$el = $.mk('div').attr('class','clr');

			if (typeof color === 'object'){
				if (color.colorid) $el.data('id', color.colorid);
				if (color.hex) $ci.val(color.hex);
				if (color.label) $cl.val(color.label);
			}

			$el.append($cp,$ci,$cl,$ca);
			$ci.trigger('change');
			return $el;
		},
		$addBtn = $.mk('button').attr('class','typcn typcn-plus green').text('Add new color').on('click',function(e){
			e.preventDefault();

			var $form = $(this).parents('#cg-editor'),
				$colors = $form.children('.clrs');
			if (!$colors.length)
				$form.append($colors = $.mk('div').attr('class', 'clrs'));
			var $div = mkClrDiv();
			$colors.append($div);
			$div.find('.clri').focus();
			$.Dialog.center();
		});
	$cgEditor
		.append('<label><span>Group name (2-30 chars.)</span><br><input name=label pattern="'+PRINTABLE_ASCII_REGEX.replace('+','{2,30}')+'" required></label>')
		.append('<p class=align-center>The # symbol is optional, rows with invalid '+color+'s will be ignored. Each color must have a short (3-30 chars.) description of its intended use.</p>',$addBtn)
		.append($.mk('div').attr('class', 'clrs'))
		.append($.mk('div').attr('class','notice').hide().html('<p></p>'));
	$cgEditor.on('render-color-inputs',function(_, data){
		var $form = $(this),
			$colors = $form.children('.clrs').empty();

		$.each(data, function(_, color){
			$colors.append(mkClrDiv(color));
		});

		new Sortable($colors.get(0), {
		    handle: ".move",
		    ghostClass: "moving",
		    scroll: false,
		    animation: 150,
		});

		$.Dialog.center();
	});

	function CGEditorMaker(title, $group){
		var dis = this;
		if (typeof $group !== 'undefined'){
			if ($group instanceof jQuery){
				var groupID = $group.attr('id').substring(2),
					ponyID = $group.parents('li').attr('id').substring(1);
			}
			else ponyID = $group;
		}
		$.Dialog.request(title,$cgEditor.clone(true, true),'cg-editor','Save',function($form){
			var $ErrorNotice = $form.children('.notice').children('p'),
				handleError = function(){
					$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
					$form.find('input, texarea').attr('disabled', false);
					$.Dialog.center();
				},
				$label = $form.find('input[name=label]'),
				editing = typeof dis === 'object' && dis.label && dis.Colors;

			if (editing){
				$label.val(dis.label);
				$form.trigger('render-color-inputs',[dis.Colors]);
			}
			$form.on('submit',function(e){
				e.preventDefault();

				var data = { label: $label.val(), Colors: [] };
				if (!editing) data.ponyid = ponyID;
				$form.find('.clr').each(function(){
					var $row = $(this),
						$ci = $row.children('.clri');

					if (!HEX_COLOR_PATTERN.test($ci.val()))
						return;

					var colorid = $row.data('id'),
						append = { hex: $ci.val().replace(HEX_COLOR_PATTERN,'#$1').toUpperCase() };
					if (typeof colorid !== 'undefined') append.colorid = parseInt(colorid, 10);

					append.label = $row.children('.clrl').val();

					data.Colors.push(append);
				});
				if (data.Colors.length === 0)
					throw new Error('You need to add at least 1 color');
				data.Colors = JSON.stringify(data.Colors);

				$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
				$.Dialog.center();

				$.post('/colorguide/'+(editing?'set':'make')+'cg'+(editing?'/'+groupID:''), data, $.mkAjaxHandler(function(){
					if (this.status){
						if (this.cg || this.cgs){
							if (this.cg){
								$group.children('[data-hasqtip]').qtip('destroy', true);
								$group.html(this.cg);
							}
							else if (this.cgs)
								$('#p'+ponyID).find('ul.colors').html(this.cgs);
							window.tooltips();
							ctxmenus();
						}
						$.Dialog.close();
					}
					else handleError.call(this);
				}));
			});
		});
	}

	var $tags;
	$list.on('page-switch',function(){
		$list.find('button.edit').on('click',function(){
			var $this = $(this),
				ponyID = $this.parents('li').attr('id').substring(1),
				ponyName = $this.parent().text().trim(),
				title = 'Editing appearance: '+ponyName;

			$.Dialog.wait(title, 'Retrieving appearance details from server');

			$.post('/colorguide/get/'+ponyID,$.mkAjaxHandler(function(){
				var data = this;
				if (data.status){
					data.ponyID = ponyID;
					mkPonyEditor($this, title, data);
				}
				else $.Dialog.fail(title, this.message);
			}));
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
						$.Dialog.success(title, this.message);

						var path = window.location.pathname;
						if ($list.children().length === 0)
							path = path.replace(/(\d+)$/,function(n){ return n > 1 ? n-1 : n });
						$.toPage(path,true,true);
					}
					else $.Dialog.fail(title, this.message);
				}));
			})
		});

		$tags = $('.tags').ctxmenu(
			[
				{text: 'Create new tag', icon: 'plus', click: function(){
					createNewTag($(this));
				}},
			],
			'Tags'
		);

		ctxmenus();
		window.tooltips();
	}).trigger('page-switch');

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
					if (this.status) $.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Save',function($form){
						var $ErrorNotice = $form.children('.notice').children('p'),
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

							$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
							$.Dialog.center();

							$.post('/colorguide/settag/'+tagID, $form.mkData(), $.mkAjaxHandler(function(){
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
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/);

				if (!tagID) return false;
				tagID = tagID[1];

				var ponyID = $tag.closest('li').attr('id').replace(/\D/g, ''),
					tagName = $tag.text().trim(),
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

				$.Dialog.confirm(title,"Deleting this tag will also remove it from every appearance where it's been used.<br>Are you sure?",['Delete it','Nope'],function(sure){
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
				$.ctxmenu.triggerItem($(this).parent(), 1);
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
					suggestion: Handlebars.compile('<span class="tag id-{{tid}} {{type}}">{{name}}</span>')
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

					$.Dialog._focusedElement = $input.attr('disabled', true);
					$input.parent().addClass('loading');

					$.post('/colorguide/tag/'+ponyID,{ tag_name: tag_name }, $.mkAjaxHandler(function(){
						$input.removeAttr('disabled').parent().removeClass('loading');
						if (this.status){
							$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
							$tagsDiv.children('.tag').remove();
							$tagsDiv.append($(this.tags).filter('span'));
							window.tooltips();
							ctxmenus();
							$input.typeahead('val', '').focus();
						}
						else if (typeof this.cancreate === 'string'){
							var new_name = this.cancreate;
							title = title.replace(tag_name, new_name);
							return $.Dialog.confirm(title, this.message, function(sure){
								if (!sure) return;
								createNewTag($input, new_name);
							});
						}
						else $.Dialog.fail(title, this.message);
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

		$colorGroups = $('ul.colors:not(.static)').attr('data-color', color);
		$colorGroups.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
					var $colors = $(this),
						$li = $colors.parents('li'),
						ponyID = $li.attr('id').substring(1),
						ponyName = $li.children().last().children('strong').text().trim(),
						title = 'Re-order '+color+' groups on appearance: '+ponyName;

					$.Dialog.wait(title, 'Retrieving color group list from server');

					$.post('/colorguide/getcgs/'+ponyID, $.mkAjaxHandler(function(){
						if (!this.status) $.Dialog.fail(this.message);

						var $form = $cgReordering.clone(),
							$cgs = $.mk('ol');

						$.each(this.cgs,function(_, cg){
							$cgs.append($.mk('li').attr('data-id', cg.groupid).text(cg.label));
						});

						$.mk('div').attr('class','cgs').append('<p class=align-center>Drag to re-arrange</p>',$cgs).prependTo($form);
						$.Dialog.center();

						new Sortable($cgs.get(0), {
						    ghostClass: "moving",
						    scroll: false,
						    animation: 150,
						});

						$.Dialog.request(title, $form, 'cg-reorder', 'Save', function($form){
							var $ErrorNotice = $form.children('.notice').children('p'),
								handleError = function(){
									$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
									$.Dialog.center();
								};

							$form.on('submit', function(e){
								e.preventDefault();
								var data = {cgs:[]},
									$cgs = $form.children('.cgs');

								if (!$cgs.length)
									return handleError.call({message:'There are no color groups to re-order'});
								$cgs.find('ol').children().each(function(){
									data.cgs.push($(this).attr('data-id'));
								});
								data.cgs = data.cgs.join(',');

								$ErrorNotice.text('Saving changes...').parent().removeClass('fail').addClass('info').show();
								$.Dialog.center();

								$.post('/colorguide/setcgs/'+ponyID,data,$.mkAjaxHandler(function(){
									if (this.status){
										$colors.html(this.cgs);
										window.tooltips();
										ctxmenus();
										$.Dialog.close();
									}
									else handleError.call(this);
								}));
							});
						});
					}));
				}},
				{text: "Create new group", icon: 'folder-add', click: function(){
					CGEditorMaker('Create color group', $(this).parents('li').attr('id').substring(1));
				}},
			],
			Color+' groups'
		);
		$colorGroups.children('li').filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Edit "+color+" group", icon: 'pencil', click: function(){
					var $this = $(this),
						$group = $this.closest('li'),
						groupID = $group.attr('id').substring(2),
						groupName = $this.children().first().text().replace(/:\s?$/,''),
						title = 'Editing '+color+' group: '+groupName;

					$.Dialog.wait(title, 'Retrieving '+color+' group details from server');

					$.post('/colorguide/getcg/'+groupID,$.mkAjaxHandler(function(){
						if (this.status) CGEditorMaker.call(this, title, $group);
						else $.Dialog.fail(title, data.message);
					}));
				}},
				{text: "Delete "+color+" group", icon: 'trash', click: function(){
					var $group = $(this).closest('li'),
						groupID = $group.attr('id').substring(2),
						groupName = $group.children().first().text().replace(/:\s?$/,''),
						title = 'Delete '+color+' group: '+groupName;
					$.Dialog.confirm(title, 'By deleting this '+color+' group, all '+color+'s within will be removed too.<br>Are you sure?',function(sure){
						if (!sure) return;

						$.Dialog.wait(title, 'Sending removal request');

						$.post('/colorguide/delcg/'+groupID,$.mkAjaxHandler(function(){
							if (this.status){
								$group.children('[data-hasqtip]').qtip('destroy', true);
								$group.remove();
								$.Dialog.close();
							}
							else $.Dialog.fail(title, this.message);
						}));
					});
				}},
				true,
				{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 1);
				}},
				{text: "Create new group", icon: 'folder-add', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 2);
				}},
			],
			function($el){ return Color+' group: '+$el.children().first().text().trim().replace(':','') }
		);
		$.ctxmenu.addItems(
			$colorGroups.children('li').children('span:not(:first-child)'),
			true,
			{text: "Edit "+color+" group", icon: 'pencil', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 1);
			}},
			{text: "Delete "+color+" group", icon: 'trash', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 2);
			}},
			true,
			{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 3);
			}},
			{text: "Create new group", icon: 'folder-add', click: function(){
				$.ctxmenu.triggerItem($(this).parent(), 4);
			}}
		);
	}
	window.ctxmenus = function(){ctxmenus()};
});
