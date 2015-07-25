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

var _sep = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~';
console.writeLine('Process awoken');
console.writeLine('> So you really wanna do this whole not-letting-me-sleep thing, huh?');
console.writeLine('> Fiiiine, lemme just... *yaaawn*');
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
];
console.write('>');
for (var i= 0,l=stuff.length;i<l;i++){
	var v = stuff[i];
	global[v.replace(/^gulp-([a-z]+).*$/, '$1')] = require(v);
	console.write(' '+v);
}
console.writeLine("\n> Huh? What? I'm pancake!   ...I-I mean, awake.\n"+_sep);

var workingDir = __dirname, ready2go = false;

function Personality(prompt, onerror, sayings){
	if (typeof sayings !== 'object' || typeof sayings.length !== 'number' )
		sayings = false;
	if (typeof sayings !== 'object' || typeof sayings.length !== 'number' )
		sayings = false;
	var $p = '['+prompt+'] ';
	this.log = function(message){
		if (message === true && sayings)
			message = sayings[Math.floor(Math.random()*sayings.length)];
		console.writeLine($p+message);
	};
	this.error = function(message){
		console.error((onerror?$p+onerror+'\n':'')+$p+message.trim());
	};
	return this;
}

var Flutters = new Personality(
	'gulp:sass',
	"I don't mean to interrupt, but I found a tiny little issue",
	[
		'I can do this. I believe in myself!',
		'Babysteps, everypony, babysteps.',
		"This won't take long, I promise!",
	]
);
gulp.task('sass', function() {
	if (ready2go) Flutters.log(true);
	gulp.src('www/sass/*.scss')
		.pipe(plumber(function(err){
			Flutters.error(err.messageFormatted);
			this.emit('end');
		}))
        .pipe(sourcemaps.init())
			.pipe(sass({
				outputStyle: 'expanded',
				errLogToConsole: true,
			}))
	        .pipe(autoprefixer('last 2 version'))
            .pipe(rename({suffix: '.min' }))
	        .pipe(minify())
        .pipe(sourcemaps.write('.'))
		.pipe(gulp.dest('www/css'));
});

var Dashie = new Personality(
	'gulp:js',
	'OH COME ON!',
	[
		'I just love doing this!',
		'Compiling in 10 millisecond flat!',
		"They don't call me the fastest flyer for no reason!",
		"Come on, this one's easy...",
		"There's no way I'm failig on this one",
	]
);
gulp.task('js', function(){
	if (ready2go) Dashie.log(true);
    gulp.src(['www/js/*.js', '!www/js/*.min.js'])
		.pipe(plumber(function(err){
			Dashie.error(
				err.fileName.replace(workingDir,'')+'\n  line '+err.lineNumber+': '+
				err.message.replace(/^[\/\\]/,'').replace(err.fileName+': ','')
			);
			this.emit('end');
		}))
        .pipe(sourcemaps.init())
		    .pipe(uglify())
		    .pipe(rename({suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
	    .pipe(gulp.dest('www/js'));
});

var Spike = new Personality('gulp:asssistant');
gulp.task('default', ['sass', 'js'], function(){
	Spike.log('Hello, I have compiled your SASS and minified your JS code.');
	Spike.log("Let me know if you need anything, I'll be in the kitchen eating Ruby gems");

	ready2go = true;

	Dashie.log("Thanks pal, we'll take it from here! Have a feast");
	Flutters.log("*gulp* hi");
	gulp.watch('www/sass/*.scss', {debounceDelay: 2000}, ['sass']);
	Dashie.log("Come on, Fluttershy! Be more assertive!");
	gulp.watch(['www/js/*.js', '!www/js/*.min.js'], {debounceDelay: 2000}, ['js']);
	Flutters.log("Hello?");
	Dashie.log("Close enough.");
});
