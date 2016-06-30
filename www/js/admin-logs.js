/* global DocReady,$w,Time */
DocReady.push(function Logs(){
	'use strict';
	let requesting = false;

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

								$dataDiv.append(`<p>${el.join(' ')}</p>`);
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
		});
	}).trigger('page-switch').on('click','.dynt-el',function(){
		let ww = $w.width();
		if (ww < 650){
			let $this = $(this),
				$td = $this.parent(),
				$tr = $td.parent(),
				$ip = $tr.children('.ip').clone();

			$ip.children('.self').html(function(){
				return $(this).text();
			});
			$ip = $ip.html().split('<br>');

			$.Dialog.info(`Hidden details of entry #${$tr.children('.entryid').text()}`,
				`<b>Timestamp:</b> ${$td.children('time').html().trim().replace(/<br>/,' ')}
				<span class="modal-ip"><br>
					<b>Initiator:</b> ${$ip[0]}<br>
					<b>IP Address:</b> ${$.mk('div').html($ip[1]).text()}
				</span>`
			);
		}
	});
});
