/* global DocReady */
DocReady.push(function Logs(){
	'use strict';
	var requesting = false;

	$('#logs').find('tbody').off('page-switch').on('page-switch',function(){
		$(this).children().each(function(){
			var $row = $(this);

			$row.find('.expand-section').off('click').on('click',function(){
				var $this = $(this),
					title = 'Log entry details';

				if ($this.hasClass('typcn-minus')) $this.toggleClass('typcn-minus typcn-plus').next().stop().slideUp();
				else {
					if ($this.next().length === 1)
						$this.toggleClass('typcn-minus typcn-plus').next().stop().slideDown();
					else {
						if (requesting) return false;
						requesting = true;

						var EntryID = parseInt($row.children().first().text());

						$.post('/logs/details/'+EntryID, $.mkAjaxHandler(function(){
							if (!this.status) $.Dialog.fail(title,this.message);

							var $dataDiv = $.mk('div').attr('class','expandable-section').css('display','none');
							$.each(this.details,function(i,el){
								if (typeof el[1] === 'boolean')
									el[1] = '<span class="color-'+(el[1]?'green':'red')+'">'+(el[1]?'yes':'no')+'</span>';

								el[0] = '<strong>'+el[0]+(/[\wáéíóöőúüű]$/.test(el[0]) ? ':' : '')+'</strong>';

								$dataDiv.append('<p>'+el.join(' ')+'</p>');
							});

							$dataDiv.insertAfter($this).slideDown();
							$this.toggleClass('typcn-minus typcn-plus');
						})).always(function(){
							requesting = false;
						});
					}
				}
			});
		});
	}).trigger('page-switch').on('click','.dynt-el',function(){
		var ww = $(window).width();
		if (ww < 650){
			var $this = $(this),
				$td = $this.parent(),
				$tr = $td.parent(),
				$ip = $tr.children('.ip').clone();

			$ip.children('.self').html(function(){
				return $(this).text();
			});
			$ip = $ip.html().split('<br>');

			$.Dialog.info('Hidden details of entry #'+$tr.children('.entryid').text(),
				'<b>Timestamp:</b> '+$td.children('time').html().trim().replace(/<br>/,' ')+
				'<span class="modal-ip"><br>'+
					'<b>Initiator:</b> '+$ip[0]+'<br>'+
					'<b>IP Address:</b> '+$.mk('div').html($ip[1]).text()+
				'</span>'
			);
		}
	});
});
