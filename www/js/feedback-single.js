/* global DocReady */
DocReady.push(function FeedbackSingle(){
	'use strict';

	var $chain = $('#feedback-chain'),
		$responseArea = $('#respond'),
		$response = $responseArea.children('textarea'),
		$respond = $responseArea.children('button');

	$respond.on('click',function(e){
		e.preventDefault();

		$.Dialog.wait(false, 'Submitting your response');

		$.post('',{message:$response.val()},$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			$response.val('');
			$chain.html(this.chain);
			window.updateTimes();
			$.Dialog.close();
		}));
	});

	var $openToggle = $('#fb-open-toggle'),
		$status = $('#fb-status');
	$openToggle.on('click',function(){
		var open = $openToggle.hasClass('red'),
			action = open ? 'close' : 'reopen',
			verb = action.replace(/e$/,''),
			Action = $.capitalize(action),
			Verb = $.capitalize(verb);

		$.Dialog.confirm(Action+' feedback','This feedback will be '+verb+'ed and a message will be left letting everyone know about it.<br>'+Action+' this feedback?',Action,function(sure){
			if (!sure) return;

			$.Dialog.wait(false, Verb+'ing feedback');

			$.post('?'+action,$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$openToggle
					[open?'removeClass':'addClass']('red typcn-lock-closed')
					[open?'addClass':'removeClass']('green typcn-lock-open')
					.text(open?'Re-open':'Close');
				$status
					[open?'addClass':'removeClass']('color-red')
					[open?'removeClass':'addClass']('color-green')
					.text(open?'Closed':'Open');
				$responseArea[open?'hide':'show']();

				$chain.html(this.chain);
				window.updateTimes();
				$.Dialog.close();
			}));
		});
	});
});
