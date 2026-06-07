import { isPlatformBrowser, isPlatformServer } from '@angular/common';
import { Component, computed, DestroyRef, effect, inject, makeStateKey, PLATFORM_ID, signal, TransferState } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, of } from 'rxjs';

import { CommandResult } from '@app/core/api/api.models';
import { AnalyticsService } from '@app/core/analytics/analytics.service';
import { AuthService } from '@app/core/auth/auth.service';
import { CatalogService } from '@app/core/catalog/catalog.service';
import { FavoriteService } from '@app/core/favorites/favorite.service';
import { SeoService } from '@app/core/seo/seo.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiCodeBlockComponent } from '@app/shared/ui/code-block/ui-code-block.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiSkeletonComponent } from '@app/shared/ui/skeleton/ui-skeleton.component';

@Component({
  selector: 'app-command-page',
  standalone: true,
  imports: [TranslocoPipe, UiBadgeComponent, UiButtonComponent, UiCodeBlockComponent, UiEmptyStateComponent, UiIconComponent, UiSkeletonComponent],
  template: `
    <section class="grid gap-6">
      @if (loading()) {
        <app-ui-skeleton height="24rem" />
      } @else if (error()) {
        <app-ui-empty-state icon="terminal" [title]="'command.errorTitle' | transloco" [description]="'command.errorDescription' | transloco" />
      } @else {
        @if (command(); as item) {
          <header class="grid gap-4 rounded-lg border border-white/10 bg-surface-dark/72 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="grid gap-2">
                <div class="flex flex-wrap gap-2">
                  <app-ui-badge tone="cyan">{{ item.plugin?.name ?? ('command.unknownPlugin' | transloco) }}</app-ui-badge>
                  @if (item.category) {
                    <app-ui-badge tone="purple">{{ item.category.name }}</app-ui-badge>
                  }
                  <app-ui-badge tone="neutral">{{ 'command.views' | transloco: { count: item.views } }}</app-ui-badge>
                </div>
                <h1 class="font-display text-3xl font-semibold text-white">{{ item.name }}</h1>
                <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ item.description ?? ('command.noDescription' | transloco) }}</p>
              </div>
              <app-ui-button variant="secondary" (click)="toggleFavorite(item)">
                <app-ui-icon name="star" [size]="16" />
                {{ favoriteLabel(item.id) | transloco }}
              </app-ui-button>
            </div>
            <app-ui-code-block [code]="item.syntax" />
          </header>

          <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-lg border border-white/10 bg-surface-dark/72 p-5">
              <h2 class="font-display text-lg font-semibold text-white">{{ 'command.aliasesTitle' | transloco }}</h2>
              <div class="mt-4 flex flex-wrap gap-2">
                @for (alias of item.aliases; track alias) {
                  <app-ui-badge tone="neutral">{{ alias }}</app-ui-badge>
                } @empty {
                  <span class="text-sm text-neutral-gray">{{ 'command.aliasesEmpty' | transloco }}</span>
                }
              </div>
            </section>

            <section class="rounded-lg border border-white/10 bg-surface-dark/72 p-5">
              <h2 class="font-display text-lg font-semibold text-white">{{ 'command.parametersTitle' | transloco }}</h2>
              <div class="mt-4 grid gap-2">
                @for (parameter of parameterRows(); track parameter) {
                  <code class="rounded-md border border-white/10 bg-deep-space px-3 py-2 text-sm text-neutral-gray">{{ parameter }}</code>
                } @empty {
                  <span class="text-sm text-neutral-gray">{{ 'command.parametersEmpty' | transloco }}</span>
                }
              </div>
            </section>
          </div>
        }
      }
    </section>
  `,
})
export class CommandPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly auth = inject(AuthService);
  private readonly catalog = inject(CatalogService);
  private readonly analytics = inject(AnalyticsService);
  private readonly favorites = inject(FavoriteService);
  private readonly seo = inject(SeoService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly transferState = inject(TransferState);
  private readonly params = toSignal(this.route.paramMap);

  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly command = signal<CommandResult | null>(null);
  protected readonly slug = computed(() => this.params()?.get('slug') ?? '');
  protected readonly parameterRows = computed(() => {
    const parameters = this.command()?.parameters;

    if (Array.isArray(parameters)) {
      return parameters.map((parameter) => JSON.stringify(parameter));
    }

    if (parameters !== null && typeof parameters === 'object') {
      return Object.entries(parameters).map(([name, parameter]) => `${name}: ${JSON.stringify(parameter)}`);
    }

    return [];
  });

  constructor() {
    if (this.auth.isAuthenticated()) {
      this.favorites.load().pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
    }

    effect(() => this.load(this.slug()));
  }

  toggleFavorite(command: CommandResult): void {
    const target = { type: 'command' as const, id: command.id };

    if (this.favorites.isFavorite(target)) {
      this.favorites.remove(target).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
      return;
    }

    this.favorites.add(target).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
  }

  favoriteLabel(commandId: number): string {
    return this.favorites.isFavorite({ type: 'command', id: commandId }) ? 'favorites.remove' : 'favorites.add';
  }

  private load(slug: string): void {
    if (slug.length === 0) {
      return;
    }

    const stateKey = makeStateKey<CommandResult>(`command:${slug}`);

    if (isPlatformBrowser(this.platformId) && this.transferState.hasKey(stateKey)) {
      const command = this.transferState.get(stateKey, null);

      if (command !== null) {
        this.transferState.remove(stateKey);
        this.loading.set(false);
        this.error.set(false);
        this.applyCommand(command);

        return;
      }
    }

    this.loading.set(true);
    this.error.set(false);

    this.catalog.command(slug).pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => {
        this.error.set(true);
        return of(null);
      }),
    ).subscribe((response) => {
      this.loading.set(false);

      if (response === null) {
        return;
      }

      if (isPlatformServer(this.platformId)) {
        this.transferState.set(stateKey, response.data);
      }

      this.applyCommand(response.data);
    });
  }

  private applyCommand(command: CommandResult): void {
    this.command.set(command);
    this.seo.update({
      title: `${command.name} - Comando CommandSphere`,
      description: command.description ?? `Sintaxe e parametros do comando ${command.name}.`,
      canonicalPath: `/commands/${command.slug}`,
      type: 'article',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'SoftwareSourceCode',
        name: command.name,
        description: command.description ?? command.syntax,
        programmingLanguage: 'Command',
      },
    });

    if (this.auth.isAuthenticated()) {
      this.analytics.recordCommandView(command.slug).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
    }
  }
}
