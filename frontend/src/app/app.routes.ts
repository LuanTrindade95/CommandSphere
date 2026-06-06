import { Routes } from '@angular/router';

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
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];
