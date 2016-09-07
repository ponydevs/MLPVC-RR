// jshint ignore: start
var cl = console.log,
	stripAnsi = require('strip-ansi'),
	chalk = require('chalk');
console.log = console.writeLine = function () {
	var args = [].slice.call(arguments);
	if (args.length){
		if (/^(\[\d{2}:\d{2}:\d{2}]|Using|Finished)/.test(args[0]))
			return;
		else if (args[0] == 'Starting'){
			args = ['[' + chalk.green('gulp') + '] ' + stripAnsi(args[1]).replace(/^'(.*)'.*$/,'$1') + ': ' + chalk.magenta('start')];
		}
	}
	return cl.apply(console, args);
};
var stdoutw = process.stdout.write;
process.stdout.write = console.write = function(str){
	var out = [].slice.call(arguments).join(' ');
	if (/\[.*?\d{2}.*?:.*?]/.test(out))
		return;
	stdoutw.call(process.stdout, out);
};

var toRun = process.argv.slice(2).slice(-1)[0] || 'default'; // Works only if task name is the last param
console.writeLine('Starting Gulp task "'+toRun+'"');
var require_list = ['gulp'];
if (['js','dist-js','scss','md','default'].indexOf(toRun) !== -1){
	require_list.push.apply(require_list, [
		'gulp-plumber',
		'gulp-duration',
	]);

	if (toRun !== 'dist-js' && toRun !== 'md')
		require_list.push('gulp-sourcemaps');

	if (toRun === 'scss' || toRun === 'default')
		require_list.push.apply(require_list, [
			'gulp-sass',
			'gulp-autoprefixer',
			'gulp-minify-css',
		]);
	if (toRun === 'js' || toRun === 'dist-js' || toRun === 'default')
		require_list.push.apply(require_list, [
			'gulp-uglify',
			'gulp-babel',
			'gulp-cached'
		]);
	if (toRun === 'md' || toRun === 'default')
		require_list.push.apply(require_list, [
			'gulp-markdown',
			'gulp-dom',
		]);

	if (toRun === 'md' || toRun === 'dist-js' || toRun === 'default')
		require_list.push('gulp-rename');
}
else if (toRun === 'pgsort')
	require_list.push('fs');
console.write('(');
for (var i= 0,l=require_list.length; i<l; i++){
	var v = require_list[i];
	global[v.replace(/^gulp-([a-z]+).*$/, '$1')] = require(v);
	console.write(' '+v);
}
console.writeLine(" )\n");

var workingDir = __dirname;

function Logger(prompt){
	var $p = '['+chalk.blue(prompt)+'] ';
	this.log = function(message){
		console.writeLine($p+message);
	};
	this.error = function(message){
		if (typeof message === 'string'){
			message = message.trim()
				.replace(/[\/\\]?www/,'');
			console.error($p+'Error in '+message);
		}
		else console.log(JSON.stringify(message,null,'4'));
	};
	return this;
}

var SASSL = new Logger('scss');
gulp.task('scss', function() {
	gulp.src('www/scss/src/*.scss')
		.pipe(plumber(function(err){
			SASSL.error(err.relativePath+'\n'+' line '+err.line+': '+err.messageOriginal);
			this.emit('end');
		}))
		.pipe(sourcemaps.init())
			.pipe(sass({
				outputStyle: 'expanded',
				errLogToConsole: true,
			}))
			.pipe(autoprefixer('last 2 version'))
			.pipe(minify({
				processImport: false,
				compatibility: '-units.pc,-units.pt'
			}))
		.pipe(sourcemaps.write('.', {
			includeContent: false,
			sourceRoot: '/scss/src',
		}))
		.pipe(duration('scss'))
		.pipe(gulp.dest('www/scss/min'));
});

var JSL = new Logger('js'),
	DJSL = new Logger('dist-js'),
	jspipe = function(pipe, taskName){
		var noSourcemaps = taskName === 'dist-js';
		pipe =  pipe
			.pipe(duration(taskName))
			.pipe(cached(taskName, { optimizeMemory: true }))
			.pipe(plumber(function(err){
				err =
					err.fileName
					? err.fileName.replace(workingDir,'')+'\n  line '+(
						err._babel === true
						? err.loc.line
						: err.lineNumber
					)+': '+err.message.replace(/^[\/\\]/,'')
					                  .replace(err.fileName.replace(/\\/g,'/')+': ','')
					                  .replace(/\(\d+(:\d+)?\)$/, '')
					: err;
				(noSourcemaps ? DJSL : JSL).error(err);
				this.emit('end');
			}));
		if (!noSourcemaps)
			pipe = pipe.pipe(sourcemaps.init());
		pipe = pipe
				.pipe(babel({
					presets: ['es2015']
				}))
				.pipe(uglify({
					preserveComments: function(_, comment){ return /^!/m.test(comment.value) },
					output: { ascii_only: noSourcemaps },
				}));
		if (noSourcemaps)
			pipe = pipe.pipe(rename(function(path){
				path.basename = path.basename.replace(/\.[^.]+$/i,'');
				path.extname = '.jsx';
			}));
		if (!noSourcemaps)
			pipe = pipe.pipe(sourcemaps.write('../min', {
				includeContent: false,
				sourceRoot: '/js/src',
				sourceMappingURL: function(file){
					return '/js/min/'+(file.relative.replace(/^\/+/,''))+'.map';
				},
			}));
		return pipe;
	},
	JSWatchArray = ['www/js/src/*.js','www/js/src/**/*.js'];
