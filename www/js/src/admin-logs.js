/* global DocReady,$w,Time */
DocReady.push(function Logs(){
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
	
	$('#logs').find('tbody').off('page-switch').on('page-switch',function(){
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

						let EntryID = parseInt($row.children().first().text());

						$.post(`/admin/logs/details/${EntryID}`, $.mkAjaxHandler(function(){
							if (!this.status) $.Dialog.fail(title,this.message);

							let $dataDiv = $.mk('div').attr('class','expandable-section').css('display','none');
							$.each(this.details, (i,el) => {
								if (typeof el[1] === 'boolean')
									el[1] = `<span class="color-${el[1]?'green':'red'}">${el[1]?'yes':'no'}</span>`;

								let char = /[a-z]$/i;
								el[0] = `<strong>${el[0]}${char.test(el[0]) ? ':' : ''}</strong>`;

								$dataDiv.append(`<div>${el.join(' ')}</div>`);
							});

							$dataDiv.insertAfter($this).slideDown();
							Time.Update();
							$this.addClass('typcn-minus color-darkblue');
						})).always(function(){
							requesting = false;
							$this.removeClass('typcn-refresh');
						}).fail(function(){
							$this.addClass('typcn-times color-red').css('cursor','not-allowed').off('click');
						});
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
});
