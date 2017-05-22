/* global DocReady */
DocReady.push(function(){
	"use strict";

	const
		$wssStatus = $('#wss-status').children(),
		$wssHeartbeat = $('#wss-heartbeat'),
		updateStatus = function(){
			if ($.WS.down){
				clearInterval(window._WSStatusCheckInterval);
				$wssStatus.text('Socket.IO server is down and/or client library failed to load');
				$wssHeartbeat.addClass('dead');
				return;
			}
			$.WS.devquery('status',{},function(data){
				$wssHeartbeat.removeClass('beat');
				setTimeout(function(){
					$wssHeartbeat.addClass('beat');
				},10);
				delete data.status;
				$wssStatus.text(JSON.stringify(data,null,4));
			});
		};

	updateStatus();
	window._WSStatusCheckInterval = setInterval(updateStatus,1000);
},function(){
	"use strict";

	clearInterval(window._WSStatusCheckInterval);
	delete window._WSStatusCheckInterval;
});
