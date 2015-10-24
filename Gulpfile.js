var cl = console.log;
console.log = console.writeLine = function () {
	var args = [].slice.call(arguments);
	if (args.length && /^(\[\d{2}:\d{2}:\d{2}]|Using|Starting|Finished)/.test(args[0]))
		return;
	return cl.apply(console, args);
};
var stdoutw = process.stdout.write;
process.stdout.write = console.write = function(str){
	var out = [].slice.call(arguments).join(' ');
	if (/\[.*\d.*]/g.test(out)) return;
	stdoutw.call(process.stdout, out);
};

var _sep = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';
console.writeLine('Gulp process awoken. It still appears to be tired.');
var stuff = [
	'gulp',
	'gulp-sourcemaps',
	'gulp-autoprefixer',
	'gulp-minify-css',
	'gulp-rename',
	'gulp-sass',
	'gulp-uglify',
	'gulp-plumber',
	'gulp-util',
	'gulp-markdown',
	'gulp-dom',
];
console.write('> *yaaawn*');
for (var i= 0,l=stuff.length;i<l;i++){
	var v = stuff[i];
	global[v.replace(/^gulp-([a-z]+).*$/, '$1')] = require(v);
	console.write(' '+v);
}
console.writeLine("\n> Huh? What? I'm pancake!   ...I-I mean, awake.\n"+_sep);

var workingDir = __dirname, ready2go = false;

function Personality(prompt, onerror){
	if (typeof onerror !== 'object' || typeof onerror.length !== 'number' )
		onerror = false;
	var $p = '['+prompt+'] ';
	this.log = function(message){
		console.writeLine($p+message);
	};
	var getErrorMessage = function(){
		return onerror[Math.floor(Math.random()*onerror.length)];
	};
	this.error = function(message){
		if (typeof message === 'string') message = message.trim();
		else console.log(message);
		console.error((onerror?$p+getErrorMessage()+'\n':'')+$p+message);
	};
	return this;
}

var Flutters = new Personality(
	'sass',
	[
		"I don't mean to interrupt, but I found a tiny little issue",
		"This doesn't seem good",
		"Ouch",
	]
);
gulp.task('sass', function() {
	gulp.src('www/sass/*.scss')
		.pipe(plumber(function(err){
			Flutters.error(err.messageFormatted || err);
			this.emit('end');
		}))
		.pipe(sourcemaps.init())
			.pipe(sass({
				outputStyle: 'expanded',
				errLogToConsole: true,
			}))
			.pipe(autoprefixer('last 2 version'))
			.pipe(rename({suffix: '.min' }))
			.pipe(minify({
				processImport: false,
				compatibility: '-units.pc,-units.pt'
			}))
		.pipe(sourcemaps.write('.', {
			includeContent: false,
			sourceRoot: '/sass',
		}))
		.pipe(gulp.dest('www/css'));
});

var Dashie = new Personality(
	'js',
	[
		'OH COME ON!',
		'Not this again!',
		'Why does it have to be me?',
		"This isn't fun at all",
		"...seriously?",
	]
);
gulp.task('js', function(){
	gulp.src(['www/js/*.js', '!www/js/*.min.js'])
		.pipe(plumber(function(err){
			err =
				err.fileName
				? err.fileName.replace(workingDir,'')+'\n  line '+err.lineNumber+': '+err.message.replace(/^[\/\\]/,'').replace(err.fileName+': ','')
				: err;
			Dashie.error(err);
			this.emit('end');
		}))
		.pipe(sourcemaps.init())
			.pipe(uglify())
			.pipe(rename({suffix: '.min' }))
		.pipe(sourcemaps.write('.', {
			includeContent: false,
			sourceRoot: '/js',
		}))
		.pipe(gulp.dest('www/js'));
});

var AJ = new Personality(
	'md',
	[
		'Awe, shucks!',
		'Stay calm sugarcube',
		'Ah seem to have a lil\' problem',
	]
);
gulp.task('md', function(){
	gulp.src('README.md')
		.pipe(plumber(function(err){
			AJ.error(err);
			this.emit('end');
		}))
		.pipe(markdown())
		.pipe(dom(function(){
			var document = this,
				el = document.getElementById('attributions'),
				newElements = "";

			while (el.nextElementSibling !== null && el.nextElementSibling.nodeName.toLowerCase() !== 'h2'){
				newElements += el.nextElementSibling.outerHTML;
				el = el.nextElementSibling;
			}

			return newElements.replace(/\n/g,'');
		}))
		.pipe(rename('about.html'))
		.pipe(gulp.dest('www/views'));
});

gulp.task('default', ['js', 'sass', 'md'], function(){
	gulp.watch(['www/js/*.js', '!www/js/*.min.js'], {debounceDelay: 2000}, ['js']);
	Dashie.log("I got my eyes on you, JavaScript files!");
	gulp.watch('www/sass/*.scss', {debounceDelay: 2000}, ['sass']);
	Flutters.log("SCSS files, do you mind if I, um, watch over you for a bit?");
	gulp.watch('README.md', {debounceDelay: 2000}, ['md']);
	AJ.log("Readme markdown file is under my radar, sugarcube");
});
