(function Paginate(){
	if (window[" paginationHandlerBound"] === true) return;
	window[" paginationHandlerBound"] = true;

	var pageRegex = /Page \d+/g,
		basePath = location.pathname.replace(/\/\d+$/,'')+'/',
		$pagination = $('.pagination'),
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10),
		title = 'Navigation',
		$PaginationForm = $.mk('form').attr('id','goto-page').on('submit',function(e){
			e.preventDefault();

			var page = parseInt($(this).find('input:visible').val(), 10);
			if (isNaN(page))
				page = 1;

			$.toPage(basePath+Math.max(1, page));
		}).append(
			$.mk('label').append(
				$.mk('span').text('Enter page number'),
				$.mk('input').attr({
					type: 'number',
					min: 1,
					step: 1,
				})
			)
		);

	$d.on('paginate-refresh',function(){
		basePath = location.pathname.replace(/\/\d+$/,'')+'/';
		$pagination = $('.pagination');
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10);
		title = 'Navigation';

		$pagination.on('click','a',function(e){
			e.preventDefault();
			e.stopPropagation();

			$('#ctxmenu').hide();

			if (typeof $(this).attr('href') === 'undefined')
				return $.Dialog.request('Navigation',$PaginationForm.clone(true,true),'goto-page','Go to page',function($form){
					$form.find('input:visible').val(pageNumber).get(0).select()
				});

			$.toPage(this.pathname);
		});
		$w.on('nav-popstate',function(e, state){
			$.toPage.call({state:state},location.pathname, true);
		});
		$.toPage = function(target, silentfail, bypass){
			if (!target) target = window.location.pathname;
			var newPageNumber = parseInt(target.replace(/^.*\/(\d+)(?:$|\?)/,'$1'), 10),
				state = this.state || {};

			if (!bypass && (pageNumber === newPageNumber || pageNumber === state.page))
				return silentfail ? false : $.Dialog.info(title, 'You are already on page '+pageNumber);

			var data = { js: true},
				params = [],
				extraQuery = this.query;
			if (location.search.length > 1)
				params = params.concat(location.search.substring(1).split('&'));
			if (typeof extraQuery === 'string')
				params = params.concat(extraQuery.split('&'));
			else extraQuery = false;

			if (params.length) $.each(params,function(_,el){
				el = el.replace(/\+/g,' ').split('=');
				data[decodeURIComponent(el[0])] = decodeURIComponent(el[1]);
			});

			$.Dialog.wait(title, 'Loading page '+newPageNumber);

			$.get(target, data, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(title, this.message);

				newPageNumber = parseInt(this.page, 10);

				var $active = $navbar.find('li.active').children().last();
				if (pageRegex.test($active.text()))
					$active.html(function(){
						return this.innerHTML.replace(pageRegex,'Page '+newPageNumber);
					});

				// Preserve static page title component at the end
				document.title = document.title.replace(pageRegex, 'Page '+newPageNumber);

				var newURI = this.request_uri || (basePath+newPageNumber+(window.location.search.length > 1 ? location.search : ''));

				if ((state.page !== newPageNumber && !isNaN(newPageNumber)) || extraQuery)
					history.pushState({paginate:true, page:newPageNumber},'',newURI);

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
