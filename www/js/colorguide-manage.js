/* globals $body,$content,DocReady,HandleNav,mk,Sortable,Bloodhound,Handlebars,PRINTABLE_ASCII_REGEX,Key */
DocReady.push(function ColorguideManage(){
	'use strict';
	var Color = window.Color, color = window.color, TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC, $colorGroups,
		HEX_COLOR_PATTERN = window.HEX_COLOR_PATTERN, isWebkit = 'WebkitAppearance' in document.documentElement.style,
		EQG = window.EQG, EQGRq = EQG?'?eqg':'', AppearancePage = !!window.AppearancePage;

	var $spriteUploadForm = $.mk('form').attr('id', 'sprite-img').html(
		'<p class="align-center"><a href="#upload">Click here to upload a file</a> (max. '+window.MAX_SIZE+') or enter a URL below.</p>' +
		'<label><input type="text" name="image_url" placeholder="External image URL" required></label>' +
		'<p class="align-center">The URL will be checked against the supported provider list, and if an image is found, it\'ll be downloaded to the server and set as this appearance\'s sprite image.</p>'
	);

	var $EpAppearances;
	if (AppearancePage)
		$EpAppearances = $('#ep-appearances').children('p');

	var $list = $('.appearance-list'),
		$ponyEditor = $.mk('form').attr('id','pony-editor')
			.append(
				$.mk('label').append(
					'<span>Name (4-70 chars.)</span>',
					$.mk('input').attr({
						name: 'label',
						placeholder: 'Enter a name',
						pattern: PRINTABLE_ASCII_REGEX.replace('+', '{4,70}'),
						required: true,
						maxlength: 70
					})
				),
				$.mk('label').append(
					'<span>Additional notes (255 chars. max, optional)</span>',
					$.mk('textarea').attr({
						name: 'notes',
						maxlength: 255
					})
				),
				$.mk('label').append(
					'<span>Link to cutie mark (optional)</span>',
					$.mk('input').attr({
						name: 'cm_favme',
						placeholder: 'DeviantArt submission URL',
					}).on('change blur',function(){
						$(this).parent().next().find('input').attr('required', this.value.trim().length === 0);
					})
				),
				$.mk('div').attr('class','align-center').append(
					'<p>Cutie mark orientation</p>',
					$.mk('div').attr('class','radio-group').append(
						$.mk('label').append(
							$.mk('input').attr({
								type: 'radio',
								name: 'cm_dir',
								value: 'ht',
							}),
							"<span>Head-Tail</span>"
						),
						$.mk('label').append(
							$.mk('input').attr({
								type: 'radio',
								name: 'cm_dir',
								value: 'th',
							}),
							"<span>Tail-Head</span>"
						)
					)
				),
				$.mk('label').append(
					"<span>Link to CM preview image (optional)</span>",
					$.mk('input').attr({
						name: 'cm_preview',
						placeholder: 'Separate preview image',
					})
				),
				'<p class="notice info">The preview of the linked CM above will be used if the preview field is left empty.</p>'
			),
		mkPonyEditor = function($this, title, data){
			var editing = !!data,
				$li = $this.parents('[id^=p]'),
				$ponyNotes = $li.find('.notes'),
				$ponyLabel;
			if (AppearancePage){
				if (!editing)
					return;
				$ponyLabel = $content.children('h1');
			}
			else $ponyLabel = $this.parent();

			$.Dialog.request(title,$ponyEditor.clone(true,true),'pony-editor','Save',function($form){
				if (editing){

					$form.find('input[name=label]').val(data.label);
					var $txtarea = $form.find('textarea');
					$txtarea.val(data.notes);
					if (parseInt(data.ponyID, 10) === 0)
						$txtarea.removeAttr('maxlength');
					var $favme = $form.find('input[name=cm_favme]');
					$favme.triggerHandler('change');
					if (data.cm_favme)
						$favme.val(data.cm_favme);
					if (data.cm_preview)
						$form.find('input[name=cm_preview]').val(data.cm_preview);
					if (data.cm_dir)
						$form.find('input[name=cm_dir][value='+data.cm_dir+']').prop('checked', true);
					$form.append(
						$.mk('div').attr('class','align-center').append(
							$.mk('button')
								.attr('class', 'blue typcn typcn-image')
								.text('Update rendered image')
								.on('click',function(e){
									e.preventDefault();
									
									$.Dialog.close();
									$.Dialog.wait('Clear appearance image cache','Clearing cache');

									$.post('/colorguide/clearrendercache/'+data.ponyID,$.mkAjaxHandler(function(){
										if (!this.status) return $.Dialog.fail(false, this.message);

										$.Dialog.success(false, this.message, true);
									}));
								})
						)
					);
				}
				else {
					$form.append(
						$.mk('label').append(
							$.mk('input').attr({
								type: 'checkbox',
								name: 'template'
							}),
							" Pre-fill with common color groups"
						)
					);
					$.Dialog.center();
				}
				$form.on('submit',function(e){
					e.preventDefault();

					var newdata = $form.mkData();
					$.Dialog.wait(false, 'Saving changes');
					if (AppearancePage)
						newdata.noreturn = true;

					$.post('/colorguide/'+(editing?'set/'+data.ponyID:'make')+EQGRq,newdata,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						if (editing){
							if (!AppearancePage){
								$ponyLabel.children().first().text(this.label);
								$ponyNotes.html(this.notes);
								$.Dialog.close();
							}
							else {
								$.Dialog.success(false, 'Metadata updated');
								$.Dialog.wait(false, 'Reloading page');
								HandleNav.reload(function(){
									$.Dialog.close();
								});
							}
						}
						else {
							var id = this.id, info = this.info;
							$.Dialog.success(title, 'Appearance added');
							$.Dialog.wait(title, 'Loading appearance page');
							HandleNav('/colorguide/appearance/'+id,function(){
								if (info)
									$.Dialog.info(title, info);
								else $.Dialog.close();
							});
						}
					}));
				});
			});
		},
		$cgReordering = $.mk('form').attr('id','cg-reorder').append($.mk('div').attr('class','notice').hide().html('<p></p>'));

	$('#new-appearance-btn').on('click',function(){
		mkPonyEditor($(this),'Add new '+(EQG?'Character':'Pony'));
	});

	var $tagEditForm = $.mk('form').attr('id', 'edit-tag');
	$tagEditForm
		.append('<label><span>Tag name (4-30 chars.)</span><input type="text" name="name" required pattern="^.{4,30}$" maxlength="30"></label>');
	var $_typeSelect = $.mk('div').addClass('type-selector');
	$.each(TAG_TYPES_ASSOC,function(type, label){
		var $lbl = $.mk('label'),
			$chx = $.mk('input')
				.attr({
					type: 'checkbox',
					name: 'type',
					value: type
				}).on('change',function(){
					if (this.checked)
						$(this).parent().siblings().find('input').prop('checked', false);
				});
		$lbl.append($chx, $.mk('span').addClass('tag typ-'+type).text(label)).appendTo($_typeSelect);
	});
	$tagEditForm.append(
		$.mk('div').addClass('align-center').append('<span>Tag type (optional)</span><br>',$_typeSelect),
		$.mk('label').append('<span>Tag description (max 255 chars., optional)</span><br><textarea name="title" maxlength="255"></textarea>'),
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

	function createNewTag($tag, name, typehint){
		var title = 'Create new tag',
			$tagsDiv = $tag.closest('.tags'),
			$li = $tagsDiv.closest('[id^=p]'),
			ponyID = $li.attr('id').substring(1),
			ponyName = !AppearancePage
				? $tagsDiv.siblings('strong').text().trim()
				: $content.children('h1').text();

		$.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Create',function($form){
			$form.append(
				$.mk('label').append(
					$.mk('input').attr({type:'checkbox',name:'addto'}).val(ponyID).prop('checked', typeof name === 'string'),
					document.createTextNode(' Add this tag to the appearance "'+ponyName+'" after creation')
				)
			);
			$.Dialog.center();

			if (typeof typehint === 'string' && typeof TAG_TYPES_ASSOC[typehint] !== 'undefined')
				$form.find('input[name=type][value='+typehint+']').prop('checked', true).trigger('change');

			if (typeof name === 'string')
				$form.find('input[name=name]').val(name);

			$form.on('submit', function(e){
				e.preventDefault();

				var data = $form.mkData();
				$.Dialog.wait(false, 'Creating tag');

				$.post('/colorguide/maketag'+EQGRq,data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.tags){
						$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
						$tagsDiv.html(this.tags);
						window.tooltips();
						ctxmenus();
					}
					$.Dialog.close();
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
	$cgEditor.append(
		$.mk('label').append(
			$.mk('span').text('Group name (2-30 chars.)'),
			mk('br'),
			$.mk('input').attr({
				type: 'text',
				name: 'label',
				pattern: PRINTABLE_ASCII_REGEX.replace('+','{2,30}'),
				required: true,
			})
		),
		$.mk('label').append(
			$.mk('input').attr({
				type: 'checkbox',
				name: 'major',
			}).on('click',function(){
				$(this).parent().next()[this.checked?'show':'hide']().children('input').attr('disabled', !this.checked);
				$.Dialog.center();
			}),
			$.mk('span').text('This is a major change')
		),
		$.mk('label').append(
			$.mk('span').text('Change reason (1-255 chars.)'),
			mk('br'),
			$.mk('input').attr({
				type: 'text',
				name: 'reason',
				pattern: PRINTABLE_ASCII_REGEX.replace('+','{1,255}'),
				required: true,
				disabled: true,
			})
		).hide(),
		$.mk('p').attr('class', 'align-center').text('The # symbol is optional, rows with invalid '+color+'s will be ignored. Each color must have a short (3-30 chars.) description of its intended use.'),
		$addBtn,
		$.mk('div').attr('class', 'clrs'),
		$.mk('div').attr('class','notice').hide().html('<p></p>')
	).on('render-color-inputs',function(_, data){
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
			var ponyID;
			if ($group instanceof jQuery){
				var groupID = $group.attr('id').substring(2);
				ponyID = $group.parents('[id^=p]').attr('id').substring(1);
			}
			else ponyID = $group;
		}
		$.Dialog.request(title,$cgEditor.clone(true, true),'cg-editor','Save',function($form){
			var $label = $form.find('input[name=label]'),
				$major = $form.find('input[name=major]'),
				$reason = $form.find('input[name=reason]'),
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
					if (typeof colorid !== 'undefined')
						append.colorid = parseInt(colorid, 10);

					append.label = $row.children('.clrl').val();

					data.Colors.push(append);
				});
				if (data.Colors.length === 0)
					return $.Dialog.fail(false, 'You need to have at least 1 valid color');
				data.Colors = JSON.stringify(data.Colors);

				if ($major.is(':checked')){
					data.major = true;
					data.reason = $reason.val();
				}

				if (AppearancePage){
					data.OUTPUT_COLOR_NAMES = true;
					data.NO_COLON = true;
					data.RETURN_CM_IMAGE = true;
				}

				$.Dialog.wait(false, 'Saving changes');

				$.post('/colorguide/'+(editing?'set':'make')+'cg'+(editing?'/'+groupID:'')+EQGRq, data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.cg || this.cgs){
						if (this.cg){
							$group.children('[data-hasqtip]').qtip('destroy', true);
							$group.html(this.cg);

							if (this.update)
								$group.parents('li').find('.update').html(this.update);
						}
						else if (this.cgs){
							var $pony = $('#p'+ponyID);
							if (this.update)
								$pony.find('.update').html(this.update);
							$pony.find('ul.colors').html(this.cgs);
						}
						window.tooltips();
						ctxmenus();
						if (this.update)
							window.updateTimes();
						if (this.cm_img)
							$('#pony-cm').css('background-image', this.cm_img);
					}
					$.Dialog.close();
				}));
			});
		});
	}

	function ctxmenus(){
		$tags.children('span:not(.ctxmenu-bound)').ctxmenu([
			{text: 'Edit tag', icon: 'pencil', click: function(){
				var $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = 'Editing tag: '+tagName;

				$.Dialog.wait(title, 'Retrieveing tag details from server');

				$.post('/colorguide/gettag/'+tagID+EQGRq,$.mkAjaxHandler(function(){
					var tag = this;
					if (this.status) $.Dialog.request(title,$tagEditForm.clone(true, true),'edit-tag','Save',function($form){
						$form.find('input[name=type][value='+tag.type+']').prop('checked', true);
						$form.find('input[type=text][name], textarea[name]').each(function(){
							var $this = $(this);
							$this.val(tag[$this.attr('name')]);
						});
						$form.on('submit', function(e){
							e.preventDefault();

							var data = $form.mkData();
							$.Dialog.wait(false, 'Saving changes');

							$.post('/colorguide/settag/'+tagID+EQGRq, data, $.mkAjaxHandler(function(){
								if (!this.status) return $.Dialog.fail(false, this.message);

								var $affected = $('.id-'+this.tid);
								$affected.qtip('destroy', true);
								if (this.title) $affected.attr('title', this.title);
								else $affected.removeAttr('title');
								$affected
									.attr('class', 'tag id-'+this.tid+(this.type?' typ-'+this.type:''))
									.text(this.name).data('ctxmenu-items').eq(0).text('Tag: '+this.name);
								$affected.parent().each(function(){ reorder($(this)) });
								window.tooltips();
								$.Dialog.close();
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

				var ponyID = $tag.closest('[id^=p]').attr('id').replace(/\D/g, ''),
					tagName = $tag.text().trim(),
					title = 'Remove tag: '+tagName;

				$.Dialog.confirm(title,"The tag "+tagName+" will be removed from this appearance.<br>Are you sure?",['Remove it','Nope'],function(sure){
					if (!sure) return;

					var data = {tag:tagID};
					$.Dialog.wait(title,'Removing tag');
					if (AppearancePage)
						data.needupdate = '?';

					$.post('/colorguide/untag/'+ponyID+EQGRq,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title, this.message);

						if (this.needupdate === true)
							$EpAppearances.html(this.eps).parent()[this.eps.length?'show':'hide']();
						$tag.qtip('destroy', true);
						$tag.remove();
						$.Dialog.close();
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

					var data = {};
					$.Dialog.wait(title,'Sending removal request');
					if (AppearancePage)
						data.needupdate = '?';
					(function Send(data){
						$.post('/colorguide/deltag/'+tagID+EQGRq,data,$.mkAjaxHandler(function(){
							if (this.status){
								if (this.needupdate === true)
									$EpAppearances.html(this.eps).parent()[this.eps.length?'show':'hide']();
								var $affected = $('.id-' + tagID);
								$affected.qtip('destroy', true);
								$affected.remove();
								$.Dialog.close();
							}
							else if (this.confirm)
								$.Dialog.confirm(false, this.message, ['NUKE TAG','Nevermind'], function(sure){
									if (!sure) return;

									data.sanitycheck = true;
									Send(data);
								});
							else $.Dialog.fail(title, this.message);
						}));
					})(data);
				});
			}},
			$.ctxmenu.separator,
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
		}), insertKeys = [Key.Enter, Key.Comma];
		$tags.children('.addtag').each(function(){
			var $input = $(this),
				ponyID = $input.closest('[id^=p]').attr('id').substring(1);
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

					$.Dialog.setFocusedElement($input.attr('disabled', true));
					$input.parent().addClass('loading');

					var data = {tag_name:tag_name};
					if (AppearancePage)
						data.needupdate = '?';

					$.post('/colorguide/tag/'+ponyID+EQGRq, data, $.mkAjaxHandler(function(){
						$input.removeAttr('disabled').parent().removeClass('loading');
						if (this.status){
							if (this.needupdate === true)
								$EpAppearances.html(this.eps).parent().show();
							$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
							$tagsDiv.children('.tag').remove();
							$tagsDiv.append($(this.tags).filter('span'));
							window.tooltips();
							ctxmenus();
							$input.typeahead('val', '').focus();
						}
						else if (typeof this.cancreate === 'string'){
							var new_name = this.cancreate,
								typehint = this.typehint;
							title = title.replace(tag_name, new_name);
							return $.Dialog.confirm(title, this.message, function(sure){
								if (!sure) return;
								createNewTag($input, new_name, typehint);
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

		$colorGroups = $('ul.colors').attr('data-color', color);
		$colorGroups.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
					var $colors = $(this),
						$li = $colors.closest('[id^=p]'),
						ponyID = $li.attr('id').substring(1),
						ponyName = !AppearancePage
							? $li.children().last().children('strong').text().trim()
							: $content.children('h1').text(),
						title = 'Re-order '+color+' groups on appearance: '+ponyName;

					$.Dialog.wait(title, 'Retrieving color group list from server');

					$.post('/colorguide/getcgs/'+ponyID+EQGRq, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(this.message);

						var $form = $cgReordering.clone(),
							$cgs = $.mk('ol');

						$.each(this.cgs,function(_, cg){
							$cgs.append($.mk('li').attr('data-id', cg.groupid).text(cg.label));
						});

						$.mk('div').attr('class','cgs').append('<p class="align-center">Drag to re-arrange</p>',$cgs).prependTo($form);
						$.Dialog.center();

						new Sortable($cgs.get(0), {
						    ghostClass: "moving",
						    scroll: false,
						    animation: 150,
						});

						$.Dialog.request(title, $form, 'cg-reorder', 'Save', function($form){
							$form.on('submit', function(e){
								e.preventDefault();
								var data = {cgs:[]},
									$cgs = $form.children('.cgs');

								if (!$cgs.length)
									return $.Dialog.fail(false, 'There are no color groups to re-order');
								$cgs.find('ol').children().each(function(){
									data.cgs.push($(this).attr('data-id'));
								});
								data.cgs = data.cgs.join(',');

								$.Dialog.wait(false, 'Saving changes');
								if (AppearancePage){
									data.OUTPUT_COLOR_NAMES = true;
									data.NO_COLON = true;
								}

								$.post('/colorguide/setcgs/'+ponyID+EQGRq,data,$.mkAjaxHandler(function(){
									if (!this.status) return $.Dialog.fail(null, this.message);

									$colors.html(this.cgs);
									window.tooltips();
									ctxmenus();
									$.Dialog.close();
								}));
							});
						});
					}));
				}},
				{text: "Create new group", icon: 'folder-add', click: function(){
					CGEditorMaker('Create '+color+' group', $(this).closest('[id^=p]').attr('id').substring(1));
				}},
				{text: "Apply template (if empty)", icon: 'document-add', click: function(){
					var ponyID = $(this).closest('[id^=p]').attr('id').substring(1);
					$.Dialog.confirm('Apply template on appearance','Add common color groups to this appearance?<br>Note: This will only work if there are no color groups currently present.',function(sure){
						if (!sure) return;

						$.Dialog.wait(false, 'Applying template');

						$.post('/colorguide/applytemplate/'+ponyID+EQGRq,$.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							var $pony = $('#p'+ponyID);
							$pony.find('ul.colors').html(this.cgs);
							window.tooltips();
							ctxmenus();

							$.Dialog.close();
						}));
					});
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

					$.post('/colorguide/getcg/'+groupID+EQGRq,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title, this.message);

						CGEditorMaker.call(this, title, $group);
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

						$.post('/colorguide/delcg/'+groupID+EQGRq,$.mkAjaxHandler(function(){
							if (this.status){
								$group.children('[data-hasqtip]').qtip('destroy', true);
								$group.remove();
								$.Dialog.close();
							}
							else $.Dialog.fail(title, this.message);
						}));
					});
				}},
				$.ctxmenu.separator,
				{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 1);
				}},
				{text: "Create new group", icon: 'folder-add', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 2);
				}},
			],
			function($el){ return Color+' group: '+$el.children().first().text().trim().replace(':','') }
		);
		var $colors = $colorGroups.children('li').find('span[id^=c]:not(:empty)');
		$.ctxmenu.addItems(
			$colors.filter('.ctxmenu-bound'),
			$.ctxmenu.separator,
			{text: "Edit "+color+" group", icon: 'pencil', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 1);
			}},
			{text: "Delete "+color+" group", icon: 'trash', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 2);
			}},
			$.ctxmenu.separator,
			{text: "Re-order "+color+" groups", icon: 'arrow-unsorted', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 3);
			}},
			{text: "Create new group", icon: 'folder-add', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 4);
			}}
		);

		$('.upload-wrap').filter(':not(.ctxmenu-bound)').each(function(){
			var $this = $(this),
				$li = $this.closest('li');
			if (!$li.length)
				$li = $content.children('[id^=p]');
			var ponyID = $li.attr('id').substring(1);
			(function($this, ponyID){
				var imgsrc, hasSprite,
					updateSprite = function(){
						imgsrc = $this.find('img').attr('src');
						hasSprite = imgsrc.indexOf('blank-pixel.png') === -1;
						$this[hasSprite?'removeClass':'addClass']('nosprite');
						$.ctxmenu.setDefault($this, hasSprite ? 1 : 3);
					};
				$this.uploadZone({
					requestKey: 'sprite',
					title: 'Upload sprite',
					accept: 'image/png',
					target: '/colorguide/setsprite/'+ponyID,
				}).on('uz-uploadstart',function(){
					$.Dialog.close();
				}).on('uz-uploadfinish',function(){
					updateSprite();
				}).ctxmenu([
					{text: 'Open image in new tab', icon: 'arrow-forward', attr: {
						href: $this.find('img').attr('src'),
						target: '_blank',
					}},
					{text: 'Copy image URL', icon: 'clipboard', click: function(){
						$.copy($.urlToAbsolute($this.find('img').attr('src')));
					}},
					{text: 'Upload new sprite', icon: 'upload', click: function(){
						var title = 'Upload sprite image',
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

								$.post('/colorguide/setsprite/'+ponyID+EQGRq,{image_url: image_url}, $.mkAjaxHandler(function(){
									if (this.status) $uploadInput.trigger('set-image', [this.path]);
									else $.Dialog.fail(title,this.message);
								}));
							});
						});
					}},
					{text: 'Remove sprite image', icon: 'times', click: function(){
						$.Dialog.confirm('Remove sprite image','Are you sure you want to <strong>permanently delete</strong> the sprite image from the server?',['Wipe it','Nope'],function(sure){
							if (!sure) return;

							$.Dialog.wait(false, 'Removing image');

							$.post('/colorguide/delsprite/'+ponyID, $.mkAjaxHandler(function(){
								if (!this.status) return $.Dialog.fail(false, this.message);

								$this.find('img').attr('src', this.sprite);
								updateSprite();
								$.Dialog.close();
							}));
						});
					}}
				], 'Sprite image').attr('title', isWebkit ? ' ' : '').on('click',function(e, forced){
					if (forced === true) return true;

					e.preventDefault();
					$.ctxmenu.runDefault($this);
				});
				updateSprite();
			})($this, ponyID);
		});
	}
	window.ctxmenus = function(){ctxmenus()};

	var $tags;
	$list.on('page-switch',BindEditTagsHandlers);
	BindEditTagsHandlers();

	function BindEditTagsHandlers(){
		$('button.edit').on('click',function(){
			var $this = $(this),
				$li = $this.closest('[id^=p]'),
				ponyID = $li.attr('id').substring(1),
				ponyName = !AppearancePage
					? $this.parent().text().trim()
					: $content.children('h1').text(),
				title = 'Editing appearance: '+ponyName;

			$.Dialog.wait(title, 'Retrieving appearance details from server');

			$.post('/colorguide/get/'+ponyID+EQGRq,$.mkAjaxHandler(function(){
				var data = this;
				if (data.status){
					data.ponyID = ponyID;
					mkPonyEditor($this, title, data);
				}
				else $.Dialog.fail(title, this.message);
			}));
		}).next('.delete').on('click',function(){
			var $this = $(this),
				$li = $this.closest('[id^=p]'),
				ponyID = $li.attr('id').substring(1),
				ponyName = !AppearancePage
					? $this.parent().text().trim()
					: $content.children('h1').text(),
				title = 'Deleting appearance: '+ponyName;

			$.Dialog.confirm(title,'Deleting this appearance will remove <strong>ALL</strong> of its color groups, the colors within them, and the sprite file, if any.<br>Delete anyway?',function(sure){
				if (!sure) return;

				$.Dialog.wait(title, 'Sending removal request');

				$.post('/colorguide/delete/'+ponyID+EQGRq,$.mkAjaxHandler(function(){
					if (this.status){
						$li.remove();
						$.Dialog.success(title, this.message);

						var path = window.location.pathname;
						if ($list.children().length === 0)
							path = path.replace(/(\d+)$/,function(n){ return n > 1 ? n-1 : n });
						if (AppearancePage){
							$.Dialog.wait('Navigation', 'Loading page 1');
							HandleNav('/colorguide/1',function(){
								$.Dialog.close();
							});
						}
						else $.toPage(path,true,true);
					}
					else $.Dialog.fail(title, this.message);
				}));
			});
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
	}
});
