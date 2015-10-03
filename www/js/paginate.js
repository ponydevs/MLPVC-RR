(function Paginate(){
	if (window[" paginationHandlerBound"] === true) return;
	window[" paginationHandlerBound"] = true;
	var pageRegex = /Page \d+/g;

	$d.on('paginate-refresh',function(){
		var basePath = location.pathname.replace(/\/\d+$/,'')+'/',
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

				var $active = $navbar.find('li.active').children().last();
				if (pageRegex.test($active.text()))
					$active.html(function(){
						return this.innerHTML.replace(pageRegex,'Page '+newPageNumber);
					});

				// Preserve static page title component at the end
				document.title = document.title.replace(pageRegex, 'Page '+newPageNumber);

				if (state.page !== newPageNumber && !isNaN(newPageNumber))
					history.pushState({paginate:true, page:newPageNumber},'',basePath+'/'+newPageNumber+(window.location.search.length > 1 ? location.search : ''));

				$pagination.html(this.pagination);

				var event = jQuery.Event('page-switch');
				$(this.update).html(this.output).trigger(event);
				window.updateTimes();
				pageNumber = newPageNumber;

				if (!event.isDefaultPrevented()) $.Dialog.close();
			}));
		};
	});
})();
