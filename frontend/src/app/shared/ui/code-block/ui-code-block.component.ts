import { Component, computed, inject, input } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

@Component({
  selector: 'app-ui-code-block',
  standalone: true,
  template: `
    <pre class="overflow-x-auto rounded-lg border border-white/10 bg-deep-space px-4 py-3 text-sm leading-7 text-slate-100"><code [innerHTML]="highlightedCode()"></code></pre>
  `,
})
export class UiCodeBlockComponent {
  readonly code = input.required<string>();
  private readonly sanitizer = inject(DomSanitizer);

  readonly highlightedCode = computed<SafeHtml>(() => {
    const escaped = this.code()
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replace(/^\/([\w:-]+)/gm, '<span class="text-neon-cyan">/$1</span>')
      .replace(/(--[\w-]+)/g, '<span class="text-soft-purple">$1</span>')
      .replace(/(&lt;[\w:-]+&gt;)/g, '<span class="text-warning">$1</span>');

    return this.sanitizer.bypassSecurityTrustHtml(escaped);
  });
}
