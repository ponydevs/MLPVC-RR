(function(){
	"use strict";

	let interval = false;
	let responseTimes = [];
	const
		$sendHello = $('#send-hello'),
		$wssStatus = $('#wss-status'),
		$wssHeartbeat = $('#wss-heartbeat'),
		$wssResponseTime = $('#wss-response-time'),
		$connectionList = $('#connection-list'),
		anonUsername = 'Anonymous',
		updateStatus = function(){
			$wssHeartbeat.removeClass('beat');
			const startTime = new Date().getTime();
			if ($.WS.down || $.WS.conn.disconnected){
				if (interval !== false)
					return;
				interval = setInterval(updateStatus, 1000);
				$wssStatus.removeClass('info success').addClass('fail').text(
					$.WS.down
					? 'Socket.IO server is down and/or client library failed to load'
					: 'Disconnected'
				);
				$wssHeartbeat.addClass('dead');
				$sendHello.disable();
				return;
			}
			else if (interval !== false){
				clearInterval(interval);
				interval = false;
				$wssStatus.removeClass('info fail').addClass('success');
				$wssHeartbeat.removeClass('dead');
				$sendHello.enable();
			}
			if ($connectionList.is(':hover')){
				setTimeout(updateStatus, 500);
				$wssStatus.text('Paused while hovering entries');
				return;
			}
			else $wssStatus.text('Connected');

			$.WS.devquery('status',{},function(data){
				$wssHeartbeat.addClass('beat');
				const ips = [];
				const conns = {};
				Object.keys(data.clients).forEach(key => {
					const c = data.clients[key];
					c.ip = c.ip.replace(/^::ffff:/,'');
					const ip = 'ip-'+c.ip.replace(/[^a-f\d]/g,'-');
					ips.push(ip);
					if (!conns[ip])
						conns[ip] = [];
					conns[ip].push(c);
				});
				if (ips.length === 0)
					$connectionList.empty();
				else {
					$connectionList.children().filter((_, el) => !conns[el.id]).remove();
					ips.forEach(ip => {
						const ipConns = conns[ip];
						const pages = {};
						const usernames = {};
						ipConns.forEach(conn => {
							if (conn.page)
								pages[conn.page] = {
									since: conn.connectedSince,
								};
							if (conn.username)
								usernames[conn.username] = (usernames[conn.username] || 0) + 1;
							else usernames[anonUsername] = (usernames[anonUsername] || 0) + 1;
						});
						const usernameKeys = Object.keys(usernames);
						let $li = $(document.getElementById(ip));
						if ($li.length === 0){
							$li = $.mk('li', ip);
							$connectionList.append($li);
						}
						$li.empty().append(`<h3>${ipConns[0].ip}</h3>`);
						if (usernameKeys.length)
							$li.append(
								`<p><strong>Users:</strong></p>`,
								$.mk('ul').append(
									usernameKeys.map(el => {
										const count = usernames[el];
										return $.mk('li').html(
											(
												el !== anonUsername
												? `<a href="/@${el}" target="_blank">${el}</a>`
												: anonUsername
											)+(
												count > 1
												? ` (${count})`
												: ''
											)
										)
									})
								)
							);
						const pageKeys = Object.keys(pages);
						if (pageKeys.length)
							$li.append(
								`<p><strong>Pages:</strong></p>`,
								$.mk('ul').append(
									pageKeys.map(el => {
										return $.mk('li').append(
											$.mk('a').attr({href:el,target:'_blank'}).text(el),
											` (${pages[el].since})`
										);
									})
								)
							);
					});
				}
				const endTime = new Date().getTime();
				responseTimes.push(endTime-startTime);
				responseTimes = responseTimes.slice(-20);
				$wssResponseTime.text($.average(responseTimes).toFixed(0)+'ms');
				setTimeout(updateStatus, 1000);
			});
		};

	updateStatus();

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

		$.API.get('/admin/wsdiag/hello', { priv, clientid }, $.mkAjaxHandler(function(){
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
})();
