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
		mkCMDataLi = function(i, el = {}){
			if (typeof el.facing === 'undefined')
				el.facing = 'right';
			let $facingSelector = $.mk('div').attr('class','disabled-show').html(
				`<p>Body orientation${i===0?'':`: <strong class="orient">${$.capitalize(el.facing)}</strong><input type="hidden" name="facing[]" value="${el.facing}">`}</p>`+
				(i===0?`<div class="radio-group">
					<label><input type="radio" name="facing[]" value="left" required><span>Left</span></label>
					<label><input type="radio" name="facing[]" value="right" required><span>Right</span></label>
					<label><input type="radio" name="facing[]" value="" required><span>Symmetrical</span></label>
				</div>`:'')
			);
			$facingSelector.find(`input[value='${el.facing?el.facing:''}']`).prop('checked', true);
			const rotation = typeof el.favme_rotation !== 'undefined' ? el.favme_rotation : (el.facing==='left'?-18:18);
			return $.mk('li').append(
					$.mk('fieldset').append(
						$.mk('legend').append(
							`<span>Cutie Mark #${i+1}</span>`,
							(i>0?`<button class="section-toggle btn blue typcn"></button>`:'')
						),
						el.id ? $.mk('input').attr({
							type: 'hidden',
							value: el.id,
							name: 'id[]',
						}) : undefined,
						$facingSelector,
						$.mk('label').append(
							"<span>Deviation link</span>",
							$.mk('input').attr({
								type: 'url',
								name: 'favme[]',
								required: true,
							}).val(el.favme?`http://fav.me/${el.favme}`:undefined)
						),
						$.mk('div').attr('class','label disabled-show').append(
							`<span>Preview rotation (<span class='rotation-display'>${rotation}</span>°)</span>`,
							$.mk('input').attr({
								type: 'range',
								name: 'favme_rotation[]',
								min: -180,
								max: 180,
								step: 2,
								'class': 'rotation-range',
								required: true,
							}).val(rotation)
						),
						$.mk('label').append(
							"<span>Custom preview (optional)</span>",
							$.mk('input').attr({
								type: 'url',
								name: 'preview_src[]',
							}).val(el.preview_src)
						)
					)
				);
		},
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

									const
										ponyLabel = data.label,
										$form = $.mk('form','selective-wipe').html(
											`<p>Select which of the following items to clear below.</p>
											<label><input type="checkbox" name="wipe_cache"> Clear cached images</label>
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
																.appendTo($content);
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
								.attr('class', 'darkblue typcn typcn-pencil')
								.text('Cutie Mark')
								.on('click',function(e){
									e.preventDefault();

									let ponyLabel = data.label;
									$.Dialog.close();
									$.Dialog.wait('Manage Cutie Mark of '+ponyLabel, 'Retrieving CM data from server');
									let $cmSection = $content.find('section.approved-cutie-mark');
									$.post(`${PGRq}/cg/appearance/getcms/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
										if (!this.status) return $.Dialog.fail(false, this.message);

										let data = this,
											$CMPreviewImages,
											$CMPreview = $.mk('ul').attr('class','dialog-preview'),
											$CMList = $.mk('ul').attr('class','cm-list'),
											$SectionToggle,
											updateRQ = false,
											updateText = 'Update preview',
											$CMDataEditorForm,
											updateRange = (range) => {
												let event = $.Event('change');
												event.target = range;
												$CMDataEditorForm.trigger(event);
											},
											toggleSecondCMSection = (disable, $secondCM) => {
												if (typeof $secondCM === 'undefined')
													$secondCM = $CMList.children().eq(1);
												$SectionToggle[disable?'hide':'show']();
												let $fieldset = $secondCM.children('fieldset'),
													ignored = $fieldset.hasClass('ignore'),
													favmeValid = Boolean($fieldset.find('input[name="favme[]"]').val() || $fieldset.find('input[name="preview_src[]"]').val());
												if ((disable && !ignored) || (!disable && !ignored && !favmeValid))
													$SectionToggle.triggerHandler('click');
											},
											updateRanges = () => {
												$CMList.find('.rotation-range').each(function(){
													updateRange(this);
												});
											},
											previewUpdated = () => {
												$CMPreviewImages = $CMPreview.find('.img');
												updateRanges();
											},
											$UpdatePreviewButton = $.mk('button').attr('class','darkblue typcn typcn-arrow-sync').text(updateText).on('click',function(e){
												e.preventDefault();

												if (updateRQ !== false){
													updateRQ.abort();
													updateRQ = false;
												}

												let $this = $(this),
													data = $this.closest('form').mkData();
												$this.disable().html('Updating preview&hellip;');
												$CMPreview.addClass('loading');
												updateRQ = $.post(`${PGRq}/cg/appearance/getcmpreview/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
													$this.text(updateText).enable();
													$CMPreview.removeClass('loading');
													updateRQ = false;

													if (!this.status) return $.Dialog.fail(false, this.message);

													$.Dialog.clearNotice(/preview/);
													$CMPreview.html(this.html);
													previewUpdated();
												}));
											}),
											$DeleteButton = $.mk('button').attr('class','red typcn typcn-trash').text('Delete Cutie Marks').on('click',function(e){
												e.preventDefault();

												if (updateRQ !== false){
													updateRQ.abort();
													updateRQ = false;
												}

												$.Dialog.close(function(){
													$.Dialog.confirm('Delete Cutie Marks of '+ponyLabel,'Are you sure you want to remove the cutie mark(s) associated with this appearance?', sure => {
														if (!sure) return;

														$.Dialog.wait(false,'Sending removal request');

														$.post(`${PGRq}/cg/appearance/delcms/${appearanceID}${EQGRq}`,$.mkAjaxHandler(function(){
															if (!this.status) return $.Dialog.fail(false, this.message);

															if ($cmSection.length)
																$cmSection.addClass('hidden').children(':not(h2,p)').remove();
															$.Dialog.close();
														}));
													});
												});
											});

										$CMDataEditorForm = $.mk('form').attr('id','cm-data-editor').append(
											$CMPreview,
											$CMList,
											$UpdatePreviewButton
										).on('change mousemove keydown','.rotation-range',function(e){
											let $this = $(e.target),
												val = $this.val();
											$this.prev().children('.rotation-display').text(val);
											if (typeof $CMPreviewImages !== 'undefined'){
												let $li = $this.closest('li'),
													index = $li.index();
												$CMPreviewImages.eq(index).css('transform',`rotateZ(${val}deg)`);
												if (index === 0 && $li.find('input[name="facing[]"]:checked').val() === ''){
													if ($CMPreviewImages.eq(1).length){
														let $range = $li.next().find('.rotation-range');
														$range.val(-val);
														updateRange($range.get(0));
														$CMPreviewImages.eq(1).css('transform',`rotate(${-val}deg)`);
													}
												}
											}
										}).on('change click keydown','input[name="facing[]"]',function(e){
											let $this = $(this),
												$group = $this.parents('.radio-group'),
												facing = $group.find('input:checked').val(),
												$secondCM = $CMList.children().eq(1);

											let orient = ({
												'': 'right',
												'left': 'right',
												'right': 'left',
											})[facing];
											$secondCM.find('.orient').text($.capitalize(orient)).next().val(orient);
											if (e.type === 'change'){
												if (facing){
													let $rangeSelectors = $this.parents('form').find('.rotation-range');
													$rangeSelectors.each(function(i){
														let $rangeSelector = $(this),
															// Invert condition on second element
															facingThatWay = facing === (i===0 ? 'right' : 'left'),
															val = $rangeSelector.val();
														if ((val < 0 && facingThatWay) || (val > 0 && !facingThatWay)){
															$rangeSelector.val(val*-1);
															updateRange(this);
														}
													});
												}
												$UpdatePreviewButton.triggerHandler('click');

												toggleSecondCMSection(!facing, $secondCM);
											}
										});

										if (data.cms.length){
											$.each(data.cms,(i,el)=>{
												$CMList.append( mkCMDataLi(i, el) );
											});
											if (data.cms.length === 1)
												$CMList.append( mkCMDataLi(1) );
											$CMPreview.html(data.preview);
											previewUpdated();
											updateRanges();
											$CMDataEditorForm.append($DeleteButton);
										}
										else {
											$CMList.append(mkCMDataLi(0),mkCMDataLi(1));
										}

										$SectionToggle = $CMDataEditorForm.find('.section-toggle').on('click',function(e){
											e.preventDefault();

											let $this = $(this),
												$fieldset = $this.closest('fieldset');
											if ($fieldset.hasClass('ignore')){
												$fieldset.removeClass('ignore');
												$fieldset.find('.force-disabled').removeClass('force-disabled').enable().parent().removeClass('hidden');
												$this.removeClass('typcn-plus').addClass('typcn-minus').html('Remove');
											}
											else {
												$fieldset.addClass('ignore');
												$fieldset.find('input:not(:disabled)').disable().addClass('force-disabled').parent().addClass('hidden');
												$this.removeClass('typcn-minus').addClass('typcn-plus').html('Add');
											}
										});

										if (data.cms.length === 1)
											$SectionToggle.addClass('typcn-minus').text('Remove');
										else {
											$SectionToggle.closest('fieldset').addClass('ignore');
											$SectionToggle.addClass('typcn-plus').text('Add');
										}

										if (!data.cms.facing){
											$SectionToggle.hide();
											toggleSecondCMSection(true);
										}
										else {
											$SectionToggle.triggerHandler('click');
										}

										$.Dialog.request(false,$CMDataEditorForm,'Save',function($form){
											$form.on('submit',function(e){
												e.preventDefault();

												if (updateRQ !== false){
													updateRQ.abort();
													updateRQ = false;
												}

												let data = $form.mkData();
												if (AppearancePage)
													data.APPEARANCE_PAGE = true;
												$.Dialog.wait(false,'Saving cutie mark data');
												$.post(`${PGRq}/cg/appearance/setcms/${appearanceID}${EQGRq}`,data,$.mkAjaxHandler(function(){
													if (!this.status) return $.Dialog.fail(false, this.message);

													$.Dialog.close();
													if ($cmSection.length){
														$cmSection.children(':not(h2,p)').remove();
														$cmSection.removeClass('hidden').append(this.html);
													}
												}));
											});
										});

										window.updateRanges = updateRanges;
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

	let $CGEditorFormTemplate = $.mk('form','cg-editor'),
		$colorInput =
			$.mk('input').attr({
				'class': 'clri',
				autocomplete: 'off',
				spellcheck: 'false',
			}).patternAttr(HEX_COLOR_PATTERN).on('keyup change input',function(_, override){
				let $this = $(this),
					$cp = $this.prev(),
					color = (typeof override === 'string' ? override : this.value).trim(),
					valid = HEX_COLOR_PATTERN.test(color);
				if (valid)
					$cp.removeClass('invalid').css('background-color', color.replace(HEX_COLOR_PATTERN, '#$1'));
				else $cp.addClass('invalid');

				$this.next().attr('required', valid);
			}).on('paste blur keyup', function(e){
				let input = this,
					f = function(){
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
							$input.trigger('change',[val]).patternAttr(
								SHORT_HEX_COLOR_PATTERN.test(input.value)
								? SHORT_HEX_COLOR_PATTERN
								: HEX_COLOR_PATTERN
							);
						}
					};
				if (e.type === 'paste') setTimeout(f, 10);
				else f();
			}),
		$colorLabel = $.mk('input').attr({ 'class': 'clrl', pattern: PRINTABLE_ASCII_PATTERN.replace('+', '{3,30}') }),
		$colorActions = $.mk('div').attr('class','clra')
			.append($.mk('span').attr('class','typcn typcn-minus remove red').on('click',function(){
				$(this).closest('.clr').remove();
			}))
			.append($.mk('span').attr('class','typcn typcn-arrow-move move blue')),
		mkClrDiv = function(color){
			let $ci = $colorInput.clone(true, true),
				$cl = $colorLabel.clone(),
				$ca = $colorActions.clone(true, true),
				$el = $.mk('div').attr('class','clr');

			if (typeof color === 'object'){
				if (color.hex) $ci.val(color.hex.toUpperCase());
				if (color.label) $cl.val(color.label);
			}

			$el.append("<span class='clrp'></span>",$ci,$cl,$ca);
			$ci.trigger('change');
			return $el;
		},
		$addBtn = $.mk('button').attr('class','typcn typcn-plus green add-color').text('Add new color').on('click', function(e){
			e.preventDefault();

			let $form = $(this).parents('#cg-editor'),
				$colors = $form.children('.clrs');
			if (!$colors.length)
				$form.append($colors = $.mk('div').attr('class', 'clrs'));

			if ($colors.hasClass('ace_editor')){
				let editor = $colors.data('editor');
				editor.clearSelection();
				editor.navigateLineEnd();
				let curpos = editor.getCursorPosition(),
					trow = curpos.row+1,
					emptyLine = curpos.column === 0,
					copyHashEnabled = window.copyHashEnabled();

				if (!emptyLine)
					trow++;

				editor.insert((!emptyLine?'\n':'')+(copyHashEnabled?'#':'')+'\tColor Name');
				editor.gotoLine(trow,Number(copyHashEnabled));
				editor.focus();
			}
			else {
				let $div = mkClrDiv();
				$colors.append($div);
				$div.find('.clri').focus();
			}
		}),
		parseColorsText = function(text, strict){
			let colors = [],
				lines = text.split('\n');

			for (let lineIndex = 0, lineCount = lines.length; lineIndex < lineCount; lineIndex++){
				let line = lines[lineIndex];

				// Comment or empty line
				if (/^(\/\/.*)?$/.test(line))
					continue;

				let matches = line.trim().match(/^#?([a-f\d]{6}|[a-f\d]{3})?(?:\s*([a-z\d][ -~]{2,29}))?$/i);
				// Valid line
				if (matches && matches[2] && (strict ? matches[1] : true)){
					colors.push({ hex: matches[1] ?  $.hexpand(matches[1]) : undefined, label: matches[2] });
					continue;
				}
				if (line.trim() === '#'){
					colors.push({ hex: undefined, label: '' });
					continue;
				}

				// Invalid line
				throw new ColorTextParseError(line, lineIndex+1, matches);
			}

			return colors;
		},
		$editorToggle = $.mk('button').attr('class','typcn typcn-document-text darkblue').text('Plain text editor').on('click', function(e){
			e.preventDefault();

			let $btn = $(this),
				$form = $btn.parents('#cg-editor');

			$btn.disable();
			try {
				$form.trigger('save-color-inputs');
			}
			catch (error){
				if (!(error instanceof ColorTextParseError))
					throw error;
				let editor = $form.find('.clrs').data('editor');
				editor.gotoLine(error.lineNumber);
				editor.navigateLineEnd();
				$.Dialog.fail(false, error.message);
				editor.focus();
				$btn.enable();
				return;
			}
			$btn.toggleClass('typcn-document-text typcn-edit').toggleHtml(['Plain text editor','Interactive editor']).enable();
			$.Dialog.clearNotice(/Parse error on line \d+ \(shown below\)/);
		});
	$CGEditorFormTemplate.append(
		`<label>
			<span>Group name (2-30 chars.)</span>
			<input type="text" name="label" pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{2,30}')}" required>
		</label>`,
		$.mk('label').append(
			$.mk('input').attr({
				type: 'checkbox',
				name: 'major',
			}).on('click change',function(){
				$(this).parent().next()[this.checked?'show':'hide']().children('input').attr('disabled', !this.checked);
			}),
			'<span>This is a major change</span>'
		),
		`<label style="display:none">
			<span>Change reason (1-255 chars.)</span>
			<input type='text' name='reason' pattern="${PRINTABLE_ASCII_PATTERN.replace('+','{1,255}')}" required disabled>
		</label>
		<p class="align-center">The # symbol is optional, rows with invalid colors will be ignored. Each color must have a short (3-30 chars.) description of its intended use.</p>`,
		$.mk('div').attr('class', 'btn-group').append(
			$addBtn, $editorToggle
		),
		$.mk('div').attr('class', 'clrs').append(
			mkClrDiv()
		)
	).on('render-color-inputs',function(){
		let $form = $(this),
			data = $form.data('color_values'),
			$colors = $form.children('.clrs').empty();

		$.each(data, (_, color) => {
			$colors.append(mkClrDiv(color));
		});

		$colors.data('sortable',new Sortable($colors.get(0), {
			handle: ".move",
			ghostClass: "moving",
			scroll: false,
			animation: 150,
		}));
	}).on('save-color-inputs', function(_, storeState, strict){
		let $form = $(this),
			$colors = $form.children('.clrs'),
			is_ace = $colors.hasClass('ace_editor'),
			editor;
		if (is_ace){
			// Saving
			editor =  $colors.data('editor');
			$form.data('color_values',parseColorsText(editor.getValue(), storeState || strict));
			if (storeState)
				return;

			// Switching
			editor.destroy();
			$colors.empty().removeClass('ace_editor ace-colorguide').removeData('editor').unbind();
			$form.trigger('render-color-inputs');
		}
		else {
			// Saving
			let data = [];
			$form.find('.clr').each(function(){
				let $row = $(this),
					$ci = $row.children('.clri'),
					val = $ci.val(),
					valid = HEX_COLOR_PATTERN.test(val);

				if (!valid && (val.length || strict))
					return;

				data.push({
					hex: valid ? $.hexpand(val).toUpperCase().replace(HEX_COLOR_PATTERN,'#$1') : undefined,
					label: $row.children('.clrl').val(),
				});
			});
			$form.data('color_values',data);
			if (storeState)
				return;

			// Switching
			let editable_content = [
				'// One color per line',
				'// e.g. #012ABC Fill',
			];
			$.each(data, (_, color) => {
				let line = [];

				if (typeof color === 'object'){
					line.push(color.hex ? color.hex : '#');
					if (color.label)
						line.push(color.label);
				}

				editable_content.push(line.join('\t'));
			});

			// Remove Sortable
			let sortable_instance = $colors.data('sortable');
			if (typeof sortable_instance !== 'undefined')
				sortable_instance.destroy();
			$colors.unbind().hide().text(editable_content.join('\n')+'\n');

			// Create editor
			$.getAceEditor(false, 'colorguide', (mode) => {
				$colors.show();
				editor = ace.edit($colors[0]);
				let session = $.aceInit(editor, mode);
				session.setTabSize(8);
				session.setMode(mode);
				editor.navigateFileEnd();
				editor.focus();
				$colors.data('editor', editor);
			});
		}
	});

	function cgEditorMaker(title, $group, dis){
		let $changes = $('#changes'),
			appearanceID,
			groupID;
		if (typeof $group !== 'undefined'){
			if ($group instanceof jQuery){
				groupID = $group.attr('id').substring(2);
				appearanceID = $group.parents('[id^=p]').attr('id').substring(1);
			}
			else appearanceID = $group;
		}
		$.Dialog.request(title,$CGEditorFormTemplate.clone(true, true),'Save', function($form){
			let $label = $form.find('input[name=label]'),
				$major = $form.find('input[name=major]'),
				$reason = $form.find('input[name=reason]'),
				editing = typeof dis === 'object' && dis.label && dis.Colors;

			if (editing){
				$label.val(dis.label);
				$form.data('color_values', dis.Colors).trigger('render-color-inputs');
			}
			$form.on('submit', function(e){
				e.preventDefault();

				try {
					$form.trigger('save-color-inputs', [true, true]);
				}
				catch (error){
					if (!(error instanceof ColorTextParseError))
						throw error;
					let editor = $form.find('.clrs').data('editor');
					editor.gotoLine(error.lineNumber);
					editor.navigateLineEnd();
					$.Dialog.fail(false, error.message);
					editor.focus();
					return;
				}

				let data = { label: $label.val(), Colors: $form.data('color_values') };
				if (!editing) data.ponyid = appearanceID;
				if (data.Colors.length === 0)
					return $.Dialog.fail(false, 'You need to add at least one valid color');
				data.Colors = JSON.stringify(data.Colors);

				if ($major.is(':checked')){
					data.major = true;
					data.reason = $reason.val();
				}

				if (AppearancePage)
					data.APPEARANCE_PAGE = true;
				if (!$changes.length)
					data.FULL_CHANGES_SECTION = true;

				$.Dialog.wait(false, 'Saving changes');

				$.post(`${PGRq}/cg/colorgroup/${editing?'set':'make'}${editing?`/${groupID}`:''}${EQGRq}`, data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					if (this.cg || this.cgs){
						let $pony = $('#p'+appearanceID);
						if (this.cg){
							$group.children('[data-hasqtip]').qtip('destroy', true);
							$group.html(this.cg);
						}
						else if (this.cgs){
							$pony.find('ul.colors').html(this.cgs);
						}
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
						let $ponycm = $('#pony-cm');
						if (AppearancePage && $ponycm.length && this.cm_img){
							$.Dialog.success(false, 'Color group updated');
							$.Dialog.wait(false, 'Updating cutie mark orientation image');
							let preload = new Image();
							preload.src = this.cm_img;
							$(preload).on('load error',function(){
								$ponycm.backgroundImageUrl(preload.src);
								$.Dialog.close();
							});
						}
						else $.Dialog.close();
					}
					else $.Dialog.close();
				}));
			});
		});
	}

	let $tags;
	function ctxmenus(){
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
							$cgs.append($.mk('li').attr('data-id', cg.groupid).text(cg.label));
						});

						$CGReorderForm.append(
							$.mk('div').attr('class','cgs').append('<p class="align-center">Drag to re-arrange</p>',$cgs)
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
					cgEditorMaker(`Create color group`, $(this).closest('[id^=p]').attr('id').substring(1));
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

						cgEditorMaker(title, $group, this);
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

		$('.upload-wrap').filter(':not(.ctxmenu-bound)').each(function(){
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
										$uploadInput.trigger('set-image', [this.path]);
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
});
