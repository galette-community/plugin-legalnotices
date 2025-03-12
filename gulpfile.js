import { src, dest, series } from 'gulp';
import { deleteAsync } from 'del';

const paths = {
    webroot: './webroot',
    js: './webroot/js',
    css: './webroot/klaro.min.css',
    klaro: {
        js: './node_modules/klaro/dist/klaro-no-css.js',
        css: './node_modules/klaro/dist/klaro.min.css'
    }
}

export const clean = () => deleteAsync([paths.js, paths.css]);

export function scripts() {
    return src(paths.klaro.js)
      .pipe(dest(paths.js));
};

export function styles() {
    return src(paths.klaro.css)
      .pipe(dest(paths.webroot));
};

const build = series(clean, scripts, styles);
export default build;
