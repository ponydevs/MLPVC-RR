$(function(){
	$('#logs').find('tbody').children().each(function(){
		var $row = $(this);

		$row.find('.expand-section').on('click',function(){
			var $this = $(this),
				title = 'Log entry details';

			if ($this.hasClass('expanded')) $this.removeClass('expanded').next().stop().slideUp();
			else {
				if ($this.next().length === 1)
					$this.addClass('expanded').next().stop().slideDown();
				else {
					var EntryID = parseInt($row.children().first().text());
					$.ajax({
						method: "POST",
						url: '/logs/details/'+EntryID,
						success: function(data){
							if (typeof data === 'string') return console.log(data) === $(window).trigger('ajaxerror');

							if (data.status){
								var $dataDiv = $(document.createElement('div')).attr('class','expandable-section').css('display','none');
								$.each(data.details,function(i,el){
									if (typeof el[1] === 'boolean')
										el[1] = '<span class="color-'+(el[1]?'green':'red')+'">'+(el[1]?'yes':'no')+'</span>';

									el[0] = '<strong>'+el[0]+(/[\wáéíóöőúüű]$/.test(el[0]) ? ':' : '')+'</strong>';

									$dataDiv.append('<p>'+el.join(' ')+'</p>');
								});

								$dataDiv.insertAfter($this).slideDown();
								$this.addClass('expanded');
							}
							else $.Dialog.fail(title,data.message);
						}
					});
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
				<b>Időpont:</b> '+$td.children('time').html().trim().replace(/<br>/,' ')+'\
				<span class="modal-ip"><br>\
					<b>Kezdeményező:</b> '+$ip[0]+'<br>\
					<b>IP Cím:</b> '+$(document.createElement('div')).html($ip[1]).text()+'\
				</span>'
			);
		}
	});

	$('#clearlogs').on('click',function(){
		var title = this.value;
		$.Dialog.confirm(title, 'A teljes rendszernapló végleges kürítésére készül, innen nincs visszaút.<br>Biztos, hogy ezt szeretné tenni?',function(sure){
			if (!sure) return;

			$.Dialog.wait('A rendszernapló ürítése folyamatban van');

			$.ajax({
				method: "POST",
				url: '/napló/ürít',
				success: function(data){
					if (typeof data === 'string') return console.log(data) === $(window).trigger('ajaxerror');

					if (data.status){
						$.Dialog.success(title,data.message);
						setTimeout(function(){
							window.location.reload();
						},1500);
					}
					else $.Dialog.fail(title,data.message);
				}
			});
		});
	});
});