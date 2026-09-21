import { isPrerenderedHtmlRequestPath, normalizePrerenderedHtmlPath } from './prerendered-html';

describe('isPrerenderedHtmlRequestPath', () => {
  it('matches .html paths regardless of case', () => {
    expect(isPrerenderedHtmlRequestPath('/index.html')).toBe(true);
    expect(isPrerenderedHtmlRequestPath('/INDEX.HTML')).toBe(true);
    expect(isPrerenderedHtmlRequestPath('/Index.Html')).toBe(true);
    expect(isPrerenderedHtmlRequestPath('/admin/ingestions/INDEX.HTML')).toBe(true);
  });

  it('does not match non-.html paths', () => {
    expect(isPrerenderedHtmlRequestPath('/search')).toBe(false);
    expect(isPrerenderedHtmlRequestPath('/runtime-config.js')).toBe(false);
    expect(isPrerenderedHtmlRequestPath('/main-ABC123.js')).toBe(false);
  });
});

describe('normalizePrerenderedHtmlPath', () => {
  it('maps the root prerendered file to /', () => {
    expect(normalizePrerenderedHtmlPath('/index.html')).toBe('/');
  });

  it('maps the CSR fallback file to /', () => {
    expect(normalizePrerenderedHtmlPath('/index.csr.html')).toBe('/');
  });

  it('maps a nested prerendered route file to its directory route', () => {
    expect(normalizePrerenderedHtmlPath('/search/index.html')).toBe('/search/');
    expect(normalizePrerenderedHtmlPath('/admin/ingestions/index.html')).toBe('/admin/ingestions/');
  });

  it('preserves a query string when normalizing', () => {
    expect(normalizePrerenderedHtmlPath('/index.html?ref=email')).toBe('/?ref=email');
    expect(normalizePrerenderedHtmlPath('/search/index.html?q=balance')).toBe('/search/?q=balance');
  });

  it('leaves non-.html paths untouched', () => {
    expect(normalizePrerenderedHtmlPath('/search')).toBe('/search');
    expect(normalizePrerenderedHtmlPath('/c/celem-ecosystem')).toBe('/c/celem-ecosystem');
    expect(normalizePrerenderedHtmlPath('/runtime-config.js')).toBe('/runtime-config.js');
  });

  it('normalizes the root prerendered file regardless of case', () => {
    expect(normalizePrerenderedHtmlPath('/INDEX.HTML')).toBe('/');
    expect(normalizePrerenderedHtmlPath('/Index.Html')).toBe('/');
  });

  it('normalizes a nested prerendered route file regardless of case', () => {
    expect(normalizePrerenderedHtmlPath('/Search/Index.Html')).toBe('/Search/');
  });
});
