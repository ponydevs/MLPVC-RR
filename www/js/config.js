$(function(){
	var $qmanager = $('#quote-manager')
	$.post('/quotes/json?editor',{},function(data){
		var $ponyUl = $(document.createElement('ul')).addClass('ponyul');

		$.each(data.quotes__ponies, function(_,el){
			var $id = $(document.createElement('span')).addClass('id').text(el.id),
				$name = $(document.createElement('span'))
					.addClass('name')
					.text(el.name)
					.on('keydown',function(e){
						if ($(this).attr('contenteditable') !== 'true') return;
						var title = 'Rename '+el.name;
						// Enter
						if (e.keyCode === 13){
							e.preventDefault();

							$.Dialog.info(title, 'Save process goes here');
						}
					})
					.on('click blur',function(e){
						var $this = $(this);
						if (e.type !== 'blur')
							$this.attr({ contenteditable:true, spellcheck: false }).focus();
						else $this.removeAttr('contenteditable spellcheck');
					}),
				$delbtn = $(document.createElement('button'))
					.attr('title','Delete pony & quotes')
					.addClass('typcn typcn-times red')
					.on('click',function(e){
						e.preventDefault();
						var title = 'Delete '+el.name;

						$.Dialog.info(title, 'Delete process goes here');
					}),
				$editbtn = $(document.createElement('button'))
					.attr('title','Edit quotes')
					.addClass('typcn typcn-spanner blue')
					.on('click',function(e){
						e.preventDefault();
						var title = 'Edit '+el.name;

						$.Dialog.info(title, 'Edit process goes here');
					});
			$ponyUl.append($(document.createElement('li')).append($id,  $delbtn, $editbtn, $name));
		});
		var $addNew = $(document.createElement('li'))
			.addClass('addnew')
			.append($(document.createElement('span'))
			.html('<span class="typcn typcn-plus"></span> Add new'))
			.on('click',function(e){
				e.preventDefault();
				var title = 'Add new pony';

				$.Dialog.info(title, 'Add new process goes here');
			});
		$ponyUl
			.append($addNew)
			.appendTo($qmanager.addClass('loaded'));
	});
});