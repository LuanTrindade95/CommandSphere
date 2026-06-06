import { Routes } from '@angular/router';

import { authGuard } from '@app/core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/auth/login.page').then((component) => component.LoginPageComponent),
  },
  {
    path: '',
    loadComponent: () => import('./shell/app-shell.component').then((component) => component.AppShellComponent),
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () => import('./features/home/home.page').then((component) => component.HomePageComponent),
      },
      {
        path: 'c/:community',
        loadComponent: () => import('./features/catalog/catalog.page').then((component) => component.CatalogPageComponent),
      },
      {
        path: 'c/:community/p/:plugin',
        loadComponent: () => import('./features/plugin-docs/plugin-docs.page').then((component) => component.PluginDocsPageComponent),
      },
      {
        path: 'c/:community/p/:plugin/commands/:slug',
        loadComponent: () => import('./features/command/command.page').then((component) => component.CommandPageComponent),
      },
      {
        path: 'commands/:slug',
        loadComponent: () => import('./features/command/command.page').then((component) => component.CommandPageComponent),
      },
      {
        path: 'search',
        loadComponent: () => import('./features/search/search.page').then((component) => component.SearchPageComponent),
      },
      {
        path: 'favorites',
        canActivate: [authGuard],
        loadComponent: () => import('./features/favorites/favorites.page').then((component) => component.FavoritesPageComponent),
      },
      {
        path: 'analytics',
        canActivate: [authGuard],
        loadComponent: () => import('./features/analytics/analytics.page').then((component) => component.AnalyticsPageComponent),
      },
      {
        path: 'admin/plugins',
        canActivate: [authGuard],
        loadComponent: () => import('./features/admin/admin-plugins.page').then((component) => component.AdminPluginsPageComponent),
      },
      {
        path: 'admin/ingestions',
        canActivate: [authGuard],
        loadComponent: () => import('./features/admin/admin-ingestions.page').then((component) => component.AdminIngestionsPageComponent),
      },
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];
