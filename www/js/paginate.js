$(function(){
	var $title = $(document.head).children('title'),
		basePath = location.pathname.replace(/(\d+)$/,''),
		$pagination = $('.pagination'),
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10),
		title = 'Navigation';
	$pagination.on('click','a',function(e){
		e.preventDefault();

		$.toPage(this.pathname);
	});
	$(window).on('popstate',function(){
		$.toPage(location.pathname, true);
	});
	$.toPage = function(target, silentfail, bypass){
		if (!target) target = window.location.pathname;
		var newPageNumber = parseInt(target.substring(basePath.length), 10);

		if (!bypass && pageNumber === newPageNumber)
			return silentfail ? false : $.Dialog.info(title, 'You are already on page '+pageNumber);

		$.Dialog.wait(title, 'Loading page '+newPageNumber);

		$.get(target, {js: true}, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(title, this.message);

			newPageNumber = parseInt(this.page, 10);

			window.updateTimesF();
			$('nav').find('li.active').children().last().text('Page '+newPageNumber);
			$title.text(this.title);
			history.pushState({},'',target);

			var max = this.maxpage;
			$pagination.each(function(){
				var $ul = $(this),
					$childs = $ul.children();

				if ($childs.length !== max){
					$ul.empty();
					for (var i = 1; i <= max; i++)
						$ul.append('<li><a href="'+basePath+i+'">'+i+'</a></li>');
					$childs = $ul.children();
				}
				else $childs.eq(pageNumber-1).html('<a href="'+basePath+pageNumber+'">'+pageNumber+'</a>');
				$childs.eq(newPageNumber-1).html('<strong>'+newPageNumber+'</strong>');
			});

			$(this.update).html(this.output).trigger('page-switch');

			pageNumber = newPageNumber;
			$.Dialog.close();
		}));
	};
});
