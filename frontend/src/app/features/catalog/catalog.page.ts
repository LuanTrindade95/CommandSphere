import { Component, computed, DestroyRef, effect, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, forkJoin, of } from 'rxjs';

import { CommunityResource, PluginResource } from '@app/core/api/api.models';
import { CatalogService } from '@app/core/catalog/catalog.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiCardComponent } from '@app/shared/ui/card/ui-card.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiInputComponent } from '@app/shared/ui/input/ui-input.component';
import { UiSkeletonComponent } from '@app/shared/ui/skeleton/ui-skeleton.component';

@Component({
  selector: 'app-catalog-page',
  standalone: true,
  imports: [RouterLink, TranslocoPipe, UiBadgeComponent, UiCardComponent, UiEmptyStateComponent, UiIconComponent, UiInputComponent, UiSkeletonComponent],
  template: `
    <section class="grid gap-6">
      <div class="grid gap-3 lg:grid-cols-[1fr_22rem] lg:items-end">
        <div class="grid gap-2">
          <app-ui-badge tone="cyan">{{ 'catalog.badge' | transloco }}</app-ui-badge>
          <h1 class="font-display text-3xl font-semibold text-white">{{ community()?.name ?? ('catalog.titleFallback' | transloco) }}</h1>
          <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ community()?.description ?? ('catalog.descriptionFallback' | transloco) }}</p>
        </div>
        <app-ui-input
          [label]="'catalog.searchLabel' | transloco"
          [placeholder]="'catalog.searchPlaceholder' | transloco"
          [value]="query()"
          (valueChange)="query.set($event)"
        />
      </div>

      @if (loading()) {
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          @for (item of skeletonItems; track item) {
            <app-ui-card>
              <div class="grid gap-4">
                <app-ui-skeleton width="70%" height="1.25rem" />
                <app-ui-skeleton height="4rem" />
                <app-ui-skeleton width="45%" height="1.75rem" />
              </div>
            </app-ui-card>
          }
        </div>
      } @else if (error()) {
        <app-ui-empty-state icon="terminal" [title]="'catalog.errorTitle' | transloco" [description]="'catalog.errorDescription' | transloco" />
      } @else if (filteredPlugins().length === 0) {
        <app-ui-empty-state icon="search" [title]="'catalog.emptyTitle' | transloco" [description]="'catalog.emptyDescription' | transloco" />
      } @else {
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          @for (plugin of filteredPlugins(); track plugin.id) {
            <a class="group block rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-neon-cyan/70" [routerLink]="['/c', communitySlug(), 'p', plugin.slug]">
              <app-ui-card>
                <div class="grid min-h-52 gap-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="grid gap-1">
                      <h2 class="font-display text-lg font-semibold text-white group-hover:text-neon-cyan">{{ plugin.name }}</h2>
                      <p class="text-xs font-semibold uppercase tracking-normal text-neutral-gray">{{ plugin.slug }}</p>
                    </div>
                    <app-ui-icon class="text-soft-purple" name="terminal" [size]="20" />
                  </div>
                  <p class="line-clamp-3 text-sm leading-6 text-neutral-gray">{{ plugin.description ?? ('catalog.noDescription' | transloco) }}</p>
                  <div class="mt-auto flex flex-wrap gap-2">
                    <app-ui-badge tone="purple">{{ plugin.default_branch }}</app-ui-badge>
                    <app-ui-badge tone="neutral">{{ 'catalog.versionCount' | transloco: { count: plugin.versions.length } }}</app-ui-badge>
                  </div>
                </div>
              </app-ui-card>
            </a>
          }
        </div>
      }
    </section>
  `,
})
export class CatalogPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly params = toSignal(this.route.paramMap);

  protected readonly skeletonItems = [1, 2, 3, 4, 5, 6];
  protected readonly query = signal('');
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly community = signal<CommunityResource | null>(null);
  protected readonly plugins = signal<PluginResource[]>([]);
  protected readonly communitySlug = computed(() => this.params()?.get('community') ?? '');
  protected readonly filteredPlugins = computed(() => {
    const term = this.query().trim().toLowerCase();

    if (term.length === 0) {
      return this.plugins();
    }

    return this.plugins().filter((plugin) => `${plugin.name} ${plugin.slug} ${plugin.description ?? ''}`.toLowerCase().includes(term));
  });

  constructor() {
    effect(() => this.load(this.communitySlug()));
  }

  private load(slug: string): void {
    if (slug.length === 0) {
      return;
    }

    this.loading.set(true);
    this.error.set(false);

    forkJoin({
      community: this.catalog.community(slug),
      plugins: this.catalog.plugins(slug),
    }).pipe(
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

      this.community.set(response.community.data);
      this.plugins.set(response.plugins.data);
    });
  }
}
