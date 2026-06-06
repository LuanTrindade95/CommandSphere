import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { API_BASE_URL } from '@app/core/api/api.tokens';

import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: API_BASE_URL, useValue: 'http://api.test/api/v1' },
      ],
    });

    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
  });

  it('logs in through dev-login and loads the authenticated user', () => {
    let completed = false;

    service.devLogin('admin@demo').subscribe(() => {
      completed = true;
    });

    const loginRequest = http.expectOne('http://api.test/api/v1/auth/dev-login');
    expect(loginRequest.request.method).toBe('POST');
    loginRequest.flush({ token: 'token-123', token_type: 'Bearer' });

    const meRequest = http.expectOne('http://api.test/api/v1/auth/me');
    expect(meRequest.request.method).toBe('GET');
    meRequest.flush({
      user: {
        id: 1,
        name: 'Admin Demo',
        email: 'admin@demo',
        discord_id: 'discord-1',
        username: 'admin',
        avatar: null,
      },
      communities: [
        {
          id: 1,
          name: 'Celem Ecosystem',
          slug: 'celem-ecosystem',
          role: 'community-admin',
          permissions: {
            'plugins.manage': true,
            'ingestion.run': true,
            'analytics.view': true,
          },
        },
      ],
    });

    expect(completed).toBe(true);
    expect(service.token()).toBe('token-123');
    expect(service.isAuthenticated()).toBe(true);
    expect(service.hasPermission('celem-ecosystem', 'plugins.manage')).toBe(true);
  });

  it('clears session when dev-login fails', () => {
    let failed = false;

    service.devLogin('missing@demo').subscribe({
      error: () => {
        failed = true;
      },
    });

    http.expectOne('http://api.test/api/v1/auth/dev-login').flush({
      message: 'User not found.',
      code: 'auth.user_not_found',
    }, { status: 404, statusText: 'Not Found' });

    expect(failed).toBe(true);
    expect(service.token()).toBeNull();
    expect(service.isAuthenticated()).toBe(false);
  });
});
