/* globals DocReady */
DocReady.push(function ColorguideTags(){
	'use strict';
	var TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC,
		$tbody = $('#tags').children('tbody'),
		updateList = function($tr, action){
			if (!this.status) return $.Dialog.fail(false, this.message);

			if (typeof $tr === 'function')
				return $tr.call(this, action);

			$tr.remove();
			$.Dialog.success(false, this.message);

			var path = window.location.pathname;
			if ($tbody.children().length === 0)
				path = path.replace(/(\d+)$/,function(n){ return n > 1 ? n-1 : n });
			$.toPage(path,true,true);
		};
	window.CGTagEditing = function(tagName, tagID, action, $tr){
		switch (action){
			case "delete":
				$.Dialog.confirm('Detele tag: '+tagName,"Deleting this tag will also remove it from every appearance where it's been used.<br>Are you sure?",['Delete it','Nope'],function(sure){
					if (!sure) return;

					$.Dialog.wait(false,'Sending removal request');

					$.post('/cg/deltag/'+tagID,$.mkAjaxHandler(function(){
						updateList.call(this, $tr, action);
					}));
				});
			break;
			case "synon":
			case "merge":
				var merging = action === 'merge',
					Action = (merging?'Merge':'Synonymize');

				$.Dialog.wait(Action+' '+tagName+' into another tag', 'Retrieving tag list from server');

				$.post('/cg/gettags',{not:tagID,action:action},$.mkAjaxHandler(function(){
					if (!this.length){
						if (this.undo)
							return window.CGTagEditing.call(this, tagName, tagID, 'unsynon', $tr);

						return $.Dialog.fail(false, this.message+'asdasasdasd');
					}

					var $form = $.mk('form').attr('id','tag-'+action),
						$select = $.mk('select').attr('required',true).attr('name','targetid'),
						optgroups = {}, ogorder = [];

					$.each(this, function(_, tag){
						var type = tag.type,
							$option = $.mk('option').attr('value', tag.tid).text(tag.name);

						if (!type) return $select.append($option);

						if (typeof optgroups[type] === 'undefined'){
							optgroups[type] = $.mk('optgroup').attr('label', TAG_TYPES_ASSOC[type]);
							ogorder.push(type);
						}
						optgroups[type].append($option);
					});

					$.each(ogorder, function(_, key){ $select.append(optgroups[key]) });

					$form.append(
						$.mk('p').text(
							merging
							? 'Merging a tag into another will permanently delete it, while replacing it with the merge target on every appearance which used it.'
							: 'Synonymizing a tag will keep both tags in the database, but when searching, the source tag will automatically redirect to the target tag.'
						),
						$.mk('label').append(
							$.mk('span').html(Action+' <strong>'+tagName+'</strong> '+(merging?'into':'with')+' the following:'),
							$select
						)
					);

					$.Dialog.request(false, $form, 'tag-'+action, Action, function($form){
						$form.on('submit',function(e){
							e.preventDefault();

							var sent = $form.mkData();
							$.Dialog.wait(false, (merging?'Merging':'Synonymizing')+' tags');

							$.post('/cg/'+action+'tag/'+tagID,sent, $.mkAjaxHandler(function(){
								updateList.call(this, $tr, action);
							}));
						});
					});
				}));
			break;
			case "unsynon":
				var message = this.message;
				$.Dialog.close(function(){
					$.Dialog.confirm('Remove synonym from '+tagName, message, ['Yes, continue…','Cancel'], function(sure){
						if (!sure) return;

						var $targetTagName = $.mk('p').html(message).children('strong'),
							$Form = $.mk('form').attr('id', 'synon-remove').append(
							$.mk('p').append(
								'If you leave the option below checked, ',
								$.mk('strong').text(tagName),
								' will be added to all appearances where ',
								$targetTagName.clone(),
								' is used, preserving how the tags worked while the synonym was active.'
							),
							$.mk('p').append(
								"If you made these tags synonyms by accident and don't want ",
								$.mk('strong').text(tagName),
								" to be added to each appearance where ",
								$targetTagName.clone(),
								" is used, you should uncheck the box below."),
							$.mk('label').append(
								$.mk('input').attr({
									type: 'checkbox',
									name: 'keep_tagged',
									checked: true,
								}),
								"<span>Preserve current tag connections</span>"
							)
						);

						$.Dialog.request(false, $Form, 'synon-remove', 'Remove synonym', function($form){
							$form.on('submit', function(e){
								e.preventDefault();

								var data = $form.mkData();
								$.Dialog.wait(false, 'Removing synonym');

								$.post('/cg/unsynontag/'+tagID,data,$.mkAjaxHandler(function(){
									updateList.call(this, $tr, action);
								}));
							});
						});
					});
				});
			break;
			case "refresh":
				$.Dialog.wait('Refresh use count of '+tagName, 'Updating use count');

				$.post('/cg/recounttag',{tagids:tagID}, TagUseUpdateHandler());
			break;
		}
	};
	$tbody.on('click','button',function(e){
		e.preventDefault();

		var $btn = $(this),
			$tr = $btn.parents('tr'),
			tagName = $tr.children().eq(1).text().trim(),
			tagID = parseInt($tr.children().first().text().trim(), 10),
			action = this.className.split(' ').pop();

		window.CGTagEditing(tagName, tagID, action, $tr);
	});

	var TagUseUpdateHandler = function(successDialog){
			return $.mkAjaxHandler(function(){
				if (!this.status) $.Dialog.fail(false, this.message);

				if (this.counts){
					var counts = this.counts;
					$tbody.children().each(function(){
						var $ch = $(this).children(),
							tid = parseInt($ch.first().text().trim(), 10);

						if (typeof counts[tid] !== 'undefined')
							$ch.last().children('span').text(counts[tid]);
					});
				}

				if (successDialog) $.Dialog.success(false, this.message, true);
				else $.Dialog.close();
			});
		};
	$('.refresh-all').on('click',function(){
		var tagIDs = [],
			title = 'Recalculate tag usage data';
		$tbody.children().each(function(){
			tagIDs.push($(this).children().first().text().trim());
		});

		$.Dialog.wait(title, 'Updating use count'+(tagIDs.length!==1?'s':''));

		$.post('/cg/recounttag',{tagids:tagIDs.join(',')}, TagUseUpdateHandler(true));
	});
});
