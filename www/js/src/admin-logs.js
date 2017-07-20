/* global DocReady,$w,Time */
$(function(){
	'use strict';

	let requesting = false,
		$FilterForm = $('#filter-form');
	
	$FilterForm.on('submit', function(e){
		e.preventDefault();

		let $_entryType = $FilterForm.find('[name="type"] option:selected'),
			_entryTypeValue = $_entryType.val(),
			_byUsername = $FilterForm.find('[name="by"]').val().trim(),
			title = `${_entryTypeValue.length?`${$_entryType.text().replace('of type ','')} entries`:''}${_byUsername.length?`${_entryTypeValue.length?'':'entries'} by ${_byUsername}`:''}`,
			query = title.length ? $FilterForm.serialize() : false;
		$FilterForm.find('button[type=reset]').attr('disabled', query === false);

		if (query !== false)
			$.Dialog.wait('Navigation', `Looking for ${title.replace(/</g,'&lt;')}`);
		else $.Dialog.success('Navigation', 'Search terms cleared');

		$.toPage.call({query:query}, window.location.pathname.replace(/\d+($|\?)/,'1$1'), true, true, false, function(){
			if (query !== false)
				return /^Page \d+/.test(document.title)
					? `${title} - ${document.title}`
					: document.title.replace(/^.*( - Page \d+)/, title+'$1');
			else return document.title.replace(/^.* - (Page \d+)/, '$1');
		});
	}).on('reset', function(e){
		e.preventDefault();

		$FilterForm.find('[name="type"]').val('');
		$FilterForm.find('[name="by"]').val('');
		$FilterForm.triggerHandler('submit');
	});

	let $logsTable = $('#logs');
	$logsTable.find('tbody').off('page-switch').on('page-switch',function(){
		$(this).children().each(function(){
			let $row = $(this);

			$row.find('.expand-section').off('click').on('click',function(){
				let $this = $(this),
					title = 'Log entry details';

				if ($this.hasClass('typcn-minus')) $this.toggleClass('typcn-minus typcn-plus').next().stop().slideUp();
				else {
					if ($this.next().length === 1)
						$this.toggleClass('typcn-minus typcn-plus').next().stop().slideDown();
					else {
						if (requesting) return false;
						requesting = true;

						$this.removeClass('typcn-minus typcn-plus').addClass('typcn-refresh');

						let EntryID = parseInt($row.children().first().text()),
							fail = function(){
								$this.addClass('typcn-times color-red').css('cursor','not-allowed').off('click');
							};

						$.post(`/admin/logs/details/${EntryID}`,$.mkAjaxHandler(function(){
							if (!this.status){
								if (this.unlickable === true)
									$this.replaceWith($this.text().trim());
								$.Dialog.fail(title,this.message);
								return fail();
							}

							let $dataDiv = $.mk('div').attr('class','expandable-section').css('display','none');
							$.each(this.details, (_, detail) => {
								let $info, $key = $.mk('strong').html(detail[0]+': ');
								if (typeof detail[2] === 'string')
									$key.addClass(`color-${detail[2]}`);
								if (detail[1] === null)
									$info = $.mk('em').addClass(`color-darkblue`).text('empty');
								else if (typeof detail[1] === 'boolean')
									$info = $.mk('span').addClass(`color-${detail[1]?'green':'red'}`).text(detail[1]?'yes':'no');
								else if ($.isArray(detail[1])){
									$info = undefined;
									$key.html($key.html().replace(/:\s$/, ''));
								}
								else $info = detail[1];

								$dataDiv.append($.mk('div').append($key,$info));
							});

							$dataDiv.insertAfter($this).slideDown();
							Time.Update();
							$this.addClass('typcn-minus color-darkblue');
						})).always(function(){
							requesting = false;
							$this.removeClass('typcn-refresh');
						}).fail(fail);
					}
				}
			});
			$row.find('.server-init').off('click').on('click',function(){
				$FilterForm.find('[name="by"]').val($(this).text().trim());
				$FilterForm.triggerHandler('submit');
			});
		});
	}).trigger('page-switch').on('click','.dynt-el',function(){
		let ww = $w.width();
		if (ww >= 650)
			return true;

		let $this = $(this),
			$td = $this.parent(),
			$tr = $td.parent(),
			$ip = $tr.children('.ip');

		if ($ip.children('a').length){
			$ip = $ip.clone(true,true);
			$ip.children('.self').html(function(){
				return $(this).text();
			});
		}
		let $split = $ip.contents(),
			$span = $.mk('span').attr('class','modal-ip').append(
				'<br><b>Initiator:</b> ',
				$split.eq(0)
			);
		if ($split.length > 1)
			$span.append(`<br><b>IP Address:</b> ${$split.get(2).textContent}`);

		$.Dialog.info(`Hidden details of entry #${$tr.children('.entryid').text()}`,
			$.mk('div').append(
				`<b>Timestamp:</b> ${$td.children('time').html().trim().replace(/<br>/,' ')}`,
				$span
			)
		);
	});

	const viewStates = [
		{
			className: 'darkblue',
			showins: true,
			showdel: true,
			title: 'diff',
		},
		{
			className: 'green',
			showins: true,
			showdel: false,
			title: 'new',
		},
		{
			className: 'red',
			showins: false,
			showdel: true,
			title: 'old',
		}
	];

	$logsTable.on('click contextmenu', '.btn.view-switch', e => {
		let backwards = e.type === 'contextmenu';
		if (backwards && e.shiftKey)
			return true;

		e.preventDefault();

		let $btn = $(e.target),
			$diffWrap = $btn.next(),
			state = $btn.attr('class').match(/\b(darkblue|green|red)\b/)[1],
			nextState;

		for (let i = 0; i<viewStates.length; i++){
			if (viewStates[i].className === state)
				nextState = viewStates[i+(backwards ? -1 : 1)];
		}
		if (typeof nextState === 'undefined')
			nextState = viewStates[backwards ? viewStates.length-1 : 0];

		$diffWrap.find('ins')[nextState.showins ? 'show' : 'hide']();
		$diffWrap.find('del')[nextState.showdel ? 'show' : 'hide']();
		$diffWrap[!nextState.showins || !nextState.showdel ? 'addClass' : 'removeClass']('no-colors');
		$diffWrap[$diffWrap.contents().filter(function(){
			return /^(del|ins)$/.test(this.nodeName.toLowerCase()) ? this.style.display !== 'none' : true;
		}).length === 0 ? 'addClass' : 'removeClass']('empty');
		$btn.removeClass(state).addClass(nextState.className).text(nextState.title);
	});
});
