import { inject } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivateFn, Router } from '@angular/router';

import { AuthService } from './auth.service';

export const permissionGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const permission = route.data['permission'];
  const communitySlug = route.paramMap.get('communitySlug') ?? route.queryParamMap.get('community') ?? auth.firstCommunitySlug();

  if (typeof permission !== 'string' || communitySlug === null) {
    return router.createUrlTree(['/']);
  }

  if (auth.hasPermission(communitySlug, permission)) {
    return true;
  }

  return router.createUrlTree(['/']);
};
