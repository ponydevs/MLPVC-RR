$(function(){
	var TAG_TYPES_ASSOC = window.TAG_TYPES_ASSOC,
		$tbody = $('#tags').children('tbody'),
		updateList = function($tr, title){
			$tr.remove();
			$.Dialog.success(title, this.message);

			var path = window.location.pathname;
			if ($tbody.children().length === 0)
				path = path.replace(/(\d+)$/,function(n){ return n > 1 ? n-1 : n });
			$.toPage(path,true,true);
		};
	$tbody.on('click','button',function(e){
		e.preventDefault();

		var $btn = $(this),
			$tr = $btn.parents('tr'),
			tagName = $tr.children().eq(1).text().trim(),
			tagID = parseInt($tr.children().first().text().trim(), 10),
			title;
		switch (this.className.split(' ').pop()){
			case "delete":
				title = 'Detele tag: '+tagName;

				$.Dialog.confirm(title,"Deleting this tag will also remove it from every appearance where it's been used.<br>Are you sure?",['Delete it','Nope'],function(sure){
					if (!sure) return;

					$.Dialog.wait(title,'Sending removal request');

					$.post('/colorguide/deltag/'+tagID,$.mkAjaxHandler(function(){
						if (!this.status) $.Dialog.fail(title, this.message);
						updateList.call(this, $tr, title);
					}));
				});
			break;
			case "merge":
				title = 'Merge '+tagName+' into another tag';

				$.Dialog.wait(title, 'Retrieving tag list from server');

				$.post('/colorguide/gettags',{not:tagID},$.mkAjaxHandler(function(){
					if (!this.length) return $.Dialog.fail(title, this.message);

					var $form = $.mk('form').attr('id','tag-merge'),
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
						$.mk('p').text('Merging a tag into another will permanently delete it, while replacing it with the merge target on every appearance which used it.'),
						$.mk('label').append(
							$.mk('span').html('Merge <strong>'+tagName+'</strong> into the following:'),
							$select
						),
						$.mk('div').attr('class','notice').hide().html('<p></p>')
					);

					$.Dialog.request(title, $form, 'tag-merge', 'Merge', function($form){
						var $ErrorNotice = $form.children('.notice').children('p'),
							handleError = function(){
								$ErrorNotice.html(this.message).parent().removeClass('info').addClass('fail').show();
								$form.find('select').attr('disabled', false);
								$.Dialog.center();
							};
						$form.on('submit',function(e){
							e.preventDefault();

							$ErrorNotice.text('Merging tags...').parent().removeClass('fail').addClass('info').show();
							$.Dialog.center();

							$.post('/colorguide/mergetag/'+tagID,$form.mkData(),function(){
								if (!this.status) handleError.call(this);
								updateList.call(this, $tr, title);
							});
						});
					});
				}));
			break;
			case "refresh":
				title = 'Refresh use count of '+tagName;

				$.Dialog.wait(title, 'Updating use count');

				$.post('/colorguide/recounttag',{tagids:tagID}, TagUseUpdateHandler(title));
			break;
		}
	});

	var TagUseUpdateHandler = function(title, successDialog){
			return $.mkAjaxHandler(function(){
				if (!this.status) $.Dialog.fail(title, this.message);

				if (this.counts){
					var counts = this.counts;
					$tbody.children().each(function(){
						var $ch = $(this).children(),
							tid = parseInt($ch.first().text().trim(), 10);

						if (typeof counts[tid] !== 'undefined')
							$ch.last().children('span').text(counts[tid]);
					});
				}

				if (successDialog) $.Dialog.success(title, this.message, true);
				else $.Dialog.close();
			})
		},
		$refresher = $('.refresh-all').on('click',function(){
			var tagIDs = [],
				title = 'Recalculate tag usage data';
			$tbody.children().each(function(){
				tagIDs.push($(this).children().first().text().trim())
			});

			$.Dialog.wait(title, 'Updating use count'+(tagIDs.length!==1?'s':''));

			$.post('/colorguide/recounttag',{tagids:tagIDs.join(',')}, TagUseUpdateHandler(title, true));
		});
});
