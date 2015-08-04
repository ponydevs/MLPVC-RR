$(function(){
	$('#logs').find('tbody').children().each(function(){
		var $row = $(this);

		$row.find('.expand-section').on('click',function(){
			var $this = $(this),
				title = 'Log entry details';

			if ($this.hasClass('typcn-minus')) $this.toggleClass('typcn-minus typcn-plus').next().stop().slideUp();
			else {
				if ($this.next().length === 1)
					$this.toggleClass('typcn-minus typcn-plus').next().stop().slideDown();
				else {
					var EntryID = parseInt($row.children().first().text());
					$.post('/logs/details/'+EntryID, $.mkAjaxHandler(function(){
						if (this.status){
							var $dataDiv = $(document.createElement('div')).attr('class','expandable-section').css('display','none');
							$.each(this.details,function(i,el){
								if (typeof el[1] === 'boolean')
									el[1] = '<span class="color-'+(el[1]?'green':'red')+'">'+(el[1]?'yes':'no')+'</span>';

								el[0] = '<strong>'+el[0]+(/[\wáéíóöőúüű]$/.test(el[0]) ? ':' : '')+'</strong>';

								$dataDiv.append('<p>'+el.join(' ')+'</p>');
							});

							$dataDiv.insertAfter($this).slideDown();
							$this.toggleClass('typcn-minus typcn-plus');
						}
						else $.Dialog.fail(title,this.message);
					}));
				}
			}
		});
	});

	$('.dynt-el').on('click',function(){
		var ww = $(window).width();
		if (ww < 650){
			var $this = $(this),
				$td = $this.parent(),
				$tr = $td.parent(),
				$ip = $tr.children('.ip').clone();

			$ip.children('.self').html(function(){
				return ' ('+$(this).text()+')';
			});
			$ip = $ip.html().split('<br>');

			$.Dialog.info($tr.children('.entryid').text()+'. bejegyzés rejtett adatai','\
				<b>Timestamp:</b> '+$td.children('time').html().trim().replace(/<br>/,' ')+'\
				<span class="modal-ip"><br>\
					<b>Initiator:</b> '+$ip[0]+'<br>\
					<b>IP Address:</b> '+$(document.createElement('div')).html($ip[1]).text()+'\
				</span>'
			);
		}
	});
});
