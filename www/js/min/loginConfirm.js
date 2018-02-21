window.opener.$.PopupMoveCenter(window, 400, 400);
if (!document.querySelector('.xdebug-error'))
	window.opener.__authCallback();
else console.log('xdebug error detected');
