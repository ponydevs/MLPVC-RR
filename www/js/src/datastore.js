(() => {
	'use strict';

	const regexRegex = new RegExp('^/(.*)/([a-z]*)$','u');

	Array.from(document.querySelectorAll('.datastore')).forEach(el => {
		const data = JSON.parse(el.innerText);
		for (const name of Object.keys(data)) {
			let value = data[name];
			if (typeof value === 'string' && regexRegex.test(value)){
				const match = value.match(regexRegex);
				value = new RegExp(match[1],match[2]);
			}
			window[name] = value;
		}
	});
})();
