/* globals $body,$content,DocReady,HandleNav,mk,Sortable,Bloodhound,Handlebars,SHORT_HEX_COLOR_PATTERN,PRINTABLE_ASCII_PATTERN,Key,ace,Time */
$(function(){
	'use strict';

	let TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC, $colorGroups, HEX_COLOR_PATTERN = window.HEX_COLOR_PATTERN,
	isWebkit = 'WebkitAppearance' in document.documentElement.style, EQG = window.EQG, EQGRq = EQG?'?eqg':'',
		AppearancePage = !!window.AppearancePage, PersonalGuide = window.PersonalGuide,
		PGRq = PersonalGuide?`/@${PersonalGuide}`:'',
		ColorTextParseError = function(line, lineNumber, matches){
			let missing = [];
			if (!matches || !matches[1])
				missing.push('HEX color');
			if (!matches || !matches[2])
				missing.push('color name');
			this.message = `Parse error on line ${lineNumber} (shown below)
				<pre style="font-size:16px"><code>${line.replace(/</g,'&lt;')}</code></pre>
				${
					missing.length
					? `The ${missing.join(' and ')} is missing from this line.`
					: 'Please check for any typos before continuing.'
				}`;
			this.lineNumber = lineNumber;
		};

	let $SpriteUploadFormTemplate = $.mk('form','sprite-upload').html(
		(PersonalGuide?`<div class="notice info"><label>About sprites</label><p>Sprites are small, pixelated images showcasing all of the colors a given character has. They are most useful if they contain a full body image of your character with any difficult details highlighted. You can use it together with the notes, adding explanations about anything that might be confusing.</p><p>Sprites have a height limit of 300px, a width limit between 300 and 700 pixels, and are expected to be PNG files with a transparent background.</p><p>We provide templates that fit these guidelines for anyone to use through the <a class="sprite-template-gen">Template Generator</a>. If you decide to use this generator, you must add at least the mane and tail before uploading the sprite to the site.</p><p class="color-red">The staff reserves the right to remove any sprites that do not follow these guidelines.</p></div>`:'')+
		`<p class="align-center"><a class="upload-link">Click here to upload a file</a> (max. ${window.MAX_SIZE}) or enter a URL below.</p>
		<label><input type="text" name="image_url" placeholder="External image URL" required></label>
		<p class="align-center">The URL will be checked against the supported provider list, and if an image is found, it\'ll be downloaded to the server and set as this appearance’s sprite image.</p>`
	);

	let $EpAppearances;
	if (AppearancePage)
		$EpAppearances = $('#ep-appearances');

	$.fn.reorderTags = function(){
		return this.each(function(){
			$(this).children('.tag').sort(function(a, b){
				let regex = /^.*typ-([a-z]+).*$/;
				a = [a.className.replace(regex,'$1'), a.innerHTML.trim()];
				b = [b.className.replace(regex,'$1'), b.innerHTML.trim()];

				if (a[0] === b[0])
					return a[1].localeCompare(b[1]);
				return a[0].localeCompare(b[0]);
			}).appendTo(this);
		});
	};

	let $list = $('.appearance-list'),
		$PonyEditorFormTemplate = $.mk('form','pony-editor')
			.append(
				`<label>
					<span>Name (4-70 chars.)</span>
					<input type="text" name="label" placeholder="Enter a name" pattern="${PRINTABLE_ASCII_PATTERN.replace('+', '{4,70}')}" required maxlength="70">
				</label>
				<div class="label">
					<span>Additional notes (1000 chars. max, optional)</span>
					<div class="ace_editor"></div>
				</div>
				<label><input type='checkbox' name='private'> Make private (only ${PersonalGuide?'you':'admins'} can see added colors)</label>`
			),
		mkPonyEditor = function($this, title, data){
			let editing = !!data,
				$li = $this.parents('[id^=p]'),
				$ponyNotes = $li.find('.notes'),
				$ponyLabel;
			if (AppearancePage){
				if (!editing)
					return;
				$ponyLabel = $content.children('h1');
			}
			else $ponyLabel = $this.siblings().first();

			$.Dialog.request(title,$PonyEditorFormTemplate.clone(true,true),'Save', function($form){
				let appearanceID, session;

				$.getAceEditor(false, 'html', function(mode){
					try {
						let div = $form.find('.ace_editor').get(0),
							editor = ace.edit(div);
						session = $.aceInit(editor, mode);
						session.setMode(mode);
						session.setUseWrapMode(true);

						if (editing && data.notes)
							session.setValue(data.notes);
					}
					catch(e){ console.error(e) }
				});

				if (editing){
					appearanceID = data.appearanceID;
					$form.find('input[name=label]').val(data.label);

					if (data.cm_preview)
						$form.find('input[name=cm_preview]').val(data.cm_preview);
					if (data.cm_dir)
						$form.find('input[name=cm_dir]').enable().filter('[value='+data.cm_dir+']').prop('checked', true);
					if (data.private)
						$form.find('input[name=private]').prop('checked', true);
					$form.append(
						$.mk('div').attr('class','align-center').append(
							$.mk('button')
								.attr('class', 'orange typcn typcn-media-eject')
								.text('Selective wipe')
								.on('click',function(e){
									e.preventDefault();

									// TODO Add some toggleable explanations on what each option clears exactly

									const
										ponyLabel = data.label,
										$form = $.mk('form','selective-wipe').html(
											`<p>Select which of the following items to clear below.</p>
											<label><input type="checkbox" name="wipe_cache"> Clear cached images</label>
											<label><input type="checkbox" name="wipe_cm_tokenized"> Clear tokenized cutie mark</label>
											<label><input type="checkbox" name="wipe_cm_source"> Clear cutie mark source file</label>
											<label><input type="checkbox" name="wipe_cms"> Remove all cutie marks</label>
											<label><input type="checkbox" name="wipe_sprite"> Clear sprite image</label>
											<fieldset>
												<legend>Color Groups</legend>
												<div class="radio-group">
													<label><input type="radio" name="wipe_colors" value="" checked><span>Nothing</span></label>
													<label><input type="radio" name="wipe_colors" value="color_hex"><span>HEX values</span></label>
													<label><input type="radio" name="wipe_colors" value="color_all"><span>Colors</span></label>
													<label><input type="radio" name="wipe_colors" value="all"><span>Color groups</span></label>
												</div>
											</fieldset>
											<label><input type="checkbox" name="wipe_notes"> Clear notes</label>`+
											(PersonalGuide?'':`<label><input type="checkbox" name="wipe_tags"> Remove all tags</label>`)+
											`<label><input type="checkbox" name="mkpriv"> Make private</label>`
										);
									$.Dialog.close();
									$.Dialog.request('Selectively wipe data from '+ponyLabel, $form, 'Clear data', function(){
										$form.on('submit',function(e){
											e.preventDefault();

											let data = $form.mkData();
											if (!data.wipe_colors)
												delete data.wipe_colors;
											if (Object.keys(data).length === 0)
												return $.Dialog.fail(false, "You didn't select any data to clear");
											$.Dialog.clearNotice(/select any data/);
											$.Dialog.confirm(false, 'The action you are about to perform is irreversible. Are you sure you want to proceed?', ['Wipe selected data','Changed my mind'],sure => {
												if (!sure) return;

												$.Dialog.wait(false);
												$.post(`/cg/appearance/selectiveclear/${appearanceID}`,data,$.mkAjaxHandler(function(){
													if (!this.status) return $.Dialog.fail(false, this.message);

													$.Navigation.reload(true);
												}));
											});
										});
									});
								}),
							PersonalGuide ? undefined : $.mk('button')
								.attr('class', 'darkblue typcn typcn-pencil')
								.text('Relations')
								.on('click', function(e){
									e.preventDefault();

									$.Dialog.close();
									$.Dialog.wait('Appearance relation editor', 'Retrieving relations from server');

									let $cgRelations = $content.find('section.related');
									$.post(`${PGRq}/cg/appearance/getrelations/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
										if (!this.status) return $.Dialog.fail(false, this.message);

										let data = this,
											$GuideRelationEditorForm = $.mk('form').attr('id','guide-relation-editor'),
											$selectLinked = $.mk('select').attr({name:'listed',multiple:true}),
											$selectUnlinked = $.mk('select').attr('multiple', true);

										if (data.linked && data.linked.length)
											$.each(data.linked,function(_, el){
												let $option = $.mk('option').attr('value', el.id).text(el.label);
												if (el.mutual)
													$option.attr('data-mutual', true).text('(M) '+$option.text());
												$selectLinked.append($option);
											});
										if (data.unlinked && data.unlinked.length)
											$.each(data.unlinked,function(_, el){
												$selectUnlinked.append($.mk('option').attr('value', el.id).text(el.label));
											});

										let $mutualness = $.mk('div').attr('class','mutual-fieldset-wrap').html(
											`<fieldset>
												<legend data-placeholder="Relation type"></legend>
												<div class="radio-group">
													<label><input type="radio" class="mutual-checkbox" name="mutual" value="1" required disabled><span>Mutual</span></label>
													<label><input type="radio" class="mutual-checkbox" name="mutual" value="0" required disabled><span>One way</span></label>
												</div>
												<div class="notice"></div>
											</fieldset>`),
											$mutualNotice = $mutualness.find('.notice'),
											$mutualLegend = $mutualness.find('legend'),
											mutualTextRegex = /^\(M\) /;

										$mutualness.find('input').on('change click',function(){
											let $this = $(this);
											if ($this.hasAttr('disabled'))
												return;

											let $selected = $selectLinked.children(':selected'),
												mutual = $this.is(':checked') && $this.attr('value') === '1',
												hasDataAttr = $selected.hasAttr('data-mutual');
											if (mutual){
												if (!hasDataAttr)
													$selected.attr('data-mutual', true).text('(M) '+$selected.text());
											}
											else if (hasDataAttr)
												$selected.removeAttr('data-mutual').text($selected.text().replace(mutualTextRegex,''));
										});

										$selectLinked.on('change',function(){
											let $selected = $selectLinked.children(':selected');
											if ($selected.length === 1){
												$mutualness.find('input').enable();
												$mutualNotice.hide();
												$mutualLegend.text('Relation to '+($selected.text().replace(mutualTextRegex,'')));
												$mutualness.find(`input[value="${$selected.hasAttr('data-mutual')?'1':'0'}"]`).prop('checked', true);
											}
											else {
												$mutualness.find('input').disable();
												$mutualLegend.empty();
												if ($selected.length > 1)
													$mutualNotice.attr('class','notice fail').text('Multiple appearances are selected').show();
												else $mutualNotice.attr('class','notice info').text('Select a relation on the left to change the type').show();
											}
										}).triggerHandler('change');

										$GuideRelationEditorForm.append(
											$.mk('div').attr('class','split-select-wrap').append(
												$.mk('div').attr('class','split-select').append("<span>Linked</span>",$selectLinked),
												$.mk('div').attr('class','buttons').append(
													$.mk('button').attr({'class':'typcn typcn-chevron-left green',title:'Link selected'}).on('click', function(e){
														e.preventDefault();

														$selectLinked.append($selectUnlinked.children(':selected').prop('selected', false)).children().sort(function(a,b){
															return a.innerHTML.localeCompare(b.innerHTML);
														}).appendTo($selectLinked);
													}),
													$.mk('button').attr({'class':'typcn typcn-chevron-right red',title:'Unlink selected'}).on('click', function(e){
														e.preventDefault();

														$selectUnlinked.append($selectLinked.children(':selected').prop('selected', false)).children().sort(function(a,b){
															return a.innerHTML.localeCompare(b.innerHTML);
														}).appendTo($selectUnlinked);
														if ($selectLinked.children().length === 0){
															$mutualness.find('input').disable();
															$mutualLegend.empty();
															$mutualness.find('.notice').show();
														}
													})
												),
												$.mk('div').attr('class','split-select').append("<span>Available</span>",$selectUnlinked)
											),
											$mutualness
										);

										$.Dialog.request(false,$GuideRelationEditorForm,'Save', function($form){
											$form.on('submit', function(e){
												e.preventDefault();

												let ids = [],
													mutuals = [];
												$selectLinked.children().each(function(_, el){
													let $el = $(el),
														val = $el.attr('value');
													ids.push(val);
													if ($el.hasAttr('data-mutual'))
														mutuals.push(val);
												});
												$.Dialog.wait(false, 'Saving changes');

												let data = {
													ids: ids.join(','),
													mutuals: mutuals.join(','),
												};
												if (AppearancePage)
													data.APPEARANCE_PAGE = true;
												$.post(`${PGRq}/cg/appearance/setrelations/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
													if (!this.status) return $.Dialog.fail(false, this.message);

													if (this.section){
														if (!$cgRelations.length)
															$cgRelations = $.mk('section')
																.addClass('related')
																.appendTo($content.children().last());
														$cgRelations.html($(this.section).filter('section').html());
													}
													else if ($cgRelations.length){
														$cgRelations.remove();
														$cgRelations = {length:0};
													}
													$.Dialog.close();
												}));
											});
										});
									}));
								}),
							$.mk('button')
								.attr({
									'class': 'darkblue typcn typcn-pencil cg-cm-editor',
								})
								.text('Cutie Mark')
								.on('click',function(e){
									e.preventDefault();

									let ponyLabel = data.label;
									$.Dialog.close();
									$.Dialog.wait('Manage Cutie Mark of '+ponyLabel, 'Retrieving CM data from server');
									$.post(`${PGRq}/cg/appearance/getcms/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
										if (!this.status) return $.Dialog.fail(false, this.message);

										CutieMarkEditor.factory(false, appearanceID, ponyLabel, this);
									}));
								})
						)
					);
				}
				else $form.append("<label><input type='checkbox' name='template'> Pre-fill with common color groups</label>");

				$form.on('submit', function(e){
					e.preventDefault();

					let data = $form.mkData();
					data.notes = session.getValue();
					$.Dialog.wait(false, 'Saving changes');
					if (AppearancePage)
						data.APPEARANCE_PAGE = true;
					if (PersonalGuide)
						data.PERSONAL_GUIDE = true;

					$.post(`${PGRq}/cg/appearance/${editing?`set/${appearanceID}`:'make'}${EQGRq}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						data = this;
						if (editing){
							if (AppearancePage)
								return $.Navigation.reload(true);

							$ponyLabel.text(data.label);
							if (data.newurl)
								$ponyLabel.attr('href',data.newurl);
							$ponyNotes.html(this.notes);
							window.tooltips();
							$.Dialog.close();
							return;
						}

						$.Dialog.success(false, 'Appearance added');

						const carryOn = () => {
							$.Dialog.wait(false, 'Loading appearance page');
							$.Navigation.visit(data.goto);
						};
						if (!data.info)
							return carryOn();
						$.Dialog.segway(title, data.info,'View appearance page',carryOn);
					}));
				});
			});
		};

	$('#new-appearance-btn').on('click',function(){
		let $this = $(this),
			title = $this.text().trim();

		if (!PersonalGuide)
			return mkPonyEditor($this,title);

		$.Dialog.wait(title, 'Checking whether you have any more slots');
		$.post(`${PGRq}/cg/slot-check`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			mkPonyEditor($this,title);
		}));
	});

	let $EditTagFormTemplate = $.mk('form','edit-tag');
	$EditTagFormTemplate.append('<label><span>Tag name (3-30 chars.)</span><input type="text" name="name" required pattern="^[^-][ -~]{1,29}$" maxlength="30"></label>');
	let _typeSelect =
		`<div class='type-selector'>
			<label>
				<input type="radio" name="type" value="" checked>
				<span class="tag">Typeless</span>
			</label>`;
	$.each(TAG_TYPES_ASSOC,function(type, label){
		_typeSelect +=
			`<label>
				<input type="radio" name="type" value="${type}">
				<span class="tag typ-${type}">${label}</span>
			</label>`;
	});
	_typeSelect += '</div>';
	$EditTagFormTemplate.append(
		`<div class="align-center">
			<span>Tag type</span><br>
			${_typeSelect}
		</div>
		<label>
			<span>Tag description (max 255 chars., optional)</span>
			<textarea name="title" maxlength="255"></textarea>
		</label>`,
		$.mk('div').attr('class','align-center edit-only').append(
			$.mk('button').attr('class','blue typcn typcn-flow-merge merge').html('Merge&hellip;'),
			$.mk('button').attr('class','blue typcn typcn-flow-children synon').html('Synonymize&hellip;')
		).on('click','button', function(e){
			e.preventDefault();

			let $form = $(this).closest('form'),
				tag = $form.data('tag'),
				tagName = tag.name,
				tagID = tag.id,
				action = this.className.split(' ').pop();

			$.Dialog.close(function(){
				window.CGTagEditing(tagName, tagID, action, function(action){
					let $affected = $('.tag.id-'+tagID), target;

					if ($affected.length)
						switch (action){
							case "synon":
								target = this.target;
								$affected.addClass('synonym');
								//noinspection ES6ConvertVarToLetConst
								var $ssp = $affected.eq(0).clone().removeClass('ctxmenu-bound'),
									$tsp = new TagSpan(target),
									$tagsDivs = $affected.add($('.tag.id-' + target.id)).closest('.tags');
								$tagsDivs.filter(function(){
									return $(this).children('.id-'+tagID).length === 0;
								}).append($ssp).reorderTags();
								$tagsDivs.filter(function(){
									return $(this).children('.id-'+target.id).length === 0;
								}).append($tsp).reorderTags();
								window.tooltips();
								ctxmenus();
							break;
							case "unsynon":
								if (this.keep_tagged)
									$affected.removeClass('synonym');
								else $affected.remove();
							break;
							case "merge":
								target = this.target;
								$affected.each(function(){
									let $this = $(this);
									if ($this.siblings('.id-'+target.id).length === 0)
										$this.replaceWith(new TagSpan(target));
									else $this.remove();
								});
								window.tooltips();
								ctxmenus();
							break;
						}

					$.Dialog.close();
				});
			});
		})
	);

	class TagSpan extends jQuery {
		constructor(data){
			return super(`<span class="tag id-${data.id}${data.type?` typ-${data.type}`:''}${data.synonym_of?' synonym':''}" data-syn-of="${data.synonym_of}">`)
				.attr('title', data.title)
				.text(data.name);
		}
	}

	function createNewTag($tag, name, typehint){
		let title = 'Create new tag',
			$tagsDiv = $tag.closest('.tags'),
			$li = $tagsDiv.closest('[id^=p]'),
			appearanceID = $li.attr('id').substring(1),
			ponyName = !AppearancePage
				? $tagsDiv.siblings('strong').text().trim()
				: $content.children('h1').text();

		$.Dialog.request(title,$EditTagFormTemplate.clone(true, true),'Create', function($form){
			$form.children('.edit-only').replaceWith(
				$.mk('label').append(
					$.mk('input').attr({type:'checkbox',name:'addto'}).val(appearanceID).prop('checked', typeof name === 'string'),
					` Add this tag to the appearance "${ponyName}" after creation`
				)
			);

			if (typeof typehint === 'string' && typeof TAG_TYPES_ASSOC[typehint] !== 'undefined')
				$form.find(`input[name=type][value=${typehint}]`).prop('checked', true).trigger('change');

			if (typeof name === 'string')
				$form.find('input[name=name]').val(name);

			$form.on('submit', function(e){
				e.preventDefault();

				let data = $form.mkData();
				$.Dialog.wait(false, 'Creating tag');

				if (data.addto && AppearancePage)
					data.APPEARANCE_PAGE = true;

				$.post(`${PGRq}/cg/tag/make${EQGRq}`,data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.tags){
						$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
						$tagsDiv.html(this.tags);
						window.tooltips();
						ctxmenus();
					}
					if (this.needupdate === true){
						let $eps = $(this.eps);
						$EpAppearances.replaceWith($eps);
						$EpAppearances = $eps;
					}
					$._tagAutocompleteCache = {};
					$.Dialog.close();
				}));
			});
		});
	}

	const AppearanceListCache = (function(){
		let _list;
		const fetch = () => {
			return new Promise((fulfill, desert) => {
				if (typeof _list !== 'undefined'){
					fulfill(_list);
					return;
				}

				const data = {};
				if (PersonalGuide)
					data.PERSONAL_GUIDE = PersonalGuide;
				$.post('/cg/colorgroup/appearance-list',data,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail('Appearance list retrieval', this.message);

					_list = this.list;
					fulfill(_list);
				})).fail(function(){
					desert();
				});
			});
		};
		return { read: () => fetch() };
	})();

	const ColorListCache = (function(){
		let _list = {};
		const fetch = appearance_id => {
			return new Promise((fulfill, desert) => {
				if (typeof _list[appearance_id] !== 'undefined'){
					fulfill(_list[appearance_id]);
					return;
				}

				$.post('/cg/colorgroup/list/'+appearance_id,$.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail('Appearance list retrieval', this.message);

					_list[appearance_id] = this.list;
					fulfill(_list[appearance_id]);
				})).fail(function(){
					desert();
				});
			});
		};
		return { read: appearance_id => fetch(appearance_id) };
	})();

	class ColorGroupEditor {
		constructor($group, data){
			this.mode = 'gui';
			this.editing = typeof data === 'object' && data.label && data.Colors;
			if (typeof $group !== 'undefined'){
				if ($group instanceof jQuery){
					this.group_id = $group.attr('id').substring(2);
					this.appearance_id = parseInt($group.parents('[id^=p]').attr('id').substring(1), 10);
				}
				else this.appearance_id = parseInt($group, 10);
			}
			this.colorAppearanceDataCache = {};

			this.templates = {
				$inputMethodDropdown:
					$.mk('select').attr({'class': 'clrmthd', required: true}).html(
						`<option value="hex">Hex</option>
						<option value="link">Link</option>`
					).on('change',e => {
						const
							$this = $(e.target),
							$clr = $this.closest('.clr');
						$clr.removeClass('mthd-hex mthd-link').addClass('mthd-'+$this.children('option:selected').attr('value'));
					}),
				$appearanceSelector:
					$.mk('select').attr('class','clrla').html(
						`<option value="" selected data-default>(appearance)</option>`
					).on('mouseenter',e => {
						const $this = $(e.target);
						if ($this.hasClass('loaded'))
							return;

						$this.disable();
						AppearanceListCache.read().then(list => {
							const
								$pony = $.mk('optgroup').attr('label','Pony Guide'),
								$eqg = $.mk('optgroup').attr('label','EQG Guide'),
								$pcg = $.mk('optgroup').attr('label','Personal Color Guide');
							if (EQG === true) $this.append($eqg, $pony);
							else if (EQG === false) $this.append($pony, $eqg);
							else $this.append($pcg);

							$.each(list,(_, el) => {
								(el.ishuman === null ? $pcg : (el.ishuman ? $eqg : $pony)).append(
									$.mk('option').attr({
										value: el.id,
									}).text(el.label)
								);
							});

							$this.addClass('loaded').enable();
						});
					}).on('change',e => {
						const
							$this = $(e.target),
							$appearance = $this.find('option:selected'),
							appeareance_id = $appearance.attr('value'),
							appearance_name = $appearance.text();
						if (isNaN(appeareance_id))
							return;

						this.loadColorSelectFor($this, appeareance_id, appearance_name);
					}),
				$colorSelector:
					$.mk('select').attr({'class': 'clrlc hidden', disabled: true}).html(
						`<option value="" selected data-default>(color)</option>
						<option value="\n" class="appearance-name-option" disabled></option>
						<option value="\n" data-appearance-switch>(change appearance)</option>
						<option value="\n" disabled>----------</option>`
					).on('change',e => {
						const $this = $(e.target);
						if (!$this.children('option:selected').hasAttr('data-appearance-switch'))
							return;

						$this.addClass('hidden').disable().prev().val('').removeClass('hidden');
					}),
				$colorInput:
					$.mk('input').attr({
						'class': 'clri',
						autocomplete: 'off',
						spellcheck: 'false',
					}).patternAttr(HEX_COLOR_PATTERN).on('keyup change input',(e, override) => {
						ColorGroupEditor.validateColorInput(e, override);
					}).on('paste blur keyup',e => {
						let f = () => this.expandColorInput(e);
						if (e.type === 'paste') setTimeout(f, 10);
						else f();
					}),
				$colorLabel:
					$.mk('input').attr({
						'class': 'clrl',
						list: 'common-color-names',
						pattern: PRINTABLE_ASCII_PATTERN.replace('+', '{3,30}'),
						maxlength: 30,
					}),
				$colorActions:
					$.mk('div').attr('class','clra').append(
						$.mk('span').attr({'class': 'typcn typcn-trash remove red', title: 'Remove'}).on('click',e => {
							const $this = $(e.target);
							$this.closest('.clr').addClass('faded').find('input:not(:disabled), select:not(:disabled)').disable().addClass('fade-disabled');
							$this.addClass('hidden').next().removeClass('hidden');
						}),
						$.mk('span').attr({'class': 'typcn typcn-arrow-back add green hidden', title: 'Restore'}).on('click',e => {
							const $this = $(e.target);
							$this.closest('.clr').removeClass('faded').find('.fade-disabled').enable().removeClass('fade-disabled');
							$this.addClass('hidden').prev().removeClass('hidden');
						}),
						$.mk('span').attr('class','typcn typcn-arrow-move move blue')
					)
			};
			this.$addBtn = $.mk('button').attr('class','typcn typcn-plus green add-color').text('Add new color').on('click',e => {
				e.preventDefault();

				this.addColor();
			});
			this.$editorToggle = $.mk('button').attr('class','typcn typcn-document-text darkblue').text('Plain text editor').on('click',e => {
				e.preventDefault();

				const $btn = $(e.target);
				$btn.disable();
				try {
					this.saveColorInputs();
				}
				catch (error){
					if (!(error instanceof ColorTextParseError))
						throw error;
					this.editor.gotoLine(error.lineNumber);
					this.editor.navigateLineEnd();
					$.Dialog.fail(false, error.message);
					this.editor.focus();
					$btn.enable();
					return;
				}
				$btn.toggleClass('typcn-document-text typcn-edit').toggleHtml(['Plain text editor','Interactive editor']).enable();
				$.Dialog.clearNotice(/Parse error on line \d+ \(shown below\)/);
			});
			this.$form = $.mk('form','cg-editor').append(
				$.mk('label').append(
					`<span>Group name (2-30 chars.)</span>`,
					$.mk('input').attr({
						type: 'text',
						name: 'label',
						pattern: PRINTABLE_ASCII_PATTERN.replace('+','{2,30}'),
						required: true,
						list: 'common-cg-names',
					}).val(data.label),
					`<datalist id="common-cg-names">
						<option>Coat</option>
						<option>Mane & Tail</option>
						<option>Eyes</option>
						<option>Iris</option>
						<option>Cutie Mark</option>
						<option>Magic</option>
					</datalist>`
				),
				PersonalGuide ? undefined : $.mk('label').append(
					$.mk('input').attr({
						type: 'checkbox',
						name: 'major',
					}).on('click change',function(){
						$(this).parent().next()[this.checked?'removeClass':'addClass']('hidden').children('input').attr('disabled', !this.checked);
					}),
					'<span>This is a major change</span>'
				),
				`<label class="hidden">
					<span>Change reason (1-255 chars.)</span>
					<input type='text' name='reason' pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{1,255}')}" required disabled>
				</label>
				<p class="align-center">Each color must have a short (3-30 chars.) description.<br>The eitor rounds RGB values: ≤3 to 0 and ≥252 to 255.</p>`,
				$.mk('div').attr('class', 'btn-group').append(
					this.$addBtn, this.$editorToggle
				),
				$.mk('div').attr('class', 'clrs').append(
					this.makeColorDiv()
				),
				`<datalist id="common-color-names">
					<option>Outline</option>
					<option>Fill</option>
					<option>Shadow Outline</option>
					<option>Shadow Fill</option>
					<option>Gradient Top</option>
					<option>Gradient Middle</option>
					<option>Gradient Bottom</option>
					<option>Hightlight Top</option>
					<option>Hightlight Bottom</option>
				</datalist>`
			).on('submit',e => {
				e.preventDefault();

				try {
					this.saveColorInputs(true, true);
				}
				catch (error){
					if (!(error instanceof ColorTextParseError))
						throw error;
					this.editor.gotoLine(error.lineNumber);
					this.editor.navigateLineEnd();
					$.Dialog.fail(false, error.message);
					this.editor.focus();
					return;
				}

				let data = this.$form.mkData(),
					appearance_id = this.appearance_id;
				data.Colors = this.colorValues;
				if (!this.editing)
					data.ponyid = this.appearance_id;
				if (data.Colors.length === 0)
					return $.Dialog.fail(false, 'You need to add at least one valid color');
				data.Colors = JSON.stringify(data.Colors);

				if (AppearancePage)
					data.APPEARANCE_PAGE = true;
				const $changes = $('#changes');
				if (!$changes.length)
					data.FULL_CHANGES_SECTION = true;

				$.Dialog.wait(false, 'Saving changes');

				$.post(`${PGRq}/cg/colorgroup/${this.editing?`set/${this.group_id}`:'make'}${EQGRq}`, data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.cgs){
						let $pony = $('#p'+appearance_id);
						if (this.cgs)
							$pony.find('ul.colors').html(this.cgs);
						if (!AppearancePage && this.notes){
							let $notes = $pony.find('.notes');
							try {
								$notes.find('.cm-direction').qtip('destroy', true);
							}catch(e){}
							$notes.html(this.notes);
						}
						if (this.update){ // Guide Page
							let $updateDiv = $pony.find('.update');
							if ($updateDiv.length)
								$updateDiv.replaceWith(this.update);
							else $(this.update).insertAfter($pony.find('strong'));
						}
						if (this.changes){ // Appearance Page
							if ($changes.length)
								$changes.replaceWith(this.changes);
							else $(this.changes).insertBefore($('#tags'));
						}

						window.tooltips();
						ctxmenus();
						if (this.update || this.changes)
							Time.Update();
						if (AppearancePage && this.cm_list)
							$('#pony-cm-list').html(this.cm_list);
						$.Dialog.close();
					}
					else $.Dialog.close();
				}));
			});

			if (this.editing)
				this.setColorValues(data.Colors).renderColorInputs();
		}
		static factory(title, $group, data){
			$.Dialog.request(title, new ColorGroupEditor($group, data).getForm(), 'Save');
		}
		static validateColorInput(e, override){
			let $this = $(e.target),
				$cp = $this.prev(),
				color = (typeof override === 'string' ? override : $this.val()).trim(),
				valid = HEX_COLOR_PATTERN.test(color);
			if (valid)
				$cp.removeClass('invalid').css('background-color', color.replace(HEX_COLOR_PATTERN, '#$1'));
			else $cp.addClass('invalid').css('background-color','');
		}
		expandColorInput(e){
			const input = e.target;
			let val = $.hexpand(input.value);
			if (HEX_COLOR_PATTERN.test(val)){
				val = val.replace(HEX_COLOR_PATTERN, '#$1').toUpperCase();
				let $input = $(input),
					rgb = $.hex2rgb(val);
				$.each(rgb, function(channel, value){
					if (value <= 3)
						rgb[channel] = 0;
					else if (value >= 252)
						rgb[channel] = 255;
				});
				val = $.rgb2hex(rgb);
				switch (e.type){
					case 'paste':
						$input.next().focus();
					/* falls through */
					case 'blur':
						$input.val(val);
				}
				$input.trigger('change', [val]).patternAttr(
					SHORT_HEX_COLOR_PATTERN.test(input.value)
						? SHORT_HEX_COLOR_PATTERN
						: HEX_COLOR_PATTERN
				);
			}
		}
		makeColorDiv(color){
			// TODO Organize input options inside a container with a child for each method
			// TODO Add RGB input method alongside Hex and Link
			let $mthd = this.templates.$inputMethodDropdown.clone(true, true),
				$ci = this.templates.$colorInput.clone(true, true),
				$cl = this.templates.$colorLabel.clone(),
				$ca = this.templates.$colorActions.clone(true, true),
				$cla = this.templates.$appearanceSelector.clone(true, true),
				$clc = this.templates.$colorSelector.clone(true, true),
				$el = $.mk('div').attr('class','clr mthd-hex'),
				$cid;
			$el.append($mthd,$cla,$clc);

			if (typeof color === 'object'){
				if (color.hex)
					$ci.val(color.hex.toUpperCase());
				if (color.label)
					$cl.val(color.label);
				if (color.id)
					$cid = $.mk('span').attr('class','clrid').text('ID:'+color.id);
				if (color.linked_to){
					$mthd.val('link').triggerHandler('change');

					// Save appearance data for when switching back from plaintext editor
					if (typeof color.appearance !== 'undefined')
						this.colorAppearanceDataCache[color.id] = color.appearance;
					else color.appearance =  this.colorAppearanceDataCache[color.id];

					this.loadColorSelectFor($cla, color.appearance.id, color.appearance.label).then(function($colorSelector){
						$colorSelector.val(color.linked_to);
					});
				}
			}

			$el.append("<span class='clrp'></span>",$ci,$cl,$cid,$ca);
			$ci.triggerHandler('change');

			return $el;
		}
		addColor(){
			let $colors = this.$form.children('.clrs');
			if (!$colors.length){
				$colors = $.mk('div').attr('class', 'clrs');
				this.$form.append($colors);
			}

			if (this.mode === 'gui'){
				const $div = this.makeColorDiv();
				$colors.append($div);
				$div.find('.clri').focus();
			}
			else {
				this.editor.clearSelection();
				this.editor.navigateLineEnd();
				let curpos = this.editor.getCursorPosition(),
					trow = curpos.row+1,
					emptyLine = curpos.column === 0,
					copyHashEnabled = window.copyHashEnabled();

				if (!emptyLine)
					trow++;

				this.editor.insert((!emptyLine?'\n':'')+(copyHashEnabled?'#':'')+'\t');
				this.editor.gotoLine(trow,Number(copyHashEnabled));
				this.editor.focus();
			}
		}
		renderColorInputs(){
			let $colors = this.$form.children('.clrs');

			$colors.empty();
			$.each(this.colorValues, (_, color) => {
				$colors.append(this.makeColorDiv(color));
			});

			this.destroySortable();
			this.sortable = new Sortable($colors.get(0), {
				handle: ".move",
				ghostClass: "moving",
				scroll: false,
				animation: 150,
			});
		}
		saveColorInputs(storeState, strict){
			let $colors = this.$form.children('.clrs');
			if (this.mode === 'gui'){
				// Saving
				let data = [];
				$colors.children('.clr').each(function(){
					const
						$row = $(this),
						$method = $row.children('.clrmthd'),
						method = $method.is(':disabled') ? null : $method.find('option:selected').attr('value'),
						$clrid = $row.children('.clrid'),
						id = $clrid.length ? $clrid.text().replace('ID:','') : void 0;

					switch (method){
						case "hex":
							const
								$ci = $row.children('.clri'),
								val = $ci.val(),
								valid = HEX_COLOR_PATTERN.test(val);

							if (!valid && (val.length || strict))
								return;

							data.push({
								id,
								hex: valid ? $.hexpand(val).toUpperCase().replace(HEX_COLOR_PATTERN, '#$1') : undefined,
								label: $row.children('.clrl').val(),
							});
						break;
						case "link":
							const $clc = $row.children('.clrlc');
							data.push({
								id,
								hex: undefined,
								label: $row.children('.clrl').val(),
								linked_to: $clc.val(),
							});
						break;
					}
				});
				this.colorValues = data;
				if (storeState)
					return;

				// Switching
				let editorContent = ['// One color per line, e.g. #012ABC Fill'];
				$.each(data, (_, color) =>{
					let line = [];

					if (typeof color === 'object'){
						if (color.linked_to)
							line.push('@'+color.linked_to);
						else line.push(color.hex ? color.hex : '#');
						line.push(color.label || '');
						if (color.id)
							line.push('ID:'+color.id);
					}

					editorContent.push(line.join('\t'));
				});

				this.destroySortable();
				$colors.unbind().hide().text(editorContent.join('\n') + '\n');

				// Create editor
				$.getAceEditor(false, 'colorguide',mode => {
					$colors.show();
					this.editor = ace.edit($colors[0]);
					let session = $.aceInit(this.editor, mode);
					session.setTabSize(8);
					session.setMode(mode);
					this.editor.navigateFileEnd();
					this.editor.focus();
					this.mode = 'text';
				});
			}
			else {
				// Saving
				this.colorValues = ColorGroupEditor.parseColorsText(this.editor.getValue(), storeState || strict);
				if (storeState)
					return;

				// Switching
				this.editor.destroy();
				this.editor = null;
				$colors.empty().unbind().attr('class','clrs');
				this.renderColorInputs();
				this.mode = 'gui';
			}
		}
		static parseColorsText(text, strict){
			let colors = [],
				lines = text.split('\n');

			for (let lineIndex = 0, lineCount = lines.length; lineIndex < lineCount; lineIndex++){
				let line = lines[lineIndex];

				// Comment or empty line
				if (/^(\/\/.*)?$/.test(line))
					continue;

				const trimmedLine = line.trim();
				if (trimmedLine === '#'){
					colors.push({
						hex: undefined,
						label: '',
					});
					continue;
				}

				const matches = trimmedLine.match(/^(?:#?([a-f\d]{6}|[a-f\d]{3})?|@(\d+))(?:\s*([ -~]{3,30}))?(?:\s*ID:(\d+))?$/i);
				// Valid line
				if (matches && matches[3] && (strict ? matches[1] : true)){
					colors.push({
						hex: matches[1] ? $.hexpand(matches[1]) : undefined,
						label: matches[3],
						id: matches[4],
						linked_to: matches[2] ? matches[2] : undefined,
					});
					continue;
				}

				// Invalid line
				throw new ColorTextParseError(line, lineIndex+1, matches);
			}

			return colors;
		}
		setColorValues(values){
			this.colorValues = values;

			return this;
		}
		getForm(){
			return this.$form;
		}
		destroySortable(){
			if (typeof this.sortable !== 'undefined'){
				this.sortable.destroy();
				this.sortable = void 0;
			}
		}
		loadColorSelectFor($this, appeareance_id, appearance_name){
			const
				$colorSelector = $this.next(),
				color_id = parseInt($this.siblings().filter('.clrid').text().replace('ID:',''),10);
			$this.disable();
			return new Promise(fulfill => {
				ColorListCache.read(appeareance_id).then(function(groups){
					$colorSelector.children('optgroup').remove();
					$colorSelector.children('.appearance-name-option').text(appearance_name);

					$.each(groups,(_, group) => {
						const $og = $.mk('optgroup').attr('label', group.label);
						$colorSelector.append($og);

						$.each(group.colors,(_, color) => {
							$og.append(
								$.mk('option').attr({
									value: color.id,
									disabled: typeof color.id === 'undefined' || color.id === color_id,
								}).text(color.label)
							);
						});
					});

					$this.enable().addClass('hidden');
					$colorSelector.enable().val('').removeClass('hidden');
					fulfill($colorSelector);
				});
			});
		}
	}

	class CutieMarkEditor {
		constructor(appearance_id, appearance_label, data){
			this.appearance_id = appearance_id;
			this.appearance_label = appearance_label;
			this.$cmSection = $content.find('section.approved-cutie-mark');

			this.$CMPreview = $.mk('ul').attr('class','dialog-preview');
			this.$CMList = $.mk('ul').attr('class','cm-list');
			this.$AddNewButton = $.mk('button').attr('class','green typcn typcn-plus').text('Add new cutie mark').on('click',e => {
				e.preventDefault();

				this.$CMList.append(this.crateCutiemarkDataLi());
			});
			this.$DeleteButton = $.mk('button').attr('class','red typcn typcn-trash').text('Delete all').on('click',e => {
				e.preventDefault();

				this.$CMList.children(':not(.faded)').find('legend > .remove').trigger('click');
				this.$DeleteButton.disable('noop-disabled');
			});
			this.$BottomActionGroup = $.mk('div').attr('class','btn-group').append(
				this.$AddNewButton
			);
			this.$form = $.mk('form','cm-data-editor').append(
				this.$CMList,
				this.$BottomActionGroup,
				$.mk('div').attr('class','notice info').append(
					$.mk('p').append(
						'<strong>Potential issues:</strong> ',
						'<button>File size issue</button>',
						'<button>Inverted colors</button>',
						'<button>Gradients to black</button>',
						'<button>Blank space around</button>',
						'<button class="darkblue typcn typcn-minus" disabled>Close</button>'
					),
					`<div class="issue-descriptions">
						<p class="hidden">Generally vector files are very light (~10KB max.) so if your file exceeds 1 MB you will see an error. This can indicate that an embedded image (such as a screencap) was left inside the vector file.</p>
						<p class="hidden">If you see any inverted colors that means those colors are not in the guide. Make sure the colors that are displayed incorrectly match the ones in the guide.</p>
						<p class="hidden">If you see gradients to black throughout then those colors used Inkscape's Swatches feature and the site simulates how the vector would appear in Adobe Illustator. You'll have to change those colors to regular fills to avoid this.</p>
						<div class="hidden">
							<p>If the cutie mark has a lot of transparent space around it that means the canvas/artboard is not cropped properly. This is strongly recommended for optimal display on the site and to make it easier to reuse. To fix this:</p>
							<p><strong>In Illustrator:</strong> Object &rsaquo; Artboards (at the bottom) &rsaquo; Fit to Artwork Bounds</p>
							<p><strong>In Inkscape:</strong> File &rsaquo; Document Properties&hellip; &rsaquo; Custom size &rsaquo; Resize page to content&hellip; &rsaquo; Resize page to drawing or selection</p>
					</div>`
				).on('click','button',e => {
					e.preventDefault();

					const $btn = $(e.target);
					$btn.disable().siblings().enable();
					$btn.parent().next().children().addClass('hidden').eq($btn.index()-1).removeClass('hidden');
				})
			).on('submit',e => {
				e.preventDefault();

				let CMData = [], stahp = false;
				this.$CMList.children(':not(.faded)').each((i, el) => {
					const
						$li = $(el),
						data = {};

					if ($li.hasAttr('id'))
						data.id = parseInt($li.attr('id').replace(/\D/g,''));

					const
						$svgReplace = $li.find('.svg-replace'),
						$replaceCheckbox = $svgReplace.find('input[type="checkbox"]');
					if ($replaceCheckbox.is(':checked')){
						data.svgdata = $svgReplace.find('.svg-replace-preview').data('svgdata');

						if (!data.svgdata){
							stahp = true;
							return $.Dialog.fail(false, `SVG data missing for ${$.nth(i+1)} cutie mark`);
						}
					}

					data.facing = $li.find('.radio-group.orientation input:checked').attr('value');

					data.attribution = $li.find('.radio-group.attrib-method-radios input:checked').attr('value');

					const $attribData = $li.find('.attrib-method-list input:not(:disabled)');

					if ($attribData.length)
						data[$attribData.attr('name')] = $attribData.val();

					data.rotation = $li.find('.rotation-range').val();

					CMData.push(data);
				});
				if (stahp)
					return;

				const data = { CMData: JSON.stringify(CMData) };
				if (AppearancePage)
					data.APPEARANCE_PAGE = true;
				$.Dialog.wait(false,'Saving cutie mark data');
				$.post(`${PGRq}/cg/appearance/setcms/${appearance_id}${EQGRq}`,data,$.mkAjaxHandler(data => {
					if (!data.status) return $.Dialog.fail(false, data.message);

					$.Dialog.close();
					if (this.$cmSection.length){
						this.$cmSection.children(':not(h2,p)').remove();
						this.$cmSection.removeClass('hidden').append(data.html);
					}
				}));
			}).on('change input','.rotation-range',function(e){
				let $this = $(e.target),
					val = $this.val();
				$this.prev().children('.rotation-display').text(val);
			});

			if (data.cms.length){
				$.each(data.cms,(i,el)=>{
					this.$CMList.append(this.crateCutiemarkDataLi(el));
				});
				this.updateRanges();
				this.$BottomActionGroup.append(this.$DeleteButton);
			}
			else {
				this.$CMList.append(this.crateCutiemarkDataLi());
			}
		}
		getForm(){
			return this.$form;
		}
		updateRange(range){
			const event = $.Event('change');
			event.target = range;
			this.$form.trigger(event);
		}
		updateRanges(){
			this.$CMList.find('.rotation-range').each((_, el) => {
				this.updateRange(el);
			});
		}
		crateCutiemarkDataLi(el){
			const editing = typeof el !== 'undefined';
			if (!editing)
				el = {};

			const radioGrouping = {
				facing: el.id ? 'facing-'+el.id : $.randomString(),
				attribution: el.id ? 'attribution-'+el.id : $.randomString(),
			};
			let $facingSelector = $.mk('div').html(
				`<p>Body orientation</p>
				<div class="radio-group orientation">
					<label><input type="radio" name="${radioGrouping.facing}" value="left" required><span>Left</span></label>
					<label><input type="radio" name="${radioGrouping.facing}" value="right" required><span>Right</span></label>
					<label><input type="radio" name="${radioGrouping.facing}" value="" required><span>Symmetrical</span></label>
				</div>`
			);
			if (typeof el.facing === 'string' || el.facing === null)
				$facingSelector.find(`input[value='${el.facing === null ? '' : el.facing}']`).prop('checked', true);
			const rotation = typeof el.rotation !== 'undefined' ? el.rotation : 0;

			const switchAttribMethod = (el, value) => {
				const
					$this = $(el),
					$methodListItems = $this.closest('.label').next().children();
				$methodListItems.addClass('hidden').find('input').disable('attrib-disabled');
				if ($this.is(':checked'))
					$methodListItems.filter('.' + value).removeClass('hidden').find('input').enable('attrib-disabled');
			};

			const $attributionRadios = $.mk('div').attr('class','label').html(
				`<p>Attribution</p>
				<div class="radio-group attrib-method-radios">
					<label><input type="radio" name="${radioGrouping.attribution}" value="none" required checked><span>None</span></label>
					<label><input type="radio" name="${radioGrouping.attribution}" value="user" required><span>User</span></label>
					<label><input type="radio" name="${radioGrouping.attribution}" value="deviation" required><span>Deviation</span></label>
				</div>`
			).on('change input','.attrib-method-radios input',e => {
				switchAttribMethod(e.target, e.target.value);
			});

			const $attributionMethodList = $.mk('div').attr('class','attrib-method-list').append(
				`<div class="attrib-method user hidden">
					<label>
						<span>Username</span>
						<input type="text" name="username" maxlength="20" required disabled class="attrib-disabled">
					</label>
				</div>
				<div class="attrib-method deviation hidden">
					<label>
						<span>Deviation link</span>
						<input type="url" name="deviation" required disabled class="attrib-disabled">
					</label>
				</div>`
			);
			if (editing){
				if (el.deviation){
					$attributionMethodList.find('input[name="deviation"]').val(el.deviation).enable('attrib-disabled').closest('.hidden').removeClass('hidden');
					$attributionRadios.find('input[value="deviation"]').prop('checked', true);
				}
				if (el.username){
					const $un = $attributionMethodList.find('input[name="username"]').val(el.username);
					if (!el.deviation){
						$un.enable('attrib-disabled').closest('.hidden').removeClass('hidden');
						$attributionRadios.find('input[value="user"]').prop('checked', true);
					}
				}
			}

			const
				$collapseButton = $.mk('button').attr({'class':'btn typcn typcn-minus collapse',title:'Hide inputs but retain values'}).text('Collapse').on('click',e => {
					e.preventDefault();

					const
						$this = $(e.target),
						$li = $this.closest('li');
					$this.parent().nextAll(':not(.hidden)').addClass('hidden collapse-hidden');
					$this.addClass('hidden').next().removeClass('hidden');
					$li.addClass('collapsed');
				}),
				$expandButton = $.mk('button').attr({'class':'btn typcn typcn-plus hidden expand',title:'Reveal inputs'}).text('Expand').on('click',e => {
					e.preventDefault();

					const
						$this = $(e.target),
						$li = $this.closest('li');
					$this.parent().nextAll('.collapse-hidden').removeClass('hidden collapse-hidden');
					$this.addClass('hidden').prev().removeClass('hidden');
					$li.removeClass('collapsed');
				}),
				$removeButton = $.mk('button').attr({'class':'btn red typcn typcn-trash remove',title:'Delete cutie mark on save'}).text('Remove').on('click',e => {
					e.preventDefault();

					const
						$this = $(e.target),
						$li = $this.closest('li');
					$li.addClass('faded').find('input:not(:disabled), select:not(:disabled)').disable().addClass('fade-disabled');
					$this.addClass('hidden').next().removeClass('hidden');
					$this.siblings('.collapse:not(.hidden)').trigger('click');
					if ($li.siblings(':not(.faded)').length === 0)
						this.$DeleteButton.disable('noop-disabled');
				}),
				$restoreButton = $.mk('button').attr({'class':'btn green typcn typcn-arrow-back hidden restore',title:"Don't delete cutie mark on save"}).text('Restore').on('click',e => {
					e.preventDefault();

					const
						$this = $(e.target),
						$li = $this.closest('li');
					$li.removeClass('faded').find('.fade-disabled').enable().removeClass('fade-disabled');
					$this.addClass('hidden').prev().removeClass('hidden');
					this.$DeleteButton.enable('noop-disabled');
					$this.siblings('.expand:not(.hidden)').trigger('click');
				}),
				fileAction = (editing?'Replace':'Upload')+' SVG file';
			const $li = $.mk('li').append(
				$.mk('fieldset').append(
					$.mk('legend').append(
						`<span>${editing ? 'Cutie Mark #'+el.id : 'New Cutie Mark'}</span>`,
						$collapseButton,
						$expandButton,
						$removeButton,
						$restoreButton
					),
					$.mk('label').attr('class','svg-replace').append(
						$.mk('span').append(
							$.mk('input').attr({
								type: 'checkbox',
								checked: !editing,
								disabled: !editing,
								'class': !editing ? 'hidden' : undefined,
							}).on('click',e => {
								if (e.target.readOnly)
									return false;
							}).on('change input',e => {
								const
									checked = e.target.checked,
									$el = $(e.target).parent().next();
								$el[checked?'removeClass':'addClass']('hidden');
								if (checked){
									const
										$svgcont = $el.children('.svgcont'),
										ogbg = $svgcont.attr('data-ogbg');
									if (ogbg){
										$el.removeData('svgdata').removeData('svgel');
										$svgcont.backgroundImageUrl(ogbg);
									}
									if (!$el.hasClass('upload-wrap')){
										$el.addClass('upload-wrap').uploadZone({
											requestKey: 'file',
											title: fileAction,
											accept: '.svg,.svgz,image/svg+xml',
											target: '/cg/sanitizesvg/'+this.appearance_id,
											helper: true,
										}).on('uz-uploadfinish',(_, data) => {
											if (data && data.svgel)
												$el.data({
													svgdata: data.svgdata,
													svgel: data.svgel,
												}).children('.svgcont').backgroundImageUrl(
													'data:image/svg+xml;utf8,'+encodeURI(data.svgel)
												);
										});
									}
								}
							}),
							`<span>${fileAction}</span>`
						),
						$.mk('div').attr('class','svg-replace-preview hidden').html('<div class="svgcont"></div>')
					),
					$facingSelector,
					$attributionRadios,
					$attributionMethodList,
					$.mk('div').append(
						`<span>Rotation: <span class='rotation-display'>${rotation}</span></span>`,
						$.mk('input').attr({
							type: 'range',
							min: -45,
							max: 45,
							step: 1,
							'class': 'rotation-range',
							required: true,
						}).val(rotation)
					),
					`<div class="notice info">Rotation does not affect the original image file, only the way it's displayed on the site.</div>`
				)
			);
			if (el.id)
				$li.attr('id', 'cmdata-'+el.id);
			if (el.rendered)
				$li.find('.svgcont').attr('data-ogbg',el.rendered);
			$li.find('.svg-replace input:checked').trigger('change');
			return $li;
		}
		static factory(title, appearance_id, appearance_label, data){
			$.Dialog.request(title, new CutieMarkEditor(appearance_id, appearance_label, data).getForm(), 'Save');
		}
	}

	let $tags;
	function ctxmenus(){
		$tags = $('.tags');
		$tags.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: 'Create new tag', icon: 'plus', click: function(){
					createNewTag($(this));
				}},
			],
			'Tags'
		);
		$tags.children('span:not(.ctxmenu-bound)').ctxmenu([
			{text: 'Edit tag', icon: 'pencil', click: function(){
				let $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = `Editing tag: ${tagName}`;

				$.Dialog.wait(title, 'Retrieveing tag details from server');

				$.post(`${PGRq}/cg/tag/get/${tagID}${EQGRq}`,$.mkAjaxHandler(function(){
					let tag = this;
					if (this.status) $.Dialog.request(title,$EditTagFormTemplate.clone(true, true).data('tag', tag),'Save', function($form){
						$form.find(`input[name=type][value=${tag.type}]`).prop('checked', true);
						$form.find('input[type=text][name], textarea[name]').each(function(){
							let $this = $(this);
							$this.val(tag[$this.attr('name')]);
						});
						$form.on('submit', function(e){
							e.preventDefault();

							let data = $form.mkData();
							if (AppearancePage)
								data.APPEARANCE_PAGE = $tag.closest('div[id^=p]').attr('id').replace(/\D/g, '');
							$.Dialog.wait(false, 'Saving changes');

							$.post(`${PGRq}/cg/tag/set/${tagID}${EQGRq}`, data, $.mkAjaxHandler(function(){
								if (!this.status) return $.Dialog.fail(false, this.message);

								let data = this,
									$affected = $('.id-'+data.id);
								$affected.qtip('destroy', true);
								if (data.title) $affected.attr('title', data.title);
								else $affected.removeAttr('title');
								$affected.text(data.name).data('ctxmenu-items').eq(0).text(`Tag: ${data.name}`);
								$affected.each(function(){
									if (/typ-[a-z]+/.test(this.className))
										this.className = this.className.replace(/typ-[a-z]+/, data.type ? `typ-${data.type}` : '');
									else if (data.type)
										this.className += ` typ-${data.type}`;
									$(this)[data.synonym_of?'addClass':'removeClass']('synonym').parent().reorderTags();
								});
								window.tooltips();

								if (AppearancePage && data.needupdate){
									let $newEpSection = $(data.eps);
									$EpAppearances.replaceWith($newEpSection);
									$EpAppearances = $newEpSection;
								}
								$.Dialog.close();
							}));
						});
					});
					else $.Dialog.fail(title, this.message);
				}));
			}},
			{text: 'Remove tag', icon: 'minus', click: function(){
				let $tag = $(this),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/);

				if (!tagID) return false;
				tagID = tagID[1];

				let appearanceID = $tag.closest('[id^=p]').attr('id').replace(/\D/g, ''),
					tagName = $tag.text().trim(),
					title = `Remove tag: ${tagName}`;

				$.Dialog.confirm(title,`The tag <strong>${tagName}</strong> will be removed from this appearance.<br>Are you sure?`,['Remove it','Nope'], function(sure){
					if (!sure) return;

					let data = {tag:tagID};
					$.Dialog.wait(title,'Removing tag');
					if (AppearancePage)
						data.APPEARANCE_PAGE = true;

					$.post(`${PGRq}/cg/appearance/untag/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title, this.message);

						if (this.needupdate === true){
							let $eps = $(this.eps);
							$EpAppearances.replaceWith($eps);
							$EpAppearances = $eps;
						}
						$tag.qtip('destroy', true);
						$tag.remove();
						$('.tag.synonym').filter(`[data-syn-of=${tagID}]`).remove();
						$.Dialog.close();
					}));
				});
			}},
			{text: 'Delete tag', icon: 'trash', click: function(){
				let $tag = $(this),
					tagName = $tag.text().trim(),
					tagID = $tag.attr('class').match(/id-(\d+)(?:\s|$)/)[1],
					title = 'Detele tag: '+tagName;

				$.Dialog.confirm(title,"Deleting this tag will also remove it from every appearance where it’s been used.<br>Are you sure?",['Delete it','Nope'], function(sure){
					if (!sure) return;

					let data = {};
					if (AppearancePage)
						data.APPEARANCE_PAGE = $tag.closest('[id^=p]').attr('id').substring(1);
					(function send(data){
						$.Dialog.wait(title,'Sending removal request');

						$.post(`${PGRq}/cg/tag/del/${tagID}${EQGRq}`,data,$.mkAjaxHandler(function(){
							if (this.status){
								if (this.needupdate === true){
									let $eps = $(this.eps);
									$EpAppearances.replaceWith($eps);
									$EpAppearances = $eps;
								}
								let $affected = $('.id-' + tagID);
								$affected.qtip('destroy', true);
								$affected.remove();
								$._tagAutocompleteCache = {};
								$.Dialog.close();
							}
							else if (this.confirm)
								$.Dialog.confirm(false, this.message, ['NUKE TAG','Nevermind'], function(sure){
									if (!sure) return;

									data.sanitycheck = true;
									send(data);
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
		], $el => `Tag: ${$el.text().trim()}` );

		let insertKeys = [Key.Enter, Key.Comma];
		$tags.children('.addtag').each(function(){
			let $input = $(this),
				appearanceID = $input.closest('[id^=p]').attr('id').substring(1);
			$input.autocomplete(
				{ minLength: 3 },
				[
					{
						name: 'tags',
						display: 'name',
						source: (query, callback) => {
							if (typeof $._tagAutocompleteCache === 'undefined')
								$._tagAutocompleteCache = {};
							else if (typeof $._tagAutocompleteCache[query] !== 'undefined')
								return callback($._tagAutocompleteCache[query]);
							$.get(`${PGRq}/cg/get-tags?s=`+encodeURIComponent(query), $.mkAjaxHandler(function(){
								callback($._tagAutocompleteCache[query] = this);
							}));
						},
						templates: {
							suggestion: Handlebars.compile('<span class="tag id-{{tid}} {{type}} {{#if synonym_of}}synonym{{else}}monospace{{/if}}">{{name}} <span class="uses">{{#if synonym_of}}<span class="typcn typcn-flow-children"></span>{{synonym_target}}{{else}}{{uses}}{{/if}}</span></span>')
						}
					}
				]
			);
			$input.on('keydown', function(e){
				if (insertKeys.includes(e.keyCode)){
					e.preventDefault();
					let tag_name = $input.val().trim(),
						$tagsDiv = $input.parents('.tags'),
						$ponyTags = $tagsDiv.children('.tag'),
						title = `Adding tag: ${tag_name}`;

					if ($ponyTags.filter(function(){ return this.innerHTML.trim() === tag_name }).length > 0)
						return $.Dialog.fail(title, 'This appearance already has this tag');

					$.Dialog.setFocusedElement($input.attr('disabled', true));
					$input.parent().addClass('loading');
					$input.autocomplete('val', tag_name);

					let data = {tag_name:tag_name};
					if (AppearancePage)
						data.APPEARANCE_PAGE = true;

					$.post(`${PGRq}/cg/appearance/tag/${appearanceID}${EQGRq}`, data, $.mkAjaxHandler(function(){
						if (this.status){
							if (this.needupdate === true){
								let $eps = $(this.eps);
								$EpAppearances.replaceWith($eps);
								$EpAppearances = $eps;
							}
							$tagsDiv.children('[data-hasqtip]').qtip('destroy', true);
							$tagsDiv.children('.tag').remove();
							$tagsDiv.append($(this.tags).filter('span'));
							window.tooltips();
							ctxmenus();
							$._tagAutocompleteCache = {};
							$input.autocomplete('val', '').focus();
						}
						else if (typeof this.cancreate === 'string'){
							let new_name = this.cancreate,
								typehint = this.typehint;
							title = title.replace(tag_name, new_name);
							return $.Dialog.confirm(title, this.message, function(sure){
								if (!sure) return;
								createNewTag($input, new_name, typehint);
							});
						}
						else $.Dialog.fail(title, this.message);
					})).always(function(){
						$input.removeAttr('disabled').parent().removeClass('loading');
					});
				}
			});
			$input.nextAll('.aa-menu').on('click', '.tag', function(){
				$input.trigger({
					type: 'keydown',
					keyCode: Key.Enter,
				});
			});
		});

		$colorGroups = $('ul.colors');
		$colorGroups.filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: `Re-order color groups`, icon: 'arrow-unsorted', click: function(){
					let $colors = $(this),
						$li = $colors.closest('[id^=p]'),
						appearanceID = $li.attr('id').substring(1),
						ponyName = !AppearancePage
							? $li.children().last().children('strong').text().trim()
							: $content.children('h1').text(),
						title = `Re-order color groups on appearance: ${ponyName}`;

					$.Dialog.wait(title, 'Retrieving color group list from server');

					$.post(`${PGRq}/cg/appearance/getcgs/${appearanceID}${EQGRq}`, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(this.message);

						let $CGReorderForm = $.mk('form','cg-reorder'),
							$cgs = $.mk('ol');

						$.each(this.cgs,function(_, cg){
							$cgs.append($.mk('li').attr('data-id', cg.id).text(cg.label));
						});

						$CGReorderForm.append(
							$.mk('div').attr('class','cgs').append(
								'<p class="align-center">Drag to re-arrange</p>',
								$cgs
							)
						);

						// jshint -W031
						new Sortable($cgs.get(0), {
							ghostClass: "moving",
							scroll: false,
							animation: 150,
						});

						$.Dialog.request(title, $CGReorderForm, 'Save', function($form){
							$form.on('submit', function(e){
								e.preventDefault();
								let data = {cgs:[]},
									$cgs = $form.children('.cgs');

								if (!$cgs.length)
									return $.Dialog.fail(false, 'There are no color groups to re-order');
								$cgs.find('ol').children().each(function(){
									data.cgs.push($(this).attr('data-id'));
								});
								data.cgs = data.cgs.join(',');

								$.Dialog.wait(false, 'Saving changes');
								if (AppearancePage)
									data.APPEARANCE_PAGE = true;

								$.post(`${PGRq}/cg/appearance/setcgs/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
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
					ColorGroupEditor.factory(`Create color group`, $(this).closest('[id^=p]').attr('id').substring(1));
				}},
				{text: "Apply template (if empty)", icon: 'document-add', click: function(){
					let appearanceID = $(this).closest('[id^=p]').attr('id').substring(1);
					$.Dialog.confirm('Apply template on appearance','Add common color groups to this appearance?<br>Note: This will only work if there are no color groups currently present.', function(sure){
						if (!sure) return;

						$.Dialog.wait(false, 'Applying template');

						let data = {};
						if (AppearancePage)
							data.APPEARANCE_PAGE = true;
						$.post(`${PGRq}/cg/appearance/applytemplate/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							let $pony = $('#p'+appearanceID);
							$pony.find('ul.colors').html(this.cgs);
							window.tooltips();
							ctxmenus();

							$.Dialog.close();
						}));
					});
				}},
			],
			'Color groups'
		);
		$colorGroups.children('li').filter(':not(.ctxmenu-bound)').ctxmenu(
			[
				{text: `Edit color group`, icon: 'pencil', click: function(){
					let $this = $(this),
						$group = $this.closest('li'),
						groupID = $group.attr('id').substring(2),
						groupName = $this.children().first().text().replace(/:\s?$/,''),
						title = `Editing color group: `+groupName;

					$.Dialog.wait(title, `Retrieving color group details from server`);

					$.post(`${PGRq}/cg/colorgroup/get/${groupID}${EQGRq}`,$.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(title, this.message);

						ColorGroupEditor.factory(title, $group, this);
					}));
				}},
				{text: `Delete color group`, icon: 'trash', click: function(){
					let $group = $(this).closest('li'),
						groupID = $group.attr('id').substring(2),
						groupName = $group.children().first().text().replace(/:\s?$/,''),
						title = `Delete color group: `+groupName;
					$.Dialog.confirm(title, `By deleting this color group, all colors within will be removed too.<br>Are you sure?`, function(sure){
						if (!sure) return;

						$.Dialog.wait(title, 'Sending removal request');

						$.post(`${PGRq}/cg/colorgroup/del/${groupID}${EQGRq}`,$.mkAjaxHandler(function(){
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
				{text: `Re-order color groups`, icon: 'arrow-unsorted', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 1);
				}},
				{text: "Create new group", icon: 'folder-add', click: function(){
					$.ctxmenu.triggerItem($(this).parent(), 2);
				}},
			],
			function($el){ return 'Color group: '+$el.children().first().text().trim().replace(':','') }
		);
		let $colors = $colorGroups.children('li').find('.valid-color');
		$.ctxmenu.addItems(
			$colors.filter('.ctxmenu-bound'),
			$.ctxmenu.separator,
			{text: `Edit color group`, icon: 'pencil', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 1);
			}},
			{text: `Delete color group`, icon: 'trash', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 2);
			}},
			$.ctxmenu.separator,
			{text: `Re-order color groups`, icon: 'arrow-unsorted', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 3);
			}},
			{text: "Create new group", icon: 'folder-add', click: function(){
				$.ctxmenu.triggerItem($(this).parent().closest('.ctxmenu-bound'), 4);
			}}
		);

		$content.find('.upload-wrap').filter(':not(.ctxmenu-bound)').each(function(){
			let $this = $(this),
				$li = $this.closest('li');
			if (!$li.length)
				$li = $content.children('[id^=p]');
			let appearanceID = $li.attr('id').substring(1);
			(($this, appearanceID) => {
				let imgsrc = $this.find('img').attr('src'), hasSprite,
					updateSprite = function(){
						imgsrc = $this.find('img').attr('src');
						hasSprite = imgsrc.indexOf('blank-pixel.png') === -1;
						$this[hasSprite?'removeClass':'addClass']('nosprite');
						$.ctxmenu.setDefault($this, hasSprite ? 1 : 4);
					};
				$this.uploadZone({
					requestKey: 'sprite',
					title: 'Upload sprite',
					accept: 'image/png',
					target: `${PGRq}/cg/appearance/setsprite/${appearanceID}`,
				}).on('uz-uploadstart',function(){
					$.Dialog.close();
				}).on('uz-uploadfinish',function(){
					updateSprite();
				}).ctxmenu([
					{text: 'Open image in new tab', icon: 'arrow-forward', click: function(){
						if (imgsrc.indexOf('blank-pixel.png') !== -1)
							return $.Dialog.fail('Open image in new tab','This appearance lacks a sprite image');
						window.open($this.find('img').attr('src'),'_blank');
					}},
					{text: 'Copy image URL', icon: 'clipboard', click: function(){
						if (imgsrc.indexOf('blank-pixel.png') !== -1)
							return $.Dialog.fail('Copy image URL','This appearance lacks a sprite image');
						$.copy($.toAbsoluteURL($this.find('img').attr('src')));
					}},
					{text: 'Check sprite colors', icon: 'adjust-contrast', click: function(){
						if (imgsrc.indexOf('blank-pixel.png') !== -1)
							return $.Dialog.fail('Check sprite colors','This appearance lacks a sprite image');
						$.Navigation.visit(`${PGRq}/cg/sprite/${appearanceID}`);
					}},
					{text: 'Upload new sprite', icon: 'upload', click: function(){
						let title = 'Upload sprite image',
							$uploadInput = $this.find('input[type="file"]');
						$.Dialog.request(title,$SpriteUploadFormTemplate.clone(),'Download image', function($form){
							let $image_url = $form.find('input[name=image_url]');
							$form.find('.upload-link').on('click', function(e){
								e.preventDefault();
								e.stopPropagation();

								$uploadInput.trigger('click', [true]);
							});
							if (PersonalGuide)
								$form.find('.sprite-template-gen').on('click',function(e){
									e.preventDefault();
									e.stopPropagation();

									const
										title = 'Sprite Template Generator',
										callme = (colors) => {
											let $clone = window.$TemplateGenFormTemplate.clone(true,true);
											$.Dialog.request(title,$clone,false, function(){
												$clone.triggerHandler('added');
												if (colors)
													$clone.triggerHandler('got-colors', [colors]);
											});
										},
										getColors = () => {
											if (isNaN(appearanceID))
												return callme(false);

											$.Dialog.wait(title,'Getting relevant appearance colors');

											$.post(`/cg/get-sprite-colors/${appearanceID}`,$.mkAjaxHandler(function(){
												if (!this.status) return callme(false);

												callme(this.colors);
											}));
										};

									$.Dialog.close();
									if (typeof window.$TemplateGenFormTemplate === 'undefined'){
										$.Dialog.wait(title,'Loading form, please wait');
										let scriptUrl = '/js/min/global-template_gen_form.js';
										$.getScript(scriptUrl,getColors).fail(function(){
											$.Dialog.fail(title, 'Form could not be loaded');
										});
									}
									else getColors();
								});
							$form.on('submit', function(e){
								e.preventDefault();

								let image_url = $image_url.val();

								$.Dialog.wait(title, 'Downloading external image to the server');

								$.post(`${PGRq}/cg/appearance/setsprite/${appearanceID}${EQGRq}`,{image_url: image_url}, $.mkAjaxHandler(function(){
									if (this.status)
										$uploadInput.trigger('set-image', [this]);
									else $.Dialog.fail(title,this.message);
								}));
							});
						});
					}},
					{text: 'Remove sprite image', icon: 'times', click: function(){
						if (imgsrc.indexOf('blank-pixel.png') !== -1)
							return $.Dialog.fail('Remove sprite image','This appearance lacks a sprite image');
						$.Dialog.confirm('Remove sprite image','Are you sure you want to <strong>permanently delete</strong> the sprite image from the server?',['Wipe it','Nope'], function(sure){
							if (!sure) return;

							$.Dialog.wait(false, 'Removing image');

							$.post(`${PGRq}/cg/appearance/delsprite/${appearanceID}`, $.mkAjaxHandler(function(){
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
			})($this, appearanceID);
		});
	}
	window.ctxmenus = function(){ctxmenus()};

	$list.on('page-switch',bindEditTagsHandlers);
	bindEditTagsHandlers();

	function bindEditTagsHandlers(){
		$('button.edit:not(.bound)').addClass('bound').on('click',function(){
			let $this = $(this),
				$li = $this.closest('[id^=p]'),
				appearanceID = $li.attr('id').substring(1),
				ponyName = !AppearancePage
					? $this.parent().text().trim()
					: $content.children('h1').text(),
				title = 'Editing appearance: '+ponyName;

			$.Dialog.wait(title, 'Retrieving appearance details from server');

			$.post(`${PGRq}/cg/appearance/get/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				let data = this;
				data.appearanceID = appearanceID;
				mkPonyEditor($this, title, data);
			}));
		}).next('.delete').on('click',function(){
			let $this = $(this),
				$li = $this.closest('[id^=p]'),
				appearanceID = $li.attr('id').substring(1),
				ponyName = !AppearancePage
					? $this.parent().text().trim()
					: $content.children('h1').text(),
				title = 'Deleting appearance: '+ponyName;

			$.Dialog.confirm(title,'Deleting this appearance will remove <strong>ALL</strong> of its color groups, the colors within them, and the sprite file, if any.<br>Delete anyway?', function(sure){
				if (!sure) return;

				$.Dialog.wait(title, 'Sending removal request');

				$.post(`${PGRq}/cg/appearance/delete/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
					if (this.status){
						$li.remove();
						$.Dialog.success(title, this.message);

						let path = window.location.pathname;
						if ($list.children().length === 0)
							path = path.replace(/(\d+)$/,function(n){ return n > 1 ? n-1 : n });
						if (AppearancePage){
							$.Dialog.wait('Navigation', 'Loading page 1');
							$.Navigation.visit(`${PGRq}/cg/1`);
						}
						else $.toPage(path,true,true);
					}
					else $.Dialog.fail(title, this.message);
				}));
			});
		});

		ctxmenus();
	}

	$('.cg-export').on('click',function(){
		$.mk('form').attr({
			method:'POST',
			action:'/cg/export',
			target: '_blank',
		}).html(
			$.mk('input').attr('name','CSRF_TOKEN').val($.getCSRFToken())
		).appendTo($body).submit().remove();
	});

	$('.cg-reindex').on('click',function(){
		$.Dialog.confirm('Re-index all appearances','Wipe and rebuild ElasticSearch index?',function(sure){
			if (!sure) return;

			$.Dialog.wait(false);

			$.post('/cg/reindex',$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.success(false, this.message, true);
			}));
		});
	});

	$('.cg-sprite-colors').on('click',function(){
		$.Dialog.confirm($(this).text(),'Run a check on all sprites & look for missing colors?',function(sure){
			if (!sure) return;

			$.Dialog.wait(false,'Checking all sprite colors (this might take a while)');

			$.post('/cg/sprite-color-checkup',$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$.Dialog.success(false, this.message, true);
			}));
		});
	});
});
