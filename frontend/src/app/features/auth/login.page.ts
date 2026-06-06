import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';

import { AuthService } from '@app/core/auth/auth.service';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiCardComponent } from '@app/shared/ui/card/ui-card.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiInputComponent } from '@app/shared/ui/input/ui-input.component';

@Component({
  selector: 'app-login-page',
  standalone: true,
  imports: [TranslocoPipe, UiButtonComponent, UiCardComponent, UiIconComponent, UiInputComponent],
  template: `
    <main class="grid min-h-screen place-items-center bg-deep-space px-4 py-8 text-slate-100">
      <section class="w-full max-w-md">
        <a class="mb-6 grid w-fit grid-cols-[auto_1fr] items-center gap-3" href="/">
          <span class="grid h-10 w-10 place-items-center rounded-md bg-electric-purple text-white shadow-command-glow">
            <app-ui-icon name="sparkles" [size]="18" />
          </span>
          <span class="font-display text-lg font-semibold text-white">{{ 'app.name' | transloco }}</span>
        </a>

        <app-ui-card>
          <div class="grid gap-5">
            <div class="grid gap-2">
              <h1 class="font-display text-2xl font-semibold text-white">{{ 'auth.title' | transloco }}</h1>
              <p class="text-sm leading-6 text-neutral-gray">{{ 'auth.description' | transloco }}</p>
            </div>

            <app-ui-button variant="primary" [ariaLabel]="'auth.discord' | transloco" (click)="auth.redirectToDiscord()">
              <app-ui-icon name="log-in" [size]="17" />
              {{ 'auth.discord' | transloco }}
            </app-ui-button>

            <div class="grid gap-3 border-t border-white/10 pt-5">
              <app-ui-input
                [label]="'auth.devEmailLabel' | transloco"
                [placeholder]="'auth.devEmailPlaceholder' | transloco"
                [value]="email()"
                autocomplete="email"
                [disabled]="loading()"
                (valueChange)="email.set($event)"
              />
              @if (errorKey(); as key) {
                <p class="text-sm font-medium text-danger">{{ key | transloco }}</p>
              }
              <app-ui-button variant="secondary" [loading]="loading()" [ariaLabel]="'auth.devLogin' | transloco" (click)="loginDev()">
                <app-ui-icon name="terminal" [size]="17" />
                {{ 'auth.devLogin' | transloco }}
              </app-ui-button>
            </div>
          </div>
        </app-ui-card>
      </section>
    </main>
  `,
})
export class LoginPageComponent {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly email = signal('admin@demo');
  protected readonly loading = signal(false);
  protected readonly errorKey = signal<string | null>(null);

  loginDev(): void {
    this.loading.set(true);
    this.errorKey.set(null);

    this.auth.devLogin(this.email()).subscribe({
      next: () => {
        this.loading.set(false);
        void this.router.navigateByUrl('/');
      },
      error: () => {
        this.loading.set(false);
        this.errorKey.set('auth.devLoginError');
      },
    });
  }
}
