/* eslint-env node */
/* eslint no-console:0 */
(function() {

	"use strict";

	const
		chalk = require('chalk'),
		gulp = require('gulp'),
		plumber = require('gulp-plumber'),
		sass = require('gulp-sass'),
		autoprefixer = require('gulp-autoprefixer'),
		cleanCss = require('gulp-clean-css'),
		uglify = require('gulp-uglify'),
		babel = require('gulp-babel'),
		cached = require('gulp-cached'),
		rename = require('gulp-rename'),
		workingDir = __dirname;

	class Logger {
		constructor(prompt) {
			this.prefix = '[' + chalk.blue(prompt) + '] ';
		}

		log(message) {
			console.log(this.prefix + message);
		}

		error(message) {
			if (typeof message === 'string'){
				message = message.trim()
					.replace(/[/\\]?public/, '');
				console.error(this.prefix + 'Error in ' + message);
			}
			else console.log(JSON.stringify(message, null, '4'));
		}
	}

	const appendMinSuffix = () => rename(path => { path.extname = `.min${path.extname}` });

	let SASSL = new Logger('scss'),
		SASSWatchArray = ['public/scss/src/*.scss', 'public/scss/src/**/*.scss'];
	gulp.task('scss', () => {
		return gulp.src(SASSWatchArray)
			.pipe(plumber(function(err) {
				SASSL.error(err.relativePath + '\n' + ' line ' + err.line + ': ' + err.messageOriginal);
				this.emit('end');
			}))
			.pipe(sass({
				outputStyle: 'expanded',
				errLogToConsole: true,
			}))
			.pipe(autoprefixer({
				browsers: ['last 2 versions', 'not ie <= 11'],
			}))
			.pipe(cleanCss({
				processImport: false,
				compatibility: '-units.pc,-units.pt'
			}))
			.pipe(appendMinSuffix())
			.pipe(gulp.dest('public/scss/min'));
	});

	let JSL = new Logger('js'),
		JSWatchArray = [
			'public/js/src/*.js',
			'public/js/src/**/*.js',
			'public/js/src/*.jsx',
			'public/js/src/**/*.jsx'
		];
	gulp.task('js', () => {
		return gulp.src(JSWatchArray)
			.pipe(cached('js', { optimizeMemory: true }))
			.pipe(plumber(function(err) {
				err =
					err.fileName
						? err.fileName.replace(workingDir, '') + '\n  line ' + (
						err._babel === true
							? err.loc.line
							: err.lineNumber
					) + ': ' + err.message.replace(/^[/\\]/, '')
						.replace(err.fileName.replace(/\\/g, '/') + ': ', '')
						.replace(/\(\d+(:\d+)?\)$/, '')
						: err;
				JSL.error(err);
				this.emit('end');
			}))
			.pipe(babel({
				presets: ['env'],
				plugins: [
					'transform-react-jsx',
					'transform-object-rest-spread'
				],
			}))
			.pipe(uglify({
				output: {
					comments: function(_, comment) {
						return /^!/m.test(comment.value)
					},
				},
			}))
			.pipe(appendMinSuffix())
			.pipe(gulp.dest('public/js/min'));
	});

	gulp.task('default', gulp.parallel('js', 'scss'));

	gulp.task('watch', gulp.series('default', done => {
		gulp.watch(JSWatchArray, { debounceDelay: 2000 }, gulp.series('js'));
		JSL.log('File watcher active');
		gulp.watch(SASSWatchArray, { debounceDelay: 2000 }, gulp.series('scss'));
		SASSL.log('File watcher active');
		done();
	}));

})();
