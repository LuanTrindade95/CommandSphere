import { Component, inject } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';

import { AuthService } from '@app/core/auth/auth.service';
import { LanguageService, SupportedLanguage } from '@app/core/i18n/language.service';
import { LoadingService } from '@app/core/loading/loading.service';
import { ShellDataService } from '@app/core/shell-data/shell-data.service';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { CommandPaletteComponent } from '@app/shared/ui/command-palette/command-palette.component';
import { CommandPaletteService } from '@app/shared/ui/command-palette/command-palette.service';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiToastContainerComponent } from '@app/shared/ui/toast/ui-toast-container.component';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [CommandPaletteComponent, RouterLink, RouterOutlet, TranslocoPipe, UiButtonComponent, UiIconComponent, UiToastContainerComponent],
  template: `
    <main class="min-h-screen overflow-x-hidden bg-deep-space text-slate-100">
      <div class="mx-auto grid min-h-screen w-full min-w-0 max-w-7xl grid-rows-[auto_1fr] px-4 sm:px-6">
        <header class="sticky top-0 z-30 min-w-0 border-b border-white/10 bg-deep-space/92 py-3 backdrop-blur">
          <div class="grid min-w-0 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3">
            <a class="grid min-w-0 grid-cols-[auto_1fr] items-center gap-3" routerLink="/">
              <span class="grid h-9 w-9 place-items-center rounded-md bg-electric-purple text-white shadow-command-glow">
                <app-ui-icon name="sparkles" [size]="18" />
              </span>
              <span class="hidden min-w-0 sm:block">
                <span class="block truncate font-display text-base font-semibold text-white">{{ 'app.name' | transloco }}</span>
                <span class="block truncate text-xs font-medium text-neutral-gray">{{ 'shell.foundation' | transloco }}</span>
              </span>
            </a>

            <button
              type="button"
              class="grid h-10 min-w-0 grid-cols-[auto_1fr_auto] items-center gap-3 rounded-md border border-white/10 bg-surface-dark/72 px-3 text-left text-sm text-neutral-gray transition hover:border-neon-cyan/40 hover:bg-neon-cyan/10"
              [attr.aria-label]="'shell.searchAria' | transloco"
              (click)="palette.open()"
            >
              <app-ui-icon name="search" [size]="17" />
              <span class="truncate">{{ 'shell.searchPlaceholder' | transloco }}</span>
              <kbd class="hidden rounded border border-white/10 bg-white/6 px-2 py-0.5 text-xs font-semibold text-neutral-gray md:block">
                {{ 'shell.searchShortcut' | transloco }}
              </kbd>
            </button>

            <div class="flex min-w-0 items-center justify-end gap-2">
              @if (loading.isLoading()) {
                <app-ui-icon class="text-neon-cyan" name="loader-circle" [size]="18" [spin]="true" />
              }

              <label class="sr-only" for="language">{{ 'shell.languageLabel' | transloco }}</label>
              <select
                id="language"
                class="h-10 rounded-md border border-white/10 bg-surface-dark px-2 text-xs font-semibold text-slate-100 outline-none focus:border-neon-cyan/60"
                [value]="language.activeLanguage()"
                (change)="setLanguage($event)"
              >
                @for (lang of language.languages; track lang) {
                  <option [value]="lang">{{ lang }}</option>
                }
              </select>

              @if (auth.isAuthenticated()) {
                <app-ui-button variant="ghost" [ariaLabel]="'auth.logout' | transloco" (click)="auth.logout().subscribe()">
                  <app-ui-icon name="log-out" [size]="17" />
                  <span class="hidden lg:inline">{{ auth.user()?.username ?? auth.user()?.name }}</span>
                </app-ui-button>
              } @else {
                <app-ui-button variant="secondary" routerLink="/login" [ariaLabel]="'auth.login' | transloco">
                  <app-ui-icon name="log-in" [size]="17" />
                  <span class="hidden lg:inline">{{ 'auth.login' | transloco }}</span>
                </app-ui-button>
              }
            </div>
          </div>

          <nav class="mt-3 flex min-h-9 max-w-full gap-2 overflow-x-auto">
            @if (auth.communities().length > 0) {
              @for (community of auth.communities(); track community.slug) {
                <a
                  class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200"
                  [routerLink]="['/c', community.slug]"
                >
                  <app-ui-icon name="shield" [size]="14" />
                  {{ community.name }}
                </a>
              }
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/search">
                {{ 'shell.nav.search' | transloco }}
              </a>
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/favorites">
                {{ 'shell.nav.favorites' | transloco }}
              </a>
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/analytics">
                {{ 'shell.nav.analytics' | transloco }}
              </a>
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/admin/plugins">
                {{ 'shell.nav.adminPlugins' | transloco }}
              </a>
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/admin/ingestions">
                {{ 'shell.nav.adminIngestions' | transloco }}
              </a>
            } @else {
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/c/celem-ecosystem">
                {{ 'shell.nav.catalog' | transloco }}
              </a>
              <a class="inline-flex h-9 shrink-0 items-center rounded-md border border-white/10 bg-white/5 px-3 text-xs font-semibold text-slate-200" routerLink="/search">
                {{ 'shell.nav.search' | transloco }}
              </a>
            }
          </nav>
        </header>

        <section class="py-6">
          <router-outlet />
        </section>
      </div>

      <app-command-palette />
      <app-ui-toast-container />
    </main>
  `,
})
export class AppShellComponent {
  protected readonly auth = inject(AuthService);
  protected readonly language = inject(LanguageService);
  protected readonly loading = inject(LoadingService);
  protected readonly palette = inject(CommandPaletteService);
  protected readonly publicData = inject(ShellDataService).readPublicData();

  setLanguage(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLSelectElement) {
      this.language.setLanguage(target.value as SupportedLanguage);
    }
  }
}
