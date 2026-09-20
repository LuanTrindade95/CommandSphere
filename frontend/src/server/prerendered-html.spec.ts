import { normalizePrerenderedHtmlPath } from './prerendered-html';

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
});
