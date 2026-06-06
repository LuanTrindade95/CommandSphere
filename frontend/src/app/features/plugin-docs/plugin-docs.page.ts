import { Component, computed, DestroyRef, effect, inject, signal } from '@angular/core';
import { NgClass } from '@angular/common';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, of } from 'rxjs';

import { DocumentResource, PluginResource } from '@app/core/api/api.models';
import { CatalogService } from '@app/core/catalog/catalog.service';
import { FavoriteService } from '@app/core/favorites/favorite.service';
import { MarkdownRendererService, RenderedMarkdown } from '@app/core/markdown/markdown-renderer.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiSkeletonComponent } from '@app/shared/ui/skeleton/ui-skeleton.component';

@Component({
  selector: 'app-plugin-docs-page',
  standalone: true,
  imports: [NgClass, RouterLink, TranslocoPipe, UiBadgeComponent, UiButtonComponent, UiEmptyStateComponent, UiIconComponent, UiSkeletonComponent],
  template: `
    <section class="grid gap-6">
      @if (loading()) {
        <div class="grid gap-4">
          <app-ui-skeleton width="18rem" height="2rem" />
          <app-ui-skeleton height="30rem" />
        </div>
      } @else if (error()) {
        <app-ui-empty-state icon="terminal" [title]="'docs.errorTitle' | transloco" [description]="'docs.errorDescription' | transloco" />
      } @else {
        @if (plugin(); as currentPlugin) {
          <header class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
          <div class="grid gap-2">
            <a class="inline-flex items-center gap-2 text-sm font-semibold text-neon-cyan" [routerLink]="['/c', communitySlug()]">
              <app-ui-icon name="chevron-right" [size]="15" />
              {{ 'docs.backToCatalog' | transloco }}
            </a>
            <app-ui-badge tone="purple">{{ currentPlugin.slug }}</app-ui-badge>
            <h1 class="font-display text-3xl font-semibold text-white">{{ currentPlugin.name }}</h1>
            <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ currentPlugin.description ?? ('docs.noDescription' | transloco) }}</p>
          </div>
          <label class="grid gap-2 text-sm font-medium text-slate-300">
            <span>{{ 'docs.versionLabel' | transloco }}</span>
            <select
              class="h-11 min-w-40 rounded-md border border-white/10 bg-surface-dark px-3 text-sm text-white outline-none focus:border-neon-cyan/60"
              [value]="selectedVersion()"
              (change)="onVersionChange($event)"
            >
              @for (version of currentPlugin.versions; track version.id) {
                <option [value]="version.version">{{ version.version }}</option>
              }
            </select>
          </label>
        </header>

        <div class="grid gap-5 xl:grid-cols-[16rem_1fr_14rem]">
          <aside class="grid content-start gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-normal text-neutral-gray">{{ 'docs.documentsTitle' | transloco }}</h2>
            @for (document of documents(); track document.id) {
              <button
                type="button"
                class="rounded-md border px-3 py-2 text-left text-sm transition"
                [ngClass]="selectedDocument()?.id === document.id ? 'border-neon-cyan bg-neon-cyan/10' : 'border-white/10 bg-white/5'"
                (click)="selectDocument(document)"
              >
                {{ document.title }}
              </button>
            }
          </aside>

          <article class="min-w-0 rounded-lg border border-white/10 bg-surface-dark/72 p-5">
            @if (selectedDocument(); as document) {
              <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-white/10 pb-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-normal text-neutral-gray">{{ document.path }}</p>
                  <h2 class="mt-1 font-display text-2xl font-semibold text-white">{{ document.title }}</h2>
                </div>
                <app-ui-button variant="secondary" (click)="toggleFavorite(document)">
                  <app-ui-icon name="star" [size]="16" />
                  {{ favoriteLabel(document.id) | transloco }}
                </app-ui-button>
              </div>
              <div class="prose prose-invert max-w-none prose-pre:p-0 prose-a:text-neon-cyan" [innerHTML]="rendered()?.html"></div>
            } @else {
              <app-ui-empty-state icon="file-text" [title]="'docs.noDocumentTitle' | transloco" [description]="'docs.noDocumentDescription' | transloco" />
            }
          </article>

          <aside class="grid content-start gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-normal text-neutral-gray">{{ 'docs.indexTitle' | transloco }}</h2>
            @for (heading of rendered()?.headings ?? []; track heading.id) {
              <a class="rounded-md px-2 py-1 text-sm text-neutral-gray hover:bg-white/6 hover:text-white" [class.pl-5]="heading.level === 3" [href]="'#' + heading.id">
                {{ heading.text }}
              </a>
            }
          </aside>
          </div>
        }
      }
    </section>
  `,
})
export class PluginDocsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly catalog = inject(CatalogService);
  private readonly renderer = inject(MarkdownRendererService);
  private readonly favorites = inject(FavoriteService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly params = toSignal(this.route.paramMap);
  private readonly queryParams = toSignal(this.route.queryParamMap);

  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly plugin = signal<PluginResource | null>(null);
  protected readonly documents = signal<DocumentResource[]>([]);
  protected readonly selectedVersion = signal('');
  protected readonly selectedDocument = signal<DocumentResource | null>(null);
  protected readonly rendered = signal<RenderedMarkdown | null>(null);
  protected readonly communitySlug = computed(() => this.params()?.get('community') ?? '');
  protected readonly pluginSlug = computed(() => this.params()?.get('plugin') ?? '');

  constructor() {
    void this.favorites.load().subscribe();
    effect(() => this.loadPlugin(this.communitySlug(), this.pluginSlug()));
  }

  onVersionChange(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLSelectElement) {
      this.selectVersion(target.value);
    }
  }

  selectVersion(version: string): void {
    const plugin = this.plugin();

    if (plugin === null || version.length === 0) {
      return;
    }

    this.selectedVersion.set(version);
    this.loadDocuments(plugin.slug, version, null);
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { version, doc: null },
      queryParamsHandling: 'merge',
    });
  }

  selectDocument(document: DocumentResource): void {
    this.selectedDocument.set(document);
    this.rendered.set(this.renderer.render(document.content_html));
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { doc: document.id },
      queryParamsHandling: 'merge',
    });
  }

  toggleFavorite(document: DocumentResource): void {
    const target = { type: 'document' as const, id: document.id };

    if (this.favorites.isFavorite(target)) {
      this.favorites.remove(target).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
      return;
    }

    this.favorites.add(target).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
  }

  favoriteLabel(documentId: number): string {
    return this.favorites.isFavorite({ type: 'document', id: documentId }) ? 'favorites.remove' : 'favorites.add';
  }

  private loadPlugin(community: string, slug: string): void {
    if (community.length === 0 || slug.length === 0) {
      return;
    }

    this.loading.set(true);
    this.error.set(false);

    this.catalog.plugin(slug, community).pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => {
        this.error.set(true);
        return of(null);
      }),
    ).subscribe((response) => {
      if (response === null) {
        this.loading.set(false);
        return;
      }

      const plugin = response.data;
      const requestedVersion = this.queryParams()?.get('version');
      const latest = plugin.versions.find((version) => version.is_latest)?.version ?? plugin.versions[0]?.version ?? '';
      const version = requestedVersion ?? latest;

      this.plugin.set(plugin);
      this.selectedVersion.set(version);
      this.loadDocuments(plugin.slug, version, this.queryParams()?.get('doc') ?? null);
    });
  }

  private loadDocuments(slug: string, version: string, requestedDocumentId: string | null): void {
    this.loading.set(true);
    this.catalog.versionDocuments(slug, version).pipe(
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

      const documents = response.data;
      const requestedId = requestedDocumentId === null ? null : Number(requestedDocumentId);
      const selected = documents.find((document) => document.id === requestedId) ?? documents[0] ?? null;

      this.documents.set(documents);
      this.selectedDocument.set(selected);
      this.rendered.set(selected === null ? null : this.renderer.render(selected.content_html));
    });
  }
}
