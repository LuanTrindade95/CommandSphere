/**
 * True for any request path ending in `.html`, matched case-insensitively.
 *
 * Windows' filesystem is case-insensitive, so `express.static` would still
 * resolve and serve the on-disk `index.html` for a request to `/INDEX.HTML`
 * or `/Index.Html` there, bypassing the `renderAngular` detour below on a
 * Windows host even though the comparison itself is case-sensitive. This
 * does not happen on the case-sensitive Linux filesystem production runs on,
 * but the check is case-insensitive everywhere so the behavior never depends
 * on the host OS.
 */
export function isPrerenderedHtmlRequestPath(path: string): boolean {
  return path.toLowerCase().endsWith('.html');
}

/**
 * Maps a request for a prerendered HTML file (`/index.html`,
 * `/search/index.html`, `/index.csr.html`, matched case-insensitively) back
 * to the SPA route Angular actually knows about (`/`, `/search/`).
 *
 * The Angular CLI prerenders several routes as static `.html` files on disk.
 * Those files must never be served as inert static assets: they are full,
 * hydratable documents that bootstrap `<app-root>` and load `main-*.js`, so
 * `server.ts` routes any `.html` request through `renderAngular` instead of
 * `express.static` to apply the same per-request CSP/nonce/no-store as every
 * other page. This function turns the `.html` request path into the route
 * path `renderAngular` needs to render the right page.
 */
export function normalizePrerenderedHtmlPath(originalUrl: string): string {
  const [path, query] = originalUrl.split('?');
  const lowerPath = path.toLowerCase();

  if (lowerPath === '/index.html' || lowerPath === '/index.csr.html') {
    return query === undefined ? '/' : `/?${query}`;
  }

  if (lowerPath.endsWith('/index.html')) {
    const normalizedPath = path.slice(0, path.length - 'index.html'.length);

    return query === undefined ? normalizedPath : `${normalizedPath}?${query}`;
  }

  return originalUrl;
}
