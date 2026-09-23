import { Component, computed, inject, input } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { Activity, AlertTriangle, BookOpen, Check, ChevronRight, Command, Database, FileCode, FileText, GitBranch, Github, Globe2, LoaderCircle, LogIn, LogOut, LucideIconData, Search, Shield, ShieldCheck, Sparkles, Star, Terminal, User, X, Zap } from 'lucide-angular';

export type IconName =
  | 'activity'
  | 'alert-triangle'
  | 'book-open'
  | 'check'
  | 'chevron-right'
  | 'command'
  | 'database'
  | 'file-code'
  | 'file-text'
  | 'git-branch'
  | 'github'
  | 'globe-2'
  | 'loader-circle'
  | 'log-in'
  | 'log-out'
  | 'search'
  | 'shield'
  | 'shield-check'
  | 'sparkles'
  | 'star'
  | 'terminal'
  | 'user'
  | 'x'
  | 'zap';

const ICONS: Record<IconName, LucideIconData> = {
  activity: Activity,
  'alert-triangle': AlertTriangle,
  'book-open': BookOpen,
  check: Check,
  'chevron-right': ChevronRight,
  command: Command,
  database: Database,
  'file-code': FileCode,
  'file-text': FileText,
  'git-branch': GitBranch,
  github: Github,
  'globe-2': Globe2,
  'loader-circle': LoaderCircle,
  'log-in': LogIn,
  'log-out': LogOut,
  search: Search,
  shield: Shield,
  'shield-check': ShieldCheck,
  sparkles: Sparkles,
  star: Star,
  terminal: Terminal,
  user: User,
  x: X,
  zap: Zap,
};

/**
 * Fixed size -> Tailwind arbitrary-value class lookup for `app-ui-icon`.
 *
 * Every call site in this codebase passes a literal numeric `size` (see
 * `ui-icon.component.spec.ts` for the enforced set), so sizing is expressed
 * through static utility classes compiled into the stylesheet at build time
 * instead of an inline `style="width:...;height:...` attribute. This keeps
 * the CSP `style-src-attr` directive free of `unsafe-inline`: Angular
 * renders these classes as a plain `class` attribute, never as `style`.
 * Every class token below must appear literally so Tailwind's content
 * scanner generates it; add a new entry here before using a new size.
 */
const ICON_SIZE_CLASSES: Record<number, string> = {
  14: 'w-[14px] h-[14px]',
  15: 'w-[15px] h-[15px]',
  16: 'w-[16px] h-[16px]',
  17: 'w-[17px] h-[17px]',
  18: 'w-[18px] h-[18px]',
  20: 'w-[20px] h-[20px]',
  22: 'w-[22px] h-[22px]',
  24: 'w-[24px] h-[24px]',
};

const DEFAULT_ICON_SIZE = 18;

@Component({
  selector: 'app-ui-icon',
  standalone: true,
  template: `
    <span
      class="inline-grid shrink-0 place-items-center"
      [class]="sizeClass()"
      [class.animate-spin]="spin()"
      [innerHTML]="svgContent()"
    ></span>
  `,
})
export class UiIconComponent {
  readonly name = input.required<IconName>();
  readonly size = input(DEFAULT_ICON_SIZE);
  readonly spin = input(false);

  private readonly sanitizer = inject(DomSanitizer);

  readonly sizeClass = computed(() => ICON_SIZE_CLASSES[this.size()] ?? ICON_SIZE_CLASSES[DEFAULT_ICON_SIZE]);

  readonly svgContent = computed<SafeHtml>(() => {
    const icon = ICONS[this.name()];
    const children = icon
      .map(([tag, attributes]) => `<${tag} ${Object.entries(attributes)
        .map(([key, value]) => `${key}="${String(value)}"`)
        .join(' ')}></${tag}>`)
      .join('');
    const svg = `<svg aria-hidden="true" class="block shrink-0" width="${this.size()}" height="${this.size()}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${children}</svg>`;

    return this.sanitizer.bypassSecurityTrustHtml(svg);
  });
}
