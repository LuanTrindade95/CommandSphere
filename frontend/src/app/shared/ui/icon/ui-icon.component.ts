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

@Component({
  selector: 'app-ui-icon',
  standalone: true,
  template: `
    <span
      class="inline-grid shrink-0 place-items-center"
      [class.animate-spin]="spin()"
      [style.width.px]="size()"
      [style.height.px]="size()"
      [innerHTML]="svgContent()"
    ></span>
  `,
})
export class UiIconComponent {
  readonly name = input.required<IconName>();
  readonly size = input(18);
  readonly spin = input(false);

  private readonly sanitizer = inject(DomSanitizer);

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
