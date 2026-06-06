import { SecurityContext, inject, Injectable } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

export interface MarkdownHeading {
  id: string;
  level: 2 | 3;
  text: string;
}

export interface RenderedMarkdown {
  html: SafeHtml;
  headings: MarkdownHeading[];
}

@Injectable({ providedIn: 'root' })
export class MarkdownRendererService {
  private readonly sanitizer = inject(DomSanitizer);

  render(contentHtml: string): RenderedMarkdown {
    const sanitized = this.sanitizer.sanitize(SecurityContext.HTML, contentHtml) ?? '';
    const headings: MarkdownHeading[] = [];
    const withHeadingIds = sanitized.replace(/<h([23])([^>]*)>(.*?)<\/h\1>/gis, (_match, level: string, attributes: string, innerHtml: string) => {
      const text = this.stripTags(innerHtml).trim();
      const id = this.uniqueSlug(text, headings);
      headings.push({ id, level: level === '2' ? 2 : 3, text });

      return `<h${level}${attributes} id="${id}" class="scroll-mt-24">${innerHtml}</h${level}>`;
    });
    const enhanced = withHeadingIds.replace(/<pre><code([^>]*)>([\s\S]*?)<\/code><\/pre>/gi, (_match, attributes: string, code: string) => (
      `<pre class="overflow-x-auto rounded-lg border border-white/10 bg-deep-space px-4 py-3 text-sm leading-7 text-slate-100"><code${attributes}>${this.highlightCommandSyntax(code)}</code></pre>`
    ));

    return {
      html: this.sanitizer.bypassSecurityTrustHtml(enhanced),
      headings,
    };
  }

  private uniqueSlug(text: string, headings: MarkdownHeading[]): string {
    const base = text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '') || 'section';
    const existing = new Set(headings.map((heading) => heading.id));
    let candidate = base;
    let suffix = 2;

    while (existing.has(candidate)) {
      candidate = `${base}-${suffix}`;
      suffix += 1;
    }

    return candidate;
  }

  private stripTags(html: string): string {
    return html.replace(/<[^>]*>/g, '');
  }

  private highlightCommandSyntax(code: string): string {
    return code
      .replace(/^\/([\w:-]+)/gm, '<span class="text-neon-cyan">/$1</span>')
      .replace(/(--[\w-]+)/g, '<span class="text-soft-purple">$1</span>')
      .replace(/(&lt;[\w:-]+&gt;)/g, '<span class="text-warning">$1</span>');
  }
}
