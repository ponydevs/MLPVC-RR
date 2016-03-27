// Color Average form
var $ColorAvgForm;
(function($){
	'use strict';

	if (typeof $ColorAvgForm !== 'undefined')
		return;

	var $ColorAvgInputRow = $.mk('div').attr('class','input-group-3').append(
			"<input type='text' pattern='^([1-9]?\\d|1\\d{2}|2[0-4]\\d|25[0-5])$' maxlength='3' placeholder='Red' class='align-center'>",
			"<input type='text' pattern='^([1-9]?\\d|1\\d{2}|2[0-4]\\d|25[0-5])$' maxlength='3' placeholder='Green' class='align-center'>",
			"<input type='text' pattern='^([1-9]?\\d|1\\d{2}|2[0-4]\\d|25[0-5])$' maxlength='3' placeholder='Blue' class='align-center'>"
		),
		$inputsDiv = $.mk('div').attr('class','inputs'),
		lsColorsStoreKey = 'color-avg-save',
		storeAvgColors = function(){
			var parsed = [];

			$ColorAvgForm.find('.inputs input').each(function(){
				parsed.push(this.value.trim());
			});

			localStorage.setItem(lsColorsStoreKey, JSON.stringify(parsed));
		},
		readAvgColors = function(){
			var data = localStorage.getItem(lsColorsStoreKey);

			if (typeof data !== 'string') return;
			data = JSON.parse(data);

			$inputsDiv.empty();
			var $appendto;
			for (var i=0,l=data.length; i<l; i++){
				var eq = i % 3;
				if (eq === 0)
					$appendto = $ColorAvgInputRow.clone(true,true).appendTo($inputsDiv);

				$appendto.children().eq(eq).val(data[i]);
			}
			$appendto.children().first().triggerHandler('change');
		},
		$AvgColorPreview = $.mk('span').css({
			position: 'absolute',
			top: 0,
			left: 0,
			width: '100%',
			height: '100%',
			display: 'block',
		}).html('&nbsp;'),
		$AvgColorPreviewTD = $.mk('td').attr('rowspan','2').css({
			width: '25%',
			position: 'relative',
		}).append($AvgColorPreview),
		$AvgRedTD =  $.mk('td').attr('class','color-red'),
		$AvgGreenTD =  $.mk('td').attr('class','color-green'),
		$AvgBlueTD =  $.mk('td').attr('class','color-darkblue'),
		$AvgHexTD =  $.mk('td').attr('colspan','3'),
		calcAvg = function(){
			setTimeout(function(){
				var count = 0,
					rAvg = 0,
					gAvg = 0,
					bAvg = 0;

				$ColorAvgForm.find('.input-group-3').each(function(){
					var $allInputs = $(this).children(),
						r = $allInputs.eq(0).val(),
						g = $allInputs.eq(1).val(),
						b = $allInputs.eq(2).val();

					if (!r.length || !g.length || !b.length)
						return;

					var row = {
						r: parseInt(r, 10),
						g: parseInt(g, 10),
						b: parseInt(b, 10),
					}, stop = false;

					$.each(row,function(k,v){
						if (isNaN(v) || v < 0 || v > 255)
							return !(stop = true);
					});
					if (stop) return;

					count++;
					rAvg += parseInt(r, 10);
					gAvg += parseInt(g, 10);
					bAvg += parseInt(b, 10);
				});

				if (count){
					rAvg = Math.round(rAvg / count);
					gAvg = Math.round(gAvg / count);
					bAvg = Math.round(bAvg / count);
				}

				$AvgRedTD.text(rAvg);
				$AvgGreenTD.text(gAvg);
				$AvgBlueTD.text(bAvg);

				var hex = $.rgb2hex({ r:rAvg, g:gAvg, b:bAvg });
				$AvgColorPreview.css('background-color',hex);
				$AvgHexTD.text(hex);
			},1);
		};
	$ColorAvgInputRow.on('paste','input',function(){
		var $this = $(this);
		setTimeout(function(){
			if (!$this.is(':valid'))
				return;

			$this.val($this.value().trim()).triggerHandler('change');
			var $next = $this.index() < 2 ? $this.next() : $this.parent().next().children().first();

			if ($next.length)
				$next.focus();
		},1);
	}).on('change keyup blur','input',function(){
		calcAvg();
	});
	calcAvg();
	for (var i = 0; i<10; i++)
		$inputsDiv.append($ColorAvgInputRow.clone(true,true));
	$ColorAvgForm = $.mk('form').attr('id','color-avg-form').append(
		$inputsDiv,
		$.mk('button').attr('class','green typcn typcn-plus').text('Add more inputs').on('click',function(e){
			e.preventDefault();

			$inputsDiv.append($ColorAvgInputRow.clone(true,true));
		}),
		$.mk('table').attr({
			'class':'align-center',
			style: 'display:table;width:100%;font-family:"Source Code Pro","Consolas",monospace;font-size:1.3em;border-collapse:collapse'
		}).append(
			$.mk('tr').append(
				$AvgColorPreviewTD,
				$AvgRedTD,
				$AvgGreenTD,
				$AvgBlueTD
			),
			$.mk('tr').append($AvgHexTD)
		).find('td').css('border','1px solid black').end()
	).on('submit',function(e){
		e.preventDefault();

		storeAvgColors();

		$.Dialog.close();
	}).on('read-colors',readAvgColors);
})(jQuery);
