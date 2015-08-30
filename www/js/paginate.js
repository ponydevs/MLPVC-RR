$document.off('paginate-refresh').on('paginate-refresh',function(){
	var $title = $head.children('title'),
		basePath = location.pathname.replace(/(\d+)$/,''),
		$pagination = $('.pagination'),
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10),
		title = 'Navigation';
	$pagination.on('click','a',function(e){
		e.preventDefault();
		e.stopPropagation();

		$('#ctxmenu').hide();

		$.toPage(this.pathname);
	});
	$w.on('nav-popstate',function(e, state){
		$.toPage.call({state:state},location.pathname, true);
	});
	$.toPage = function(target, silentfail, bypass){
		if (!target) target = window.location.pathname;
		var newPageNumber = parseInt(target.substring(basePath.length), 10),
			state = this.state || {};

		if (!bypass && (pageNumber === newPageNumber || pageNumber === state.page))
			return silentfail ? false : $.Dialog.info(title, 'You are already on page '+pageNumber);

		if (location.search.length > 1)
			target += location.search;

		$.Dialog.wait(title, 'Loading page '+newPageNumber);

		$.get(target, {js: true}, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(title, this.message);

			newPageNumber = parseInt(this.page, 10);

			$('nav').find('li.active').children().last().html(function(){
				return this.innerHTML.replace(/Page \d+/,'Page '+newPageNumber);
			});

			// Preserve static page title component at the end
			$title.text($title.text().replace(/^.*( - [^-]+)$/,this.title+'$1'));

			if (state.page !== newPageNumber && !isNaN(newPageNumber))
				history.pushState({paginate:true, page:newPageNumber},'',basePath+newPageNumber+(window.location.search.length > 1 ? location.search : ''));

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

			var event = jQuery.Event('page-switch');
			$(this.update).html(this.output).trigger(event);
			window.updateTimesF();
			pageNumber = newPageNumber;

			if (!event.isDefaultPrevented()) $.Dialog.close();
		}));
	};
});
