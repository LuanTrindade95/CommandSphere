import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, of } from 'rxjs';

import { CommandResult } from '@app/core/api/api.models';
import { AnalyticsService } from '@app/core/analytics/analytics.service';
import { AuthService } from '@app/core/auth/auth.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';

@Component({
  selector: 'app-analytics-page',
  standalone: true,
  imports: [TranslocoPipe, UiBadgeComponent, UiEmptyStateComponent],
  template: `
    <section class="grid gap-6">
      <header class="grid gap-3 lg:grid-cols-[1fr_auto_auto] lg:items-end">
        <div class="grid gap-2">
          <app-ui-badge tone="cyan">{{ 'analytics.badge' | transloco }}</app-ui-badge>
          <h1 class="font-display text-3xl font-semibold text-white">{{ 'analytics.title' | transloco }}</h1>
          <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ 'analytics.description' | transloco }}</p>
        </div>
        <label class="grid gap-2 text-sm font-medium text-slate-300">
          <span>{{ 'analytics.communityLabel' | transloco }}</span>
          <select class="h-11 rounded-md border border-white/10 bg-surface-dark px-3 text-sm text-white" [value]="community()" (change)="setCommunity($event)">
            @for (community of allowedCommunities(); track community.slug) {
              <option [value]="community.slug">{{ community.name }}</option>
            }
          </select>
        </label>
        <label class="grid gap-2 text-sm font-medium text-slate-300">
          <span>{{ 'analytics.periodLabel' | transloco }}</span>
          <select class="h-11 rounded-md border border-white/10 bg-surface-dark px-3 text-sm text-white" [value]="days()" (change)="setDays($event)">
            <option value="7">{{ 'analytics.period7' | transloco }}</option>
            <option value="30">{{ 'analytics.period30' | transloco }}</option>
            <option value="90">{{ 'analytics.period90' | transloco }}</option>
          </select>
        </label>
      </header>

      @if (allowedCommunities().length === 0) {
        <app-ui-empty-state icon="terminal" [title]="'analytics.forbiddenTitle' | transloco" [description]="'analytics.forbiddenDescription' | transloco" />
      } @else if (commands().length === 0) {
        <app-ui-empty-state icon="search" [title]="'analytics.emptyTitle' | transloco" [description]="'analytics.emptyDescription' | transloco" />
      } @else {
        <div class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-5">
          @for (command of commands(); track command.id) {
            <article class="grid gap-2">
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-white">{{ command.name }}</span>
                <span class="text-neutral-gray">{{ 'command.views' | transloco: { count: command.views } }}</span>
              </div>
              <svg class="block h-2 w-full" viewBox="0 0 100 8" preserveAspectRatio="none" role="presentation" aria-hidden="true">
                <rect width="100" height="8" rx="4" class="fill-white/8" />
                <rect [attr.width]="barWidth(command)" height="8" rx="4" class="fill-neon-cyan" />
              </svg>
            </article>
          }
        </div>
      }
    </section>
  `,
})
export class AnalyticsPageComponent {
  private readonly analytics = inject(AnalyticsService);
  private readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly days = signal(30);
  protected readonly community = signal('');
  protected readonly commands = signal<CommandResult[]>([]);
  protected readonly allowedCommunities = computed(() => this.auth.communities().filter((community) => community.permissions['analytics.view'] === true));
  protected readonly maxViews = computed(() => Math.max(1, ...this.commands().map((command) => command.views)));

  constructor() {
    const first = this.allowedCommunities()[0]?.slug ?? '';
    this.community.set(first);
    this.load();
  }

  setCommunity(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLSelectElement) {
      this.community.set(target.value);
      this.load();
    }
  }

  setDays(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLSelectElement) {
      this.days.set(Number(target.value));
      this.load();
    }
  }

  barWidth(command: CommandResult): number {
    return Math.max(6, Math.round((command.views / this.maxViews()) * 100));
  }

  private load(): void {
    if (this.community().length === 0) {
      return;
    }

    this.analytics.mostViewed(this.community(), this.days()).pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => of({ data: [] })),
    ).subscribe((response) => this.commands.set(response.data));
  }
}
