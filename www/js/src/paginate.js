/* global $d,$w,$navbar,HandleNav,Time */
(function Paginate(){
	'use strict';

	let pageRegex = /Page \d+/g,
		basePath = location.pathname.replace(/\/\d+$/,'')+'/',
		$pagination = $('.pagination'),
		title = 'Navigation',
		$GoToPageFormTemplate = $.mk('form').attr('id','goto-page').html(
			`<label>
				<span>Enter page number</span>
				<input type="number" min="1" step="1" class="large-number-input">
			</label>`
		).on('submit', function(e){
			e.preventDefault();

			let page = parseInt($(this).find('input:visible').val(), 10);
			page = isNaN(page) ? 1 : Math.max(1, page);

			$.Dialog.wait('Navigation','Loading page '+page);
			$.toPage(basePath+page).then(function(){
				$.Dialog.close();
			});
		});

	function getLimits($el){
		if (typeof $el === 'undefined'){
			const path = window.location.pathname.split('/');
			let pageNumber = 1;
			if (path.length > 1){
				const lastItem = path[path.length-1];
				if (!isNaN(lastItem))
					pageNumber = parseInt(lastItem, 10);
			}
			return { pageNumber, maxPages: null };
		}

		return {
			pageNumber: parseInt($el.children('li').children('strong').text(), 10),
			maxPages: parseInt($el.children(':not(.spec)').last().text(), 10),
		};
	}

	function clearLoading(){
		$pagination.removeClass('loading').find('.loading').removeClass('loading');
	}

	$d.off('paginate-refresh').on('paginate-refresh',function(){
		basePath = location.pathname.replace(/\/\d+$/,'')+'/';
		$pagination = $('.pagination');
		title = 'Navigation';

		$pagination.off('click').on('click','a', function(e){
			e.preventDefault();
			e.stopPropagation();
			$('#ctxmenu').hide();

			const $this = $(this);

			if (typeof $this.attr('href') === 'undefined'){
				const limits = getLimits($this.closest('.pagination'));
				return $.Dialog.request('Navigation',$GoToPageFormTemplate.clone(true,true),'Go to page', function($form){
					$form.find('input:visible').attr('max', limits.maxPages).val(limits.pageNumber).get(0).select();
				});
			}

			$this.closest('li').addClass('loading');

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

			const limits = getLimits();
			if (!bypass && (limits.pageNumber === newPageNumber || limits.pageNumber === state.page) && location.pathname === target)
				return silentfail ? false : $.Dialog.fail(title, 'You are already on page '+limits.pageNumber);

			let data = { paginate: true },
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
					if (typeof el[1] !== 'string' || el[1].length === 0)
						return;
					data[decodeURIComponent(el[0])] = decodeURIComponent(el[1]);
				});
				// USE data INSTEAD OF param FROM THIS POINT FORWARD
			}

			if (this.btnl)
				data.btnl = true;
			else $pagination.addClass('loading');

			target += location.hash;

			return new Promise((fulfill, desert) => {
				$.get(target, data, $.mkAjaxHandler(function(){
					if (!this.status){
						clearLoading();
						return $.Dialog.fail(title, this.message);
					}

					if (this.goto){
						clearLoading();
						return $.Navigation.visit(this.goto);
					}

					newPageNumber = parseInt(this.page, 10);

					// Preserve static page title component at the end
					if (typeof titleProcessor === 'function')
						document.title = titleProcessor(newPageNumber);
					document.title = document.title.replace(pageRegex, `Page ${newPageNumber}`);

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

					$pagination.filter('[data-for="'+this.for+'"]').html(this.pagination);

					let event = jQuery.Event('page-switch');
					$(this.update).html(this.output).trigger(event);
					Time.Update();
					clearLoading();
					fulfill();
				})).fail(function(){
					clearLoading();
					desert();
				});
			});
		};
	});
})();
