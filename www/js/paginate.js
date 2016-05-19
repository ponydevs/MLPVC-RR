/* global $d,$w,$navbar,HandleNav */
(function Paginate(){
	'use strict';

	var pageRegex = /Page \d+/g,
		basePath = location.pathname.replace(/\/\d+$/,'')+'/',
		$pagination = $('.pagination'),
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10),
		maxPages = parseInt($pagination.first().children(':not(.spec)').last().text(), 10),
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
					max: maxPages,
					step: 1,
				})
			)
		);

	$d.off('paginate-refresh').on('paginate-refresh',function(){
		basePath = location.pathname.replace(/\/\d+$/,'')+'/';
		$pagination = $('.pagination');
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10);
		title = 'Navigation';

		$pagination.off('click').on('click','a',function(e){
			e.preventDefault();
			e.stopPropagation();
			$('#ctxmenu').hide();

			if (typeof $(this).attr('href') === 'undefined')
				return $.Dialog.request('Navigation',$PaginationForm.clone(true,true),'goto-page','Go to page',function($form){
					$form.find('input:visible').val(pageNumber).get(0).select();
				});

			$.toPage(this.pathname);
		});
		$w.off('nav-popstate').on('nav-popstate',function(e, state, goto){
			var obj = {state:state},
				params = [location.pathname, true, undefined, true];
			if (typeof state.baseurl !== 'undefined' && $.Navigation.lastLoadedPathname.replace(/\/\d+($|\?)/,'$1') !== state.baseurl)
				goto(location.pathname,function(){
					$.toPage.apply(obj, params);
				});
			else $.toPage.apply(obj, params);
		});
		$.toPage = function(target, silentfail, bypass, overwriteState, titleProcessor){
			if (!target) target = location.pathname;
			var newPageNumber = parseInt(target.replace(/^.*\/(\d+)(?:\?.*)?$/,'$1'), 10),
				state = this.state || {};

			if (isNaN(newPageNumber))
				return $.Dialog.fail(title, 'Could not get page number to go to');

			if (!bypass && (pageNumber === newPageNumber || pageNumber === state.page))
				return silentfail ? false : $.Dialog.info(title, 'You are already on page '+pageNumber);

			var data = { js: true },
				params = [],
				extraQuery = this.query;
			if (typeof extraQuery !== 'undefined'){
				if (typeof extraQuery === 'string')
					params = params.concat(extraQuery.split('&'));
				extraQuery = true;
			}
			else {
				extraQuery = false;
				if (location.search.length > 1)
					params = params.concat(location.search.substring(1).split('&'));
			}

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
				if (typeof titleProcessor === 'function')
					document.title = titleProcessor(newPageNumber);
				document.title = document.title.replace(pageRegex, 'Page '+newPageNumber);

				var newURI = this.request_uri || (basePath+newPageNumber+
						(
							location.search.length > 1
							? location.search
							: ''
						)
					),
					stateParams = [{paginate:true, page:newPageNumber, baseurl: this.request_uri.replace(/\/\d+($|\?)/,'$1') },'',newURI];

				if (overwriteState === true)
					history.replaceState(history, stateParams);
				else if ((state.page !== newPageNumber && !isNaN(newPageNumber)) || extraQuery)
					history.replaceState.apply(history, stateParams);

				$pagination.html(this.pagination);
				maxPages = parseInt($pagination.first().children(':not(.spec)').last().text(), 10);

				var event = jQuery.Event('page-switch');
				$(this.update).html(this.output).trigger(event);
				window.updateTimes();
				pageNumber = newPageNumber;

				if (!event.isDefaultPrevented()) $.Dialog.close();
			}));
		};
	});
})();
