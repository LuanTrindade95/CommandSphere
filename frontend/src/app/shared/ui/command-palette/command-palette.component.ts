import { isPlatformBrowser } from '@angular/common';
import { Component, DestroyRef, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, debounceTime, distinctUntilChanged, filter, finalize, fromEvent, of, Subject, switchMap, tap } from 'rxjs';

import { CommandResult, SearchResponse } from '@app/core/api/api.models';
import { AuthService } from '@app/core/auth/auth.service';
import { SearchService } from '@app/core/search/search.service';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiInputComponent } from '@app/shared/ui/input/ui-input.component';

import { CommandPaletteService } from './command-palette.service';

const EMPTY_SEARCH_RESPONSE: SearchResponse = {
  data: [],
  facets: {
    plugin: {},
    category: {},
  },
  meta: {
    query: '',
    estimated_total_hits: 0,
  },
};

@Component({
  selector: 'app-command-palette',
  standalone: true,
  imports: [TranslocoPipe, UiEmptyStateComponent, UiIconComponent, UiInputComponent],
  template: `
    @if (palette.isOpen()) {
      <div class="fixed inset-0 z-40 bg-deep-space/78 px-4 py-6 backdrop-blur-sm" (click)="close()">
        <section
          class="mx-auto grid max-h-[min(44rem,calc(100vh-3rem))] w-full max-w-3xl grid-rows-[auto_1fr] overflow-hidden rounded-lg border border-white/10 bg-surface-dark/98 shadow-command-glow"
          role="dialog"
          aria-modal="true"
          [attr.aria-label]="'palette.ariaLabel' | transloco"
          (click)="$event.stopPropagation()"
          (keydown)="onPanelKeydown($event)"
        >
          <header class="border-b border-white/10 p-4">
            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3">
              <app-ui-icon class="text-neon-cyan" name="command" [size]="20" />
              <app-ui-input
                [placeholder]="'palette.placeholder' | transloco"
                [value]="query()"
                [disabled]="loading()"
                (valueChange)="setQuery($event)"
              />
              <kbd class="rounded border border-white/10 bg-white/6 px-2 py-1 text-xs font-semibold text-neutral-gray">
                {{ 'palette.escapeKey' | transloco }}
              </kbd>
            </div>
          </header>

          <div class="min-h-0 overflow-y-auto p-3">
            @if (!auth.isAuthenticated()) {
              <app-ui-empty-state
                icon="terminal"
                [title]="'palette.authRequiredTitle' | transloco"
                [description]="'palette.authRequiredDescription' | transloco"
              />
            } @else if (loading()) {
              <div class="grid gap-2">
                @for (item of loadingRows; track item) {
                  <div class="h-16 animate-pulse rounded-md bg-white/8"></div>
                }
              </div>
            } @else if (results().length === 0) {
              <app-ui-empty-state
                icon="search"
                [title]="emptyTitle() | transloco"
                [description]="emptyDescription() | transloco"
              />
            } @else {
              <div class="mb-3 flex flex-wrap items-center gap-2 px-2 text-xs text-neutral-gray">
                <span>{{ 'palette.resultCount' | transloco: { count: totalHits() } }}</span>
                @for (facet of topFacets(); track facet.label) {
                  <span class="rounded-full border border-white/10 bg-white/6 px-2 py-1">{{ facet.label }} / {{ facet.count }}</span>
                }
              </div>

              <ul class="grid gap-1" role="listbox">
                @for (command of results(); track command.id; let index = $index) {
                  <li>
                    <button
                      type="button"
                      role="option"
                      class="grid w-full grid-cols-[auto_1fr_auto] items-center gap-3 rounded-md px-3 py-3 text-left transition hover:bg-white/8"
                      [class.bg-electric-purple]="selectedIndex() === index"
                      [class.text-white]="selectedIndex() === index"
                      (mouseenter)="selectedIndex.set(index)"
                      (click)="openResult(command)"
                    >
                      <app-ui-icon class="text-neon-cyan" name="terminal" [size]="18" />
                      <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-100">{{ command.name }}</span>
                        <span class="block truncate font-mono text-xs text-neutral-gray">{{ command.syntax }}</span>
                      </span>
                      <span class="hidden text-xs font-medium text-neutral-gray sm:block">
                        {{ command.plugin?.slug ?? ('palette.unknownPlugin' | transloco) }}
                      </span>
                    </button>
                  </li>
                }
              </ul>
            }
          </div>
        </section>
      </div>
    }
  `,
})
export class CommandPaletteComponent {
  protected readonly palette = inject(CommandPaletteService);
  protected readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly router = inject(Router);
  private readonly search = inject(SearchService);
  private readonly queryInput = new Subject<string>();
  private readonly response = signal<SearchResponse>(EMPTY_SEARCH_RESPONSE);

  protected readonly query = signal('');
  protected readonly loading = signal(false);
  protected readonly selectedIndex = signal(0);
  protected readonly loadingRows = [1, 2, 3, 4];

  protected readonly results = computed(() => this.response().data);
  protected readonly totalHits = computed(() => this.response().meta.estimated_total_hits);
  protected readonly emptyTitle = computed(() => this.query().trim().length === 0 ? 'palette.emptyTitle' : 'palette.noResultsTitle');
  protected readonly emptyDescription = computed(() => this.query().trim().length === 0 ? 'palette.emptyDescription' : 'palette.noResultsDescription');
  protected readonly topFacets = computed(() => Object.entries(this.response().facets.plugin)
    .slice(0, 3)
    .map(([label, count]) => ({ label, count })));

  constructor() {
    this.queryInput.pipe(
      debounceTime(220),
      distinctUntilChanged(),
      tap(() => this.selectedIndex.set(0)),
      switchMap((query) => {
        if (!this.auth.isAuthenticated() || query.trim().length === 0) {
          return of(EMPTY_SEARCH_RESPONSE);
        }

        this.loading.set(true);

        return this.search.search(query.trim()).pipe(
          catchError(() => of(EMPTY_SEARCH_RESPONSE)),
          finalize(() => this.loading.set(false)),
        );
      }),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((response) => this.response.set(response));

    if (isPlatformBrowser(this.platformId)) {
      fromEvent<KeyboardEvent>(window, 'keydown').pipe(
        filter((event) => (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k'),
        takeUntilDestroyed(this.destroyRef),
      ).subscribe((event) => {
        event.preventDefault();
        this.palette.toggle();
      });
    }
  }

  setQuery(value: string): void {
    this.query.set(value);
    this.queryInput.next(value);
  }

  close(): void {
    this.palette.close();
  }

  onPanelKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.close();
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      this.moveSelection(1);
      return;
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault();
      this.moveSelection(-1);
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      const command = this.results()[this.selectedIndex()];

      if (command !== undefined) {
        this.openResult(command);
      }
    }
  }

  openResult(command: CommandResult): void {
    this.close();
    void this.router.navigate(['/'], { queryParams: { command: command.slug } });
  }

  private moveSelection(direction: 1 | -1): void {
    const resultCount = this.results().length;

    if (resultCount === 0) {
      return;
    }

    this.selectedIndex.update((index) => (index + direction + resultCount) % resultCount);
  }
}
