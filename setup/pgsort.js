#!/usr/bin/node
const fs = require('fs');
const path = require('path');
const glob = require('glob');
const cwd = path.dirname(require.main.filename);
const parseRow = row => {
	let match = row.match(/VALUES \((\d+)(?:, (\d+|NULL))?[, )]/);
	return match ? [match[1], match[2]] : [];
};
glob('*.pg.sql', { cwd, dot: false, absolute: true }, function(err, files) {
	if (err) throw err;

	for (let i = 0; i < files.length; i++)
		(function(fpath) {
			fs.readFile(fpath, 'utf8', function(err, data) {
				if (err) throw err;
				let test = /INSERT INTO ((?:public\.)?[a-z_\-]+)(?:\s+\([^)]+\))?\s+VALUES\s*\((\d+),[\s\S]+?;(?:\r|\r\n|\n)/g;
				if (!test.test(data))
					return;
				let Tables = {},
					TableCounters = {};
				data.replace(test, function(row, table) {
					if (typeof Tables[table] !== 'object')
						Tables[table] = [];
					Tables[table].push(row);
					TableCounters[table] = 0;
					return row;
				});

				for (let j = 0, k = Object.keys(Tables), l = k.length; j < l; j++){
					let table = k[j];
					Tables[table].sort(function(a, b) {
						a = parseRow(a);
						b = parseRow(b);

						let ix = 0;
						if (a[0] === b[0] && !isNaN(a[1]) && !isNaN(b[1]))
							ix++;

						a[ix] = parseInt(a[ix], 10);
						b[ix] = parseInt(b[ix], 10);

						return a[ix] > b[ix] ? 1 : (a[ix] < b[ix] ? -1 : 0);
					});
				}
				data = data.replace(test, function(row, table) {
					return Tables[table][TableCounters[table]++];
				});
				data = data.replace(/;(?:\r|\r\n|\n)INSERT INTO ((?:public\.)?[a-z_\-]+)(?:\s+\([^)]+\))?\s+VALUES\s+/g, ',\n');
				data = data.replace(/((?:\r|\r\n|\n)\s*(?:\r|\r\n|\n)INSERT INTO ((?:public\.)?[a-z_\-]+)(?:\s+\([^)]+\))?\s+VALUES)\s*\(/g, '$1\n(');
				data = data.replace(/\r\n?/g, '\n');

				fs.writeFile(fpath, data, function(err) {
					if (err) throw err;
				});
			});
		})(files[i]);
});
