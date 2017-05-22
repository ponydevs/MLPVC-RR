/* global $d,$w,$navbar,HandleNav,Time */
(function Paginate(){
	'use strict';

	let pageRegex = /Page \d+/g,
		basePath = location.pathname.replace(/\/\d+$/,'')+'/',
		$pagination = $('.pagination'),
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10),
		maxPages = parseInt($pagination.first().children(':not(.spec)').last().text(), 10),
		title = 'Navigation',
		$GoToPageFormTemplate = $.mk('form').attr('id','goto-page').html(
			`<label>
				<span>Enter page number</span>
				<input type="number" min="1" max="${maxPages}" step="1">
			</label>`
		).on('submit', function(e){
			e.preventDefault();

			let page = parseInt($(this).find('input:visible').val(), 10);
			if (isNaN(page))
				page = 1;

			$.toPage(basePath+Math.max(1, page));
		});

	$d.off('paginate-refresh').on('paginate-refresh',function(){
		basePath = location.pathname.replace(/\/\d+$/,'')+'/';
		$pagination = $('.pagination');
		pageNumber = parseInt($pagination.first().children('li').children('strong').text(), 10);
		title = 'Navigation';

		$pagination.off('click').on('click','a', function(e){
			e.preventDefault();
			e.stopPropagation();
			$('#ctxmenu').hide();

			if (typeof $(this).attr('href') === 'undefined')
				return $.Dialog.request('Navigation',$GoToPageFormTemplate.clone(true,true),'Go to page', function($form){
					$form.find('input:visible').val(pageNumber).get(0).select();
				});

			$.toPage(this.pathname);
		});
		$w.off('nav-popstate').on('nav-popstate',function(e, state, goto){
			let obj = state,
				params = [false, true, undefined, true];
			if (typeof state.baseurl !== 'undefined' && $.Navigation._lastLoadedPathname.replace(/\/\d+($|\?)/,'$1') !== state.baseurl)
				goto(location.pathname+location.search+location.hash,function(){
					$.toPage.apply(obj, params);
				});
			else $.toPage.apply(obj, params);
		});
		$.toPage = function(target, silentfail, bypass, overwriteState, titleProcessor){
			if (!target) target = location.pathname;
			let newPageNumber = parseInt(target.replace(/^.*\/(\d+)(?:\?.*)?$/,'$1'), 10),
				state = this.state || {};

			if (isNaN(newPageNumber))
				return $.Dialog.fail(title, 'Could not get page number to go to');

			if (!bypass && (pageNumber === newPageNumber || pageNumber === state.page))
				return silentfail ? false : $.Dialog.info(title, 'You are already on page '+pageNumber);

			let data = { js: true },
				params = [],
				extraQuery = this.query,
				haveExtraQuery = typeof extraQuery === 'string';
			if (haveExtraQuery){
				params = params.concat(extraQuery.split('&'));
			}
			else if (location.search.length > 1)
				params = params.concat(location.search.substring(1).split('&'));

			if (params.length){
				$.each(params, (_, el) => {
					el = el.replace(/\+/g,' ').split('=');
					if (el[1].length === 0)
						return;
					data[decodeURIComponent(el[0])] = decodeURIComponent(el[1]);
				});
				//noinspection JSUnusedAssignment
				params = undefined;
				// USE data FROM THIS POINT FORWARD
			}

			if (this.gofast){
				$.Dialog.wait(title, `Loading appearance page`);
				data.GOFAST = true;
			}
			else $.Dialog.wait(title, `Loading page ${newPageNumber}`);

			target += location.hash;

			$.get(target, data, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(title, this.message);

				if (data.GOFAST && this.goto)
					return $.Navigation.visit(this.goto,function(){
						$.Dialog.close();
					});

				newPageNumber = parseInt(this.page, 10);

				let $active = $navbar.find('li.active').children().last();
				if (pageRegex.test($active.text()))
					$active.html(function(){ this.innerHTML.replace(pageRegex,`Page ${newPageNumber}`) });

				// Preserve static page title component at the end
				if (typeof titleProcessor === 'function')
					document.title = titleProcessor(newPageNumber);
				document.title = document.title.replace(pageRegex, `Page ${newPageNumber}`);
				$navbar.find('li.active').children().last().html(function(){
					return this.innerHTML.replace(pageRegex, `Page ${newPageNumber}`);
				});

				let newURI = this.request_uri || (basePath+newPageNumber+
						(
							location.search.length > 1
							? location.search
							: ''
						)
					),
					stateParams = [
						{
							paginate: true,
							page: newPageNumber,
							baseurl: this.request_uri.replace(/\/\d+($|\?)/,'$1'),
						},
						'',
						newURI
					];
				if (haveExtraQuery)
					stateParams[0].query = extraQuery;

				if (typeof window.ga === 'function')
					window.ga('send', {
						hitType: 'pageview',
						page: newURI,
						title: document.title,
					});


				if (overwriteState === true || (state.page !== newPageNumber && !isNaN(newPageNumber)) || haveExtraQuery){
					history.replaceState.apply(history, stateParams);
					$.WS.navigate();
				}

				$pagination.html(this.pagination);
				maxPages = parseInt($pagination.first().children(':not(.spec)').last().text(), 10);

				let event = jQuery.Event('page-switch');
				$(this.update).html(this.output).trigger(event);
				Time.Update();
				pageNumber = newPageNumber;

				if (!event.isDefaultPrevented())
					$.Dialog.close();
			}));
		};
	});
})();
