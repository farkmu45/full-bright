/**
 * Landing page media (images, GIFs, video) bundled through Vite.
 *
 * Files live in `resources/assets/` and are resolved with `import.meta.glob`
 * so Vite hashes, versions, and rewrites every URL at build time. Nothing is
 * served straight from `public/`, which keeps cache busting automatic.
 *
 * @see https://laravel.com/docs/vite#working-with-static-assets
 */
const bundled = import.meta.glob('../../assets/**/*', {
    eager: true,
    query: '?url',
    import: 'default',
}) as Record<string, string>;

const urls: Record<string, string> = {};

for (const [path, url] of Object.entries(bundled)) {
    urls[path.replace('../../assets/', '')] = url;
}

/**
 * Resolves a file name inside `resources/assets/` to its built URL.
 *
 * Unknown names resolve to an empty string so a missing file degrades to a
 * blank image instead of breaking the build or the page.
 */
export function asset(name: string): string {
    const url = urls[name];

    if (!url) {
        if (import.meta.env.DEV) {
            console.warn(`[assets] resources/assets/${name} not found`);
        }

        return '';
    }

    return url;
}
