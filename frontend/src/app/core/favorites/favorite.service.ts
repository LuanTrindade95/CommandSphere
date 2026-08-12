import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { catchError, Observable, tap, throwError } from 'rxjs';

import { ApiCollection, ApiResource, FavoriteResource } from '@app/core/api/api.models';
import { API_BASE_URL } from '@app/core/api/api.tokens';

export type FavoriteTargetType = 'command' | 'document';

export interface FavoriteTarget {
  type: FavoriteTargetType;
  id: number;
}

@Injectable({ providedIn: 'root' })
export class FavoriteService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly favoritesState = signal<FavoriteResource[]>([]);

  readonly favorites = this.favoritesState.asReadonly();
  readonly favoriteKeys = computed(() => new Set(this.favoritesState().map((favorite) => this.key({
    type: favorite.type === 'document' ? 'document' : 'command',
    id: favorite.favoritable_id,
  }))));

  load(): Observable<ApiCollection<FavoriteResource>> {
    return this.http.get<ApiCollection<FavoriteResource>>(`${this.apiBaseUrl}/favorites`).pipe(
      tap((response) => this.favoritesState.set(response.data)),
    );
  }

  isFavorite(target: FavoriteTarget): boolean {
    return this.favoriteKeys().has(this.key(target));
  }

  add(target: FavoriteTarget): Observable<ApiResource<FavoriteResource>> {
    const optimistic = this.optimisticFavorite(target);
    const previous = this.favoritesState();

    this.favoritesState.update((favorites) => this.upsertFavorite(favorites, optimistic));

    return this.http.post<ApiResource<FavoriteResource>>(`${this.apiBaseUrl}/favorites`, target).pipe(
      tap((response) => this.favoritesState.update((favorites) => this.upsertFavorite(
        favorites.filter((favorite) => favorite.id !== optimistic.id),
        response.data,
      ))),
      catchError((error: unknown) => {
        this.favoritesState.set(previous);
        return throwError(() => error);
      }),
    );
  }

  remove(target: FavoriteTarget): Observable<void> {
    const previous = this.favoritesState();
    this.favoritesState.update((favorites) => favorites.filter((favorite) => this.key({
      type: favorite.type === 'document' ? 'document' : 'command',
      id: favorite.favoritable_id,
    }) !== this.key(target)));

    return this.http.delete<void>(`${this.apiBaseUrl}/favorites`, { body: target }).pipe(
      catchError((error: unknown) => {
        this.favoritesState.set(previous);
        return throwError(() => error);
      }),
    );
  }

  private key(target: FavoriteTarget): string {
    return `${target.type}:${target.id}`;
  }

  private optimisticFavorite(target: FavoriteTarget): FavoriteResource {
    return {
      id: -Date.now(),
      type: target.type,
      favoritable_id: target.id,
      item: null,
      created_at: new Date().toISOString(),
    };
  }

  private upsertFavorite(favorites: FavoriteResource[], next: FavoriteResource): FavoriteResource[] {
    const nextKey = this.key({
      type: next.type === 'document' ? 'document' : 'command',
      id: next.favoritable_id,
    });
    const withoutPrevious = favorites.filter((favorite) => this.key({
      type: favorite.type === 'document' ? 'document' : 'command',
      id: favorite.favoritable_id,
    }) !== nextKey);

    return [next, ...withoutPrevious];
  }
}
