import { DOCUMENT, isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, PLATFORM_ID, signal } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, map, Observable, of, switchMap, tap, throwError } from 'rxjs';

import { API_BASE_URL } from '@app/core/api/api.tokens';
import { AuthCommunity, AuthMeResponse, AuthSessionResponse, AuthUser } from '@app/core/api/api.models';

type AuthStatus = 'anonymous' | 'authenticating' | 'authenticated';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly document = inject(DOCUMENT);

  private readonly tokenState = signal<string | null>(null);
  private readonly userState = signal<AuthUser | null>(null);
  private readonly communitiesState = signal<AuthCommunity[]>([]);
  private readonly statusState = signal<AuthStatus>('anonymous');

  readonly token = this.tokenState.asReadonly();
  readonly user = this.userState.asReadonly();
  readonly communities = this.communitiesState.asReadonly();
  readonly status = this.statusState.asReadonly();
  readonly isAuthenticated = computed(() => this.tokenState() !== null && this.userState() !== null);

  devLogin(email: string): Observable<AuthMeResponse> {
    this.statusState.set('authenticating');

    return this.http.post<AuthSessionResponse>(`${this.apiBaseUrl}/auth/dev-login`, { email }).pipe(
      tap((session) => this.tokenState.set(session.token)),
      switchMap(() => this.refreshMe()),
      catchError((error: unknown) => {
        this.clearSession();
        return throwError(() => error);
      }),
    );
  }

  refreshMe(): Observable<AuthMeResponse> {
    return this.http.get<AuthMeResponse>(`${this.apiBaseUrl}/auth/me`).pipe(
      tap((response) => this.applyMe(response)),
    );
  }

  logout(): Observable<void> {
    return this.http.post<{ message: string; code: string }>(`${this.apiBaseUrl}/auth/logout`, {}).pipe(
      map(() => undefined),
      catchError(() => of(undefined)),
      tap(() => {
        this.clearSession();
        void this.router.navigateByUrl('/login');
      }),
    );
  }

  redirectToDiscord(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    this.document.defaultView?.location.assign(`${this.apiBaseUrl}/auth/discord/redirect`);
  }

  clearSession(): void {
    this.tokenState.set(null);
    this.userState.set(null);
    this.communitiesState.set([]);
    this.statusState.set('anonymous');
  }

  hasPermission(communitySlug: string, permission: string): boolean {
    return this.communitiesState()
      .some((community) => community.slug === communitySlug && community.permissions[permission] === true);
  }

  firstCommunitySlug(): string | null {
    return this.communitiesState()[0]?.slug ?? null;
  }

  private applyMe(response: AuthMeResponse): void {
    this.userState.set(response.user);
    this.communitiesState.set(response.communities);
    this.statusState.set('authenticated');
  }
}