gulp.task('js', function(){
	jspipe(
		gulp.src(JSWatchArray),
		'js'
	).pipe(gulp.dest('www/js/min'));
});
gulp.task('dist-js', function(){
	jspipe(
		gulp.src(['www/dist/*.src.jsx']),
		'dist-js'
	).pipe(gulp.dest('www/dist'));
});

var MDL = new Logger('md');
gulp.task('md', function(){
	gulp.src('README.md')
		.pipe(duration('md'))
		.pipe(plumber(function(err){
			MDL.error(err);
			this.emit('end');
		}))
		.pipe(markdown())
		.pipe(dom(function(){
			var document = this,
				el = document.getElementById('what-s-this-site-'),
				newElements = '<section class="'+el.id+'">'+el.outerHTML;

			while (el.nextElementSibling !== null && el.nextElementSibling.id !== 'contributing'){
				var next = el.nextElementSibling;
				if (next.nodeName.toLowerCase() == 'h2')
					newElements += '</section><section class="'+next.id+'">';
				newElements += next.outerHTML;
				el = next;
			}

			return newElements+'\n';
		}))
		.pipe(rename('about.html'))
		.pipe(gulp.dest('www/views'));
});

var PGL = new Logger('pgsort'),
	parseRow = function(r){
		var match = r.match(/VALUES \((\d+)(?:, (\d+|NULL))?[, )]/);
		if (!match)
			return [];
		return [match[1], match[2]];
	};
gulp.task('pgsort', function(){
	try {
		fs.readdir('./setup', function(err, dir){
			if (err) throw err;

			var i = 0;
			while (i < dir.length){
				if (!/\.pg\.sql$/.test(dir[i])){
					dir.splice(i, 1);
					continue;
				}
				i++;
			}

			for (i = 0; i<dir.length; i++)
				(function(fpath){
					fs.readFile(fpath, 'utf8', function(err, data){
						if (err) throw err;
						var test = /INSERT INTO "?([a-z_\-]+)"?\s*VALUES\s*\((\d+),[\s\S]+?;(?:\r|\r\n|\n)/g;
						if (!test.test(data))
							return;
						var Tables = {},
							TableCounters = {};
						data.replace(test,function(row,table){
							if (typeof Tables[table] !== 'object')
								Tables[table] = [];
							Tables[table].push(row);
							TableCounters[table] = 0;
							return row;
						});

						for (var j = 0, k = Object.keys(Tables), l = k.length; j<l; j++){
							var table = k[j];
							Tables[table].sort(function(a,b){
								a = parseRow(a);
								b = parseRow(b);

								var ix = 0;
								if (a[0] === b[0] && !isNaN(a[1]) && !isNaN(b[1]))
									ix++;

								a[ix] = parseInt(a[ix], 10);
								b[ix] = parseInt(b[ix], 10);

								return a[ix] > b[ix] ? 1 : (a[ix] < b[ix] ? -1 : 0);
							})
						}
						data = data.replace(test,function(row,table){
							return Tables[table][TableCounters[table]++];
						});
						data = data.replace(/;(?:\r|\r\n|\n)INSERT INTO "?([a-z_\-]+)"?\s+VALUES\s+/g,',\n');
						data = data.replace(/((?:\r|\r\n|\n)\s*(?:\r|\r\n|\n)INSERT INTO "?([a-z_\-]+)"?\s*VALUES)\s*\(/g,'$1\n(');
						data = data.replace(/\r\n?/g,'\n');

						fs.writeFile(fpath, data, function(err){
							if (err) throw err;
						});
					});
				})('./setup/'+dir[i]);
		});
	}
	catch(err){
		PGL.error(err);
		this.emit('end');
	}
});

gulp.task('default', ['js', 'dist-js', 'scss', 'md'], function(){
	gulp.watch(JSWatchArray, {debounceDelay: 2000}, ['js']);
	JSL.log('File watcher active');
	gulp.watch(['www/dist/*.src.jsx'], {debounceDelay: 2000}, ['dist-js']);
	DJSL.log('File watcher active');
	gulp.watch('www/scss/src/*.scss', {debounceDelay: 2000}, ['scss']);
	SASSL.log('File watcher active');
	gulp.watch('README.md', {debounceDelay: 2000}, ['md']);
	MDL.log('File watcher active');
});
