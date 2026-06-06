import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { NgClass } from '@angular/common';
import { takeUntilDestroyed, toObservable, toSignal } from '@angular/core/rxjs-interop';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, debounceTime, of, switchMap, tap } from 'rxjs';

import { CommandResult, SearchResponse } from '@app/core/api/api.models';
import { SearchService } from '@app/core/search/search.service';
import { SearchUrlState, SearchUrlStateService } from '@app/core/search/search-url-state.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiInputComponent } from '@app/shared/ui/input/ui-input.component';

@Component({
  selector: 'app-search-page',
  standalone: true,
  imports: [NgClass, RouterLink, TranslocoPipe, UiBadgeComponent, UiEmptyStateComponent, UiInputComponent],
  template: `
    <section class="grid gap-6" (keydown)="handleKeydown($event)">
      <header class="grid gap-3">
        <app-ui-badge tone="cyan">{{ 'search.badge' | transloco }}</app-ui-badge>
        <h1 class="font-display text-3xl font-semibold text-white">{{ 'search.title' | transloco }}</h1>
        <app-ui-input
          [label]="'search.queryLabel' | transloco"
          [placeholder]="'search.queryPlaceholder' | transloco"
          [value]="state().q"
          (valueChange)="updateQuery($event)"
        />
      </header>

      <div class="grid gap-5 lg:grid-cols-[16rem_1fr]">
        <aside class="grid content-start gap-4">
          @for (facet of facetGroups(); track facet.key) {
            <section class="grid gap-2 rounded-lg border border-white/10 bg-surface-dark/72 p-4">
              <h2 class="text-xs font-semibold uppercase tracking-normal text-neutral-gray">{{ facet.label | transloco }}</h2>
              @for (value of facet.values; track value.name) {
                <button
                  type="button"
                  class="flex items-center justify-between rounded-md px-2 py-1.5 text-left text-sm transition hover:bg-white/6"
                  [class.text-neon-cyan]="state()[facet.key] === value.name"
                  [class.text-neutral-gray]="state()[facet.key] !== value.name"
                  (click)="toggleFacet(facet.key, value.name)"
                >
                  <span>{{ value.name }}</span>
                  <span class="text-xs">{{ value.count }}</span>
                </button>
              } @empty {
                <span class="text-sm text-neutral-gray">{{ 'search.noFacets' | transloco }}</span>
              }
            </section>
          }
        </aside>

        <section class="grid content-start gap-3">
          @if (loading()) {
            <div class="rounded-lg border border-white/10 bg-white/5 p-5 text-sm text-neutral-gray">{{ 'search.loading' | transloco }}</div>
          } @else if (results().length === 0) {
            <app-ui-empty-state icon="search" [title]="'search.emptyTitle' | transloco" [description]="'search.emptyDescription' | transloco" />
          } @else {
            <p class="text-sm text-neutral-gray">{{ 'search.resultCount' | transloco: { count: metaTotal() } }}</p>
            @for (result of results(); track result.id; let index = $index) {
              <a
                class="grid gap-2 rounded-lg border p-4 transition hover:border-neon-cyan/50 hover:bg-neon-cyan/8"
                [ngClass]="activeIndex() === index ? 'border-neon-cyan bg-neon-cyan/10' : 'border-white/10 bg-surface-dark/72'"
                [routerLink]="['/commands', result.slug]"
                (mouseenter)="activeIndex.set(index)"
              >
                <div class="flex flex-wrap items-center gap-2">
                  <app-ui-badge tone="cyan">{{ result.plugin?.name ?? ('search.unknownPlugin' | transloco) }}</app-ui-badge>
                  @if (result.category) {
                    <app-ui-badge tone="purple">{{ result.category.name }}</app-ui-badge>
                  }
                </div>
                <h2 class="font-display text-lg font-semibold text-white" [innerHTML]="highlight(result.name)"></h2>
                <p class="text-sm leading-6 text-neutral-gray" [innerHTML]="highlight(result.description ?? result.syntax)"></p>
                <code class="text-sm text-neon-cyan" [innerHTML]="highlight(result.syntax)"></code>
              </a>
            }
          }
        </section>
      </div>
    </section>
  `,
})
export class SearchPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly search = inject(SearchService);
  private readonly urlState = inject(SearchUrlStateService);
  private readonly sanitizer = inject(DomSanitizer);
  private readonly destroyRef = inject(DestroyRef);
  private readonly queryParams = toSignal(this.route.queryParams, { initialValue: {} });

  protected readonly state = computed(() => this.urlState.read(this.queryParams()));
  protected readonly loading = signal(false);
  protected readonly response = signal<SearchResponse | null>(null);
  protected readonly activeIndex = signal(0);
  protected readonly results = computed(() => this.response()?.data ?? []);
  protected readonly metaTotal = computed(() => this.response()?.meta.estimated_total_hits ?? 0);
  protected readonly facetGroups = computed(() => {
    const facets = this.response()?.facets ?? { community: {}, plugin: {}, category: {} };

    return [
      { key: 'community' as const, label: 'search.facets.community', values: this.facetValues(facets.community) },
      { key: 'plugin' as const, label: 'search.facets.plugin', values: this.facetValues(facets.plugin) },
      { key: 'category' as const, label: 'search.facets.category', values: this.facetValues(facets.category) },
    ];
  });

  constructor() {
    toObservable(this.state).pipe(
      debounceTime(250),
      tap(() => this.loading.set(true)),
      switchMap((state) => this.search.search(state.q, this.urlState.filters(state)).pipe(
        catchError(() => of(null)),
      )),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((response) => {
      this.loading.set(false);
      this.activeIndex.set(0);
      this.response.set(response);
    });
  }

  updateQuery(query: string): void {
    this.navigate({ ...this.state(), q: query });
  }

  toggleFacet(key: keyof Pick<SearchUrlState, 'community' | 'plugin' | 'category'>, value: string): void {
    const current = this.state();
    this.navigate({ ...current, [key]: current[key] === value ? undefined : value });
  }

  handleKeydown(event: KeyboardEvent): void {
    if (this.results().length === 0) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      this.activeIndex.set(Math.min(this.activeIndex() + 1, this.results().length - 1));
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      this.activeIndex.set(Math.max(this.activeIndex() - 1, 0));
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      const result = this.results()[this.activeIndex()];
      if (result !== undefined) {
        void this.router.navigate(['/commands', result.slug]);
      }
    }
  }

  highlight(value: string): SafeHtml {
    const term = this.state().q.trim();
    const escaped = this.escapeHtml(value);

    if (term.length === 0) {
      return this.sanitizer.bypassSecurityTrustHtml(escaped);
    }

    const highlighted = escaped.replace(new RegExp(this.escapeRegExp(term), 'gi'), (match) => `<mark class="rounded bg-neon-cyan/20 px-1 text-neon-cyan">${match}</mark>`);

    return this.sanitizer.bypassSecurityTrustHtml(highlighted);
  }

  private navigate(state: SearchUrlState): void {
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: this.urlState.toQueryParams(state),
      queryParamsHandling: 'merge',
    });
  }

  private facetValues(values: Record<string, number>): { name: string; count: number }[] {
    return Object.entries(values)
      .map(([name, count]) => ({ name, count }))
      .sort((first, second) => second.count - first.count || first.name.localeCompare(second.name));
  }

  private escapeHtml(value: string): string {
    return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
  }

  private escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }
}
