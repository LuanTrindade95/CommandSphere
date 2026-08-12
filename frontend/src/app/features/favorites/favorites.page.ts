import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';

import { CommandResult, DocumentResource, FavoriteResource } from '@app/core/api/api.models';
import { FavoriteService } from '@app/core/favorites/favorite.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

@Component({
  selector: 'app-favorites-page',
  standalone: true,
  imports: [RouterLink, TranslocoPipe, UiBadgeComponent, UiButtonComponent, UiEmptyStateComponent, UiIconComponent],
  template: `
    <section class="grid gap-6">
      <header class="grid gap-2">
        <app-ui-badge tone="cyan">{{ 'favorites.badge' | transloco }}</app-ui-badge>
        <h1 class="font-display text-3xl font-semibold text-white">{{ 'favorites.title' | transloco }}</h1>
        <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ 'favorites.description' | transloco }}</p>
      </header>

      @if (favorites.favorites().length === 0) {
        <app-ui-empty-state icon="file-text" [title]="'favorites.emptyTitle' | transloco" [description]="'favorites.emptyDescription' | transloco" />
      } @else {
        <div class="grid gap-3">
          @for (favorite of favorites.favorites(); track favorite.id) {
            <article class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-4 md:grid-cols-[1fr_auto] md:items-center">
              <div class="grid gap-2">
                <div class="flex flex-wrap gap-2">
                  <app-ui-badge [tone]="favorite.type === 'command' ? 'purple' : 'neutral'">{{ favorite.type }}</app-ui-badge>
                  @if (isCommand(favorite.item)) {
                    <app-ui-badge tone="cyan">{{ favorite.item.plugin?.name ?? ('favorites.unknownPlugin' | transloco) }}</app-ui-badge>
                  }
                </div>
                @if (isCommand(favorite.item)) {
                  <a class="font-display text-lg font-semibold text-white hover:text-neon-cyan" [routerLink]="['/commands', favorite.item.slug]">{{ favorite.item.name }}</a>
                  <p class="text-sm text-neutral-gray">{{ favorite.item.description ?? favorite.item.syntax }}</p>
                } @else if (isDocument(favorite.item)) {
                  <h2 class="font-display text-lg font-semibold text-white">{{ favorite.item.title }}</h2>
                  <p class="text-sm text-neutral-gray">{{ favorite.item.path }}</p>
                } @else {
                  <h2 class="font-display text-lg font-semibold text-white">{{ 'favorites.unavailableTitle' | transloco }}</h2>
                }
              </div>
              <app-ui-button variant="danger" (click)="remove(favorite)">
                <app-ui-icon name="x" [size]="16" />
                {{ 'favorites.remove' | transloco }}
              </app-ui-button>
            </article>
          }
        </div>
      }
    </section>
  `,
})
export class FavoritesPageComponent {
  protected readonly favorites = inject(FavoriteService);
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    this.favorites.load().pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
  }

  remove(favorite: FavoriteResource): void {
    if (favorite.type !== 'command' && favorite.type !== 'document') {
      return;
    }

    this.favorites.remove({ type: favorite.type, id: favorite.favoritable_id }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
  }

  isCommand(item: FavoriteResource['item']): item is CommandResult {
    return item !== null && 'syntax' in item;
  }

  isDocument(item: FavoriteResource['item']): item is DocumentResource {
    return item !== null && 'content_html' in item;
  }
}
