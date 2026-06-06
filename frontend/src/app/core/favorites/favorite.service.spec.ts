import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { API_BASE_URL } from '@app/core/api/api.tokens';

import { FavoriteService } from './favorite.service';

describe('FavoriteService', () => {
  let service: FavoriteService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://api.test/api/v1' },
      ],
    });

    service = TestBed.inject(FavoriteService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
  });

  it('rolls back optimistic favorite changes on 422 and 500 errors', () => {
    service.load().subscribe();
    http.expectOne('http://api.test/api/v1/favorites').flush({
      data: [
        {
          id: 10,
          type: 'command',
          favoritable_id: 1,
          item: null,
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
    });

    service.add({ type: 'document', id: 2 }).subscribe({ error: () => undefined });
    expect(service.isFavorite({ type: 'document', id: 2 })).toBe(true);
    http.expectOne('http://api.test/api/v1/favorites').flush({
      message: 'Invalid.',
      code: 'validation.failed',
    }, { status: 422, statusText: 'Unprocessable Entity' });
    expect(service.isFavorite({ type: 'document', id: 2 })).toBe(false);
    expect(service.isFavorite({ type: 'command', id: 1 })).toBe(true);

    service.remove({ type: 'command', id: 1 }).subscribe({ error: () => undefined });
    expect(service.isFavorite({ type: 'command', id: 1 })).toBe(false);
    http.expectOne('http://api.test/api/v1/favorites').flush({
      message: 'Server error.',
      code: 'server.error',
    }, { status: 500, statusText: 'Server Error' });
    expect(service.isFavorite({ type: 'command', id: 1 })).toBe(true);
  });
});
