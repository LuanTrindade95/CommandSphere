import { Component, DestroyRef, inject, OnDestroy, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, of } from 'rxjs';

import { IngestionRunResource } from '@app/core/api/api.models';
import { IngestionService } from '@app/core/admin/ingestion.service';
import { AuthService } from '@app/core/auth/auth.service';
import { RealtimeService, RealtimeSubscription } from '@app/core/realtime/realtime.service';
import { ToastService } from '@app/core/toast/toast.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';

interface IngestionRunStatusChangedPayload {
  run: IngestionRunResource;
}

@Component({
  selector: 'app-admin-ingestions-page',
  standalone: true,
  imports: [TranslocoPipe, UiBadgeComponent, UiEmptyStateComponent],
  template: `
    <section class="grid gap-6">
      <header class="grid gap-2">
        <app-ui-badge tone="purple">{{ 'admin.ingestions.badge' | transloco }}</app-ui-badge>
        <h1 class="font-display text-3xl font-semibold text-white">{{ 'admin.ingestions.title' | transloco }}</h1>
        <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ 'admin.ingestions.description' | transloco }}</p>
      </header>

      @if (runs().length === 0) {
        <app-ui-empty-state icon="terminal" [title]="'admin.ingestions.emptyTitle' | transloco" [description]="'admin.ingestions.emptyDescription' | transloco" />
      } @else {
        <div class="grid gap-3">
          @for (run of runs(); track run.id) {
            <article class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                  <app-ui-badge [tone]="run.status === 'success' ? 'success' : run.status === 'failed' ? 'danger' : 'neutral'">{{ run.status }}</app-ui-badge>
                  <span class="text-sm text-neutral-gray">{{ 'admin.ingestions.runId' | transloco: { id: run.id } }}</span>
                </div>
                <span class="text-sm text-neutral-gray">{{ run.finished_at ?? run.started_at }}</span>
              </div>
              <pre class="overflow-x-auto rounded-md border border-white/10 bg-deep-space p-3 text-xs leading-6 text-neutral-gray">{{ stringify(run.stats) }}</pre>
              <pre class="overflow-x-auto rounded-md border border-white/10 bg-deep-space p-3 text-xs leading-6 text-neutral-gray">{{ stringify(run.log) }}</pre>
            </article>
          }
        </div>
      }
    </section>
  `,
})
export class AdminIngestionsPageComponent implements OnDestroy {
  private readonly ingestion = inject(IngestionService);
  private readonly auth = inject(AuthService);
  private readonly realtime = inject(RealtimeService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly subscriptions: RealtimeSubscription[] = [];

  protected readonly runs = signal<IngestionRunResource[]>([]);

  constructor() {
    this.ingestion.runs().pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => of({ data: [] })),
    ).subscribe((response) => this.runs.set(response.data));

    this.subscribeToRealtime();
  }

  ngOnDestroy(): void {
    this.subscriptions.forEach((subscription) => subscription.stop());
  }

  stringify(value: unknown): string {
    return JSON.stringify(value ?? {}, null, 2);
  }

  private subscribeToRealtime(): void {
    const communities = this.auth.communities()
      .filter((community) => community.permissions['analytics.view'] === true || community.permissions['ingestion.run'] === true);

    for (const community of communities) {
      this.subscriptions.push(this.realtime.listenPrivate<IngestionRunStatusChangedPayload>(
        `community.${community.slug}`,
        'ingestion.run.status.changed',
        (payload) => this.applyRun(payload.run),
      ));
    }
  }

  private applyRun(run: IngestionRunResource): void {
    this.runs.update((runs) => {
      const withoutCurrent = runs.filter((current) => current.id !== run.id);

      return [run, ...withoutCurrent].sort((first, second) => second.id - first.id);
    });

    if (['success', 'partial', 'failed'].includes(run.status)) {
      this.toast.info('admin.ingestions.finishedToast');
    }
  }
}
