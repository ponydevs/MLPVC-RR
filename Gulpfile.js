var gulp = require('gulp'),
	sourcemaps = require('gulp-sourcemaps'),
	autoprefixer = require('gulp-autoprefixer'),
	minify = require('gulp-minify-css'),
	rename = require('gulp-rename'),
	sass = require('gulp-sass'),
	uglify = require('gulp-uglify');

gulp.task('sass', function() {
	gulp.src('www/sass/*.scss')
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

gulp.task('js', function () {
    gulp.src(['www/js/*.js', '!www/js/*.min.js'])
        .pipe(sourcemaps.init())
		    .pipe(uglify())
		    .pipe(rename({suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
	    .pipe(gulp.dest('www/js'));
});

gulp.task('default', ['sass', 'js'], function(){
	gulp.watch('www/sass/*.scss', ['sass']);
	gulp.watch(['www/js/*.js', '!www/js/*.min.js'], ['js']);
});
