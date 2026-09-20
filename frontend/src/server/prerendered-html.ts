/**
 * Maps a request for a prerendered HTML file (`/index.html`,
 * `/search/index.html`, `/index.csr.html`) back to the SPA route Angular
 * actually knows about (`/`, `/search/`).
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

  if (path === '/index.html' || path === '/index.csr.html') {
    return query === undefined ? '/' : `/?${query}`;
  }

  if (path.endsWith('/index.html')) {
    const normalizedPath = path.slice(0, -'index.html'.length);

    return query === undefined ? normalizedPath : `${normalizedPath}?${query}`;
  }

  return originalUrl;
}
