import { src, dest, series } from 'gulp';
import { deleteAsync } from 'del';

const paths = {
    js: './webroot/js',
    klarojs: './node_modules/klaro/dist/klaro.js'
}

export const clean = () => deleteAsync(paths.js);

export function scripts() {
    return src(paths.klarojs)
      .pipe(dest(paths.js));
};

const build = series(clean, scripts);
export default build;
