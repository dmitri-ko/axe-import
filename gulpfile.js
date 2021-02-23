'use strict';

const gulp = require('gulp'),
    watcher = require('gulp-watch'),
    uglify = require('gulp-uglify'),
    rigger = require('gulp-rigger'),
    imagemin = require('gulp-imagemin'),
    sourcemaps = require('gulp-sourcemaps'),
    sass = require('gulp-sass'),
    prefixer = require('gulp-autoprefixer'),
    del = require('del'),
    cssmin = require('gulp-clean-css'),
    plumber = require('gulp-plumber'),
    notify = require('gulp-notify'),
    pngquant = require('imagemin-pngquant'),
    concat = require('gulp-concat'),
    rename = require("gulp-rename"),
    replace = require("gulp-replace");

const path = {
    dest: '/Users/dmitri.kokorin/Library/Mobile Documents/com~apple~CloudDocs/MAMP/htdocs/wp/wp-content/plugins/axe-import',
    build: { //Тут мы укажем куда складывать готовые после сборки файлы
        js:    'build/assets/js/',
        css:   'build/assets/css/',
        img:   'build/assets/images/',
        fonts: 'build/assets/fonts/',
        php:  'build/',
        doc:    'build/Documents/',
    },
    src: { //Пути откуда брать исходники
        js: 'assets/js/*.js',//В стилях и скриптах нам понадобятся только main файлы
        img: 'assets/images/**/*.*', //Синтаксис img/**/*.* означает - взять все файлы всех расширений из папки и из вложенных каталогов
        style: 'assets/css/**/*.scss',
        fonts: 'assets/fonts/**/*.*',
        php: ['**/*.php','readme.*' ],
        doc: '/Documents/**/*.*',
        plugin: 'build/**/*.*',
    },    
    watch: { //Тут мы укажем, за изменением каких файлов мы хотим наблюдать
        js: 'assets/js/*.js',//В стилях и скриптах нам понадобятся только main файлы
        img: 'assets/images/**/*.*', //Синтаксис img/**/*.* означает - взять все файлы всех расширений из папки и из вложенных каталогов
        style: 'assets/css/**/*.scss',
        fonts: 'assets/fonts/**/*.*',
        php: ['**/*.php','readme.*' ],
        doc: '/Documents/**/*.*',
        plugin: 'build/**/*.*'
    },
    clean: './build'   

};

function js() {
    return gulp.src(path.src.js, { allowEmpty: true }) //Найдем наш main файл
        .pipe(plumber({
            errorHandler: function (err) {
                notify.onError({
                    title: "Gulp error in " + err.plugin,
                    message: err.toString()
                })(err);
            }
        }))
       // .pipe(concat(jsbuild + '.js'))
        .pipe(gulp.dest(path.build.js))
        .pipe(rigger()) //Прогоним через rigger
       // .pipe(sourcemaps.init()) //Инициализируем sourcemap
        .pipe(uglify()) //Сожмем наш js
      //  .pipe(sourcemaps.write()) //Пропишем карты
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(path.build.js)); //Выплюнем готовый файл в build   
}

function image() {
    return gulp.src(path.src.img) //Выберем наши картинки
        .pipe(plumber({
            errorHandler: function (err) {
                notify.onError({
                    title: "Gulp error in " + err.plugin,
                    message: err.toString()
                })(err);
            }
        }))
        .pipe(imagemin({ //Сожмем их
            progressive: true,
            svgoPlugins: [{ removeViewBox: false }],
            use: [pngquant()],
            interlaced: true
        }))
        .pipe(gulp.dest(path.build.img)); //И бросим в build
}

function style() {
    return gulp.src(path.src.style) //Выберем наш main.scss
        .pipe(plumber({
            errorHandler: function (err) {
                notify.onError({
                    title: "Gulp error in " + err.plugin,
                    message: err.toString()
                })(err);
            }
        }))
       // .pipe(sourcemaps.init()) //То же самое что и с js
        .pipe(sass()) //Скомпилируем
       // .pipe(concat('style.css'))
        .pipe(prefixer({
            cascade: false
        })) //Добавим вендорные префиксы
       // .pipe(replace(/^[ \t]*\@charset[ \t]+\"UTF\-8\"[ \t]*;/gmi, '' ))
        .pipe(gulp.dest(path.build.css))
        .pipe(cssmin()) 
       // .pipe(sourcemaps.write())
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest(path.build.css));
}

function fonts() {
    return gulp.src(path.src.fonts)
        .pipe(plumber({
            errorHandler: function (err) {
                notify.onError({
                    title: "Gulp error in " + err.plugin,
                    message: err.toString()
                })(err);
            }
        }))
        .pipe(gulp.dest(path.build.fonts));
}

function php() {
    return gulp.src(path.src.php, {base:"."})
        //.pipe(gulpFlatten({ includeParents: 1 }))
        .pipe(gulp.dest(path.build.php));
}

function doc() {
    return gulp.src(path.src.doc, {base:"."})
        //.pipe(gulpFlatten({ includeParents: 1 }))
        .pipe(gulp.dest(path.build.doc));
}

function copy() {
    return gulp.src(path.src.plugin)
        //.pipe(gulpFlatten({ includeParents: 1 }))
        .pipe(gulp.dest(path.dest));
}

function watch() {
    watcher(path.watch.plugin, copy);
    watcher(path.watch.js, js);
    watcher(path.watch.style, style);
    watcher(path.watch.img, image);
    watcher(path.watch.php, php);
    watcher(path.watch.doc, doc);
}

function clean() {
    return del([path.clean], {force:true});
}

const build = gulp.series(clean, gulp.parallel(php, doc, js, style, fonts, image), copy);
const all = gulp.series(build, watch);

exports.copy = copy;
exports.php = php;
exports.doc = doc;
exports.images = image;
exports.css = style;
exports.js = js;
exports.clean = clean;
exports.build = build;
exports.watch = watch;
exports.default = all;