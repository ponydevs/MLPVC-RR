$(function(){
	var EP_TITLE_REGEX = window.EP_TITLE_REGEX,
		EP_TITLE_HTML_REGEX = EP_TITLE_REGEX.toString().split('/')[1],
		formContents =
			'<label><input type="number" min=1 max=8 name=season placeholder="Season #" required></label>\
			<label><input type="number" min=1 max=26 name=episode placeholder="Episode #" required></label>\
			<label><input type="text" maxlength=255 name=title placeholder=Title pattern="'+EP_TITLE_HTML_REGEX+'" autocomplete=off required></label>\
			<div class="notice info align-center">\
				<p><strong>Title</strong> must be between 5 and 35 characters.<br>Letters, numbers, and these characters, are allowed:<br>-, apostrophe, !, &, comma.</p>\
			</div>\
			<label><input type="checkbox" name=twoparter> Two-parter</label>\
			<div class="notice info align-center">\
				<p>If <strong>Two-parter</strong> is checked, only specify<br>the episode number of the first part</p>\
			</div>',
		$content = $('#content'),
		$pageTitle = $content.children('h1');

	var $eptable = $('#episodes'),
		$eptableBody = $eptable.children('tbody'),
		$addep = $(document.createElement('form')).attr('id','addep').html(formContents),
		$editep = $(document.createElement('form')).attr('id','editep').html(formContents);

	$('#add-episode').on('click',function(e){
		e.preventDefault();
		var title = 'Add Episode';

		$.Dialog.request(title,$addep.clone(),'addep','Add',function(){
			$('#addep').on('submit',function(e){
				e.preventDefault();

				var tempdata = $(this).serializeArray(), data = {};
				$.each(tempdata,function(i,el){
					data[el.name] = el.value;
				});

				$.Dialog.wait(title,'Adding episode to database');

				$.ajax({
					method: "POST",
					url: "/episode/add",
					data: data,
					success: function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							$eptableBody.html(data.tbody);
							$pageTitle.html($pageTitle.data('list')).next().hide();
							Bind();
							$.Dialog.close();
						}
						else $.Dialog.fail(title,data.message);
					}
				});
			})
		});
	});

	function Bind(){
		$eptable.find('td[data-epid]').each(function(){
			var $this = $(this),
				epid = $this.attr('data-epid');

			$this.removeAttr('data-epid').data('epid', epid);
		});
		$eptable.find('.edit-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid'),
				title = 'Editing '+epid;

			$.ajax({
				method: "POST",
				url: "/episode/"+epid,
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						var $editepWithData = $editep.clone();

						$editepWithData.find('input[name=twoparter]').prop('checked',!!data.ep.twoparter);
						delete data.ep.twoparter;

						var epid = data.epid;
						delete data.epid;

						$.each(data.ep,function(k,v){
							$editepWithData.find('input[name='+k+']').val(v);
						});

						$.Dialog.request('Editing',$editepWithData,'editep','Save',function(){
							$('#editep').on('submit',function(e){
								e.preventDefault();

								var tempdata = $(this).serializeArray(), data = {};
								$.each(tempdata,function(i,el){
									data[el.name] = el.value;
								});

								$.Dialog.wait(title,'Saving edits');

								$.ajax({
									method: "POST",
									url: '/episode/edit/'+epid,
									data: data,
									success: function(data){
										if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

										if (data.status){
											$eptableBody.html(data.tbody);
											Bind();
											$.Dialog.close();
										}
										else $.Dialog.fail(title,data.message);
									}
								})
							})
						});
					}
					else $.Dialog.fail(title,data.message);
				}
			});
		});

		$eptable.find('.delete-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid'),
				title = 'Deleting '+epid;

			$.Dialog.confirm(title,'<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>',function(sure){
				if (!sure) return;

				$.Dialog.wait(title);

				$.ajax({
					method: "POST",
					url: '/episode/delete/'+epid,
					success: function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							$eptableBody.html(data.tbody);
							if ($eptableBody.children('.empty').length)
								$pageTitle.html($pageTitle.data('none')).next().show();
							Bind();
							$.Dialog.close();
						}
						else $.Dialog.fail(title,data.message);
					}
				});
			});
		});
	}
	Bind();
});