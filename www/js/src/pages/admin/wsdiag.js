/* global DocReady,$w */
$(function(){
	"use strict";

	let interval;
	const
		$sendHello = $('#send-hello'),
		$wssStatus = $('#wss-status').children(),
		$wssHeartbeat = $('#wss-heartbeat'),
		updateStatus = function(){
			// Skip the update if the data is being hovered over
			if ($wssStatus.parent().is(':hover')){
				$wssHeartbeat.addClass('paused');
				return;
			}
			$wssHeartbeat.removeClass('paused');

			if ($.WS.down){
				clearInterval(interval);
				$wssStatus.text('Socket.IO server is down and/or client library failed to load');
				$wssHeartbeat.addClass('dead');
				$sendHello.disable();
				return;
			}
			$.WS.devquery('status',{},function(data){
				$wssHeartbeat.removeClass('beat');
				setTimeout(function(){
					$wssHeartbeat.addClass('beat');
				},20);
				delete data.status;
				$wssStatus.text(JSON.stringify(data,null,4));
			});
		};

	updateStatus();
	interval = setInterval(updateStatus,1000);

	$sendHello.on('click', () => {
		$.Dialog.wait('Test PHP to WS server connectivity', 'Sending hello');

		const priv = $.randomString();
		const clientid = $.WS.getClientId();
		const timeout = 5000;
		let responseReceived = false;

		$w.on('ws-hello', (e, response) => {
			if (response.priv !== priv)
				return;

			$w.off('ws-hello');
			responseReceived = true;

			$.Dialog.success(false, 'Hello response received', true);
		});

		$.get('/api/admin/wsdiag/hello', { priv, clientid }, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			if (!responseReceived)
				$.Dialog.success(false, 'Hello sent successfully');
			if (!responseReceived)
				$.Dialog.wait(false, `Waiting for reply (timeout ${timeout/1000}s)`);

			setTimeout(() => {
				if (responseReceived)
					return;

				$w.off('ws-hello');
				$.Dialog.fail(false, 'Hello response timed out');
			}, timeout);
		}));
	});
});
