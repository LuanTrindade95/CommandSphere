import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '@app/core/auth/auth.service';
import { ToastService } from '@app/core/toast/toast.service';

export const errorInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const toast = inject(ToastService);

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401) {
          auth.clearSession();
          void router.navigateByUrl('/login');
        }

        if (error.status === 403) {
          toast.danger('toast.forbidden');
        }

        if (error.status >= 500) {
          toast.danger('toast.serverError');
        }
      }

      return throwError(() => error);
    }),
  );
};
