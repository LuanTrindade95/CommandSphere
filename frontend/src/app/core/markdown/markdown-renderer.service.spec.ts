import { SecurityContext } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { TestBed } from '@angular/core/testing';

import { MarkdownRendererService } from './markdown-renderer.service';

describe('MarkdownRendererService', () => {
  let service: MarkdownRendererService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(MarkdownRendererService);
  });

  it('renders structured markdown html with headings, highlighted code blocks, and sanitization', () => {
    const warn = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
    const sanitizer = TestBed.inject(DomSanitizer);
    const rendered = service.render(`
      <h2>Commands</h2>
      <script>alert('xss')</script>
      <p>Use the command below.</p>
      <pre><code>/blood balance &lt;player&gt; --verbose</code></pre>
    `);
    const html = sanitizer.sanitize(SecurityContext.HTML, rendered.html) ?? '';

    expect(rendered.headings).toEqual([{ id: 'commands', level: 2, text: 'Commands' }]);
    expect(html).toContain('id="commands"');
    expect(html).toContain('text-neon-cyan');
    expect(html).not.toContain('<script>');
    warn.mockRestore();
  });
});
