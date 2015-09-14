var gulp       = require( 'gulp' );
var watch      = require( 'gulp-watch' );
var sass       = require( 'gulp-sass' );
var cssmin     = require( 'gulp-minify-css' );
var rename     = require( 'gulp-rename' );
var uglifyjs   = require( 'gulp-uglifyjs' );
var browserify = require( 'browserify' );
var source     = require( 'vinyl-source-stream' );

gulp.task( 'sass', function() {
	return gulp.src( './src/scss/*.scss' )
		.pipe( sass() )
		.pipe( gulp.dest( './' ) )
		.on( 'end', function() {
			gulp.src( ['./*.css', '!./*.min.css'] )
				.pipe( cssmin( {
					keepSpecialComments: 0
				} ) )
				.pipe( rename( { suffix: '.min' } ) )
				.pipe( gulp.dest( './' ) );
		} );
} );

gulp.task( 'watch', ['sass'], function() {
	gulp.watch( ['./src/scss/*.scss', './src/scss/*/*.scss'], ['sass'] );
} );
