import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';

import { AuthService } from '@app/core/auth/auth.service';
import { ToastService } from '@app/core/toast/toast.service';

import { errorInterceptor } from './error.interceptor';

describe('errorInterceptor', () => {
  it('redirects to login on 401', () => {
    const clearSession = jest.fn();
    const navigateByUrl = jest.fn();

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([errorInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: { clearSession } },
        { provide: Router, useValue: { navigateByUrl } },
        { provide: ToastService, useValue: { danger: jest.fn() } },
      ],
    });

    const client = TestBed.inject(HttpClient);
    const http = TestBed.inject(HttpTestingController);
    let failed = false;

    client.get('/secure').subscribe({
      error: () => {
        failed = true;
      },
    });

    http.expectOne('/secure').flush({
      message: 'Unauthenticated.',
      code: 'auth.unauthenticated',
    }, { status: 401, statusText: 'Unauthorized' });

    expect(failed).toBe(true);
    expect(clearSession).toHaveBeenCalledTimes(1);
    expect(navigateByUrl).toHaveBeenCalledWith('/login');

    http.verify();
  });
});
