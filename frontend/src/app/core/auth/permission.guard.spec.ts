import { convertToParamMap, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
import { ActivatedRouteSnapshot } from '@angular/router';
import { TestBed } from '@angular/core/testing';

import { AuthService } from './auth.service';
import { permissionGuard } from './permission.guard';

describe('permissionGuard', () => {
  it('blocks users without the required community permission', () => {
    const deniedTree = {} as UrlTree;

    TestBed.configureTestingModule({
      providers: [
        {
          provide: AuthService,
          useValue: {
            firstCommunitySlug: () => 'celem-ecosystem',
            hasPermission: () => false,
          },
        },
        {
          provide: Router,
          useValue: {
            createUrlTree: () => deniedTree,
          },
        },
      ],
    });

    const route = {
      data: { permission: 'plugins.manage' },
      paramMap: convertToParamMap({ communitySlug: 'celem-ecosystem' }),
      queryParamMap: convertToParamMap({}),
    } as unknown as ActivatedRouteSnapshot;
    const state = {} as RouterStateSnapshot;
    const result = TestBed.runInInjectionContext(() => permissionGuard(route, state));

    expect(result).toBe(deniedTree);
  });
});
