'use strict';

const gulp = require('gulp'),
    watcher = require('gulp-watch'),
    del = require('del');

const path = {
    dest: '/Users/dmitri.kokorin/Library/Mobile Documents/com~apple~CloudDocs/MAMP/htdocs/wp/wp-content/plugins/axe-import',
    src: ['*', 'assets/**/*', 'Documents/**/*', 'lang/**/*',  'includes/**/*' ],
    watch:  '**/*',
    clean: '/Users/dmitri.kokorin/Library/Mobile Documents/com~apple~CloudDocs/MAMP/htdocs/wp/plugin/axe-import/assets/css'
};


function copy() {
    return gulp.src(path.src, {base:"."})
        //.pipe(gulpFlatten({ includeParents: 1 }))
        .pipe(gulp.dest(path.dest));
}

function watch() {
    watcher(path.watch, copy);
}

function clean() {
    return del([path.clean], {force:true});
}

const all = gulp.series(copy, watch);

exports.copy = copy;
exports.clean = clean;
exports.watch = watch;
exports.default = all;