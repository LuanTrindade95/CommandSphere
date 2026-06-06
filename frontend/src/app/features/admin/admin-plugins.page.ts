import { Component, computed, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { TranslocoPipe } from '@jsverse/transloco';
import { catchError, of } from 'rxjs';

import { PluginCreatePayload, PluginResource } from '@app/core/api/api.models';
import { AuthService } from '@app/core/auth/auth.service';
import { CatalogService } from '@app/core/catalog/catalog.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiEmptyStateComponent } from '@app/shared/ui/empty-state/ui-empty-state.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiInputComponent } from '@app/shared/ui/input/ui-input.component';

@Component({
  selector: 'app-admin-plugins-page',
  standalone: true,
  imports: [TranslocoPipe, UiBadgeComponent, UiButtonComponent, UiEmptyStateComponent, UiIconComponent, UiInputComponent],
  template: `
    <section class="grid gap-6">
      <header class="grid gap-2">
        <app-ui-badge tone="purple">{{ 'admin.plugins.badge' | transloco }}</app-ui-badge>
        <h1 class="font-display text-3xl font-semibold text-white">{{ 'admin.plugins.title' | transloco }}</h1>
        <p class="max-w-3xl text-sm leading-6 text-neutral-gray">{{ 'admin.plugins.description' | transloco }}</p>
      </header>

      @if (managedCommunities().length === 0) {
        <app-ui-empty-state icon="terminal" [title]="'admin.plugins.forbiddenTitle' | transloco" [description]="'admin.plugins.forbiddenDescription' | transloco" />
      } @else {
        <div class="grid gap-5 xl:grid-cols-[24rem_1fr]">
          <form class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-5" (submit)="create($event)">
            <label class="grid gap-2 text-sm font-medium text-slate-300">
              <span>{{ 'admin.plugins.communityLabel' | transloco }}</span>
              <select class="h-11 rounded-md border border-white/10 bg-deep-space px-3 text-sm text-white" [value]="communityId()" (change)="setCommunity($event)">
                @for (community of managedCommunities(); track community.id) {
                  <option [value]="community.id">{{ community.name }}</option>
                }
              </select>
            </label>
            <app-ui-input [label]="'admin.plugins.nameLabel' | transloco" [value]="name()" (valueChange)="name.set($event)" />
            <app-ui-input [label]="'admin.plugins.slugLabel' | transloco" [value]="slug()" (valueChange)="slug.set($event)" />
            <app-ui-input [label]="'admin.plugins.descriptionLabel' | transloco" [value]="description()" (valueChange)="description.set($event)" />
            <app-ui-input [label]="'admin.plugins.repoLabel' | transloco" [value]="githubRepo()" (valueChange)="githubRepo.set($event)" />
            <app-ui-input [label]="'admin.plugins.docsPathLabel' | transloco" [value]="docsPath()" (valueChange)="docsPath.set($event)" />
            <app-ui-input [label]="'admin.plugins.branchLabel' | transloco" [value]="defaultBranch()" (valueChange)="defaultBranch.set($event)" />
            <app-ui-button type="submit" [loading]="saving()">
              <app-ui-icon name="check" [size]="16" />
              {{ 'admin.plugins.create' | transloco }}
            </app-ui-button>
          </form>

          <section class="grid content-start gap-3">
            @for (plugin of plugins(); track plugin.id) {
              <article class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-4 md:grid-cols-[1fr_auto] md:items-center">
                <div>
                  <h2 class="font-display text-lg font-semibold text-white">{{ plugin.name }}</h2>
                  <p class="text-sm text-neutral-gray">{{ plugin.repository_url }}</p>
                </div>
                <app-ui-button variant="secondary" (click)="sync(plugin)">
                  <app-ui-icon name="loader-circle" [size]="16" />
                  {{ 'admin.plugins.sync' | transloco }}
                </app-ui-button>
              </article>
            } @empty {
              <app-ui-empty-state icon="file-text" [title]="'admin.plugins.emptyTitle' | transloco" [description]="'admin.plugins.emptyDescription' | transloco" />
            }
          </section>
        </div>
      }
    </section>
  `,
})
export class AdminPluginsPageComponent {
  private readonly auth = inject(AuthService);
  private readonly catalog = inject(CatalogService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly managedCommunities = computed(() => this.auth.communities().filter((community) => community.permissions['plugins.manage'] === true));
  protected readonly communityId = signal<number | null>(null);
  protected readonly plugins = signal<PluginResource[]>([]);
  protected readonly name = signal('');
  protected readonly slug = signal('');
  protected readonly description = signal('');
  protected readonly githubRepo = signal('');
  protected readonly docsPath = signal('docs');
  protected readonly defaultBranch = signal('main');
  protected readonly saving = signal(false);

  constructor() {
    const first = this.managedCommunities()[0] ?? null;
    this.communityId.set(first?.id ?? null);
    this.loadPlugins(first?.slug);
  }

  setCommunity(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLSelectElement) {
      const id = Number(target.value);
      this.communityId.set(id);
      this.loadPlugins(this.managedCommunities().find((community) => community.id === id)?.slug);
    }
  }

  create(event: SubmitEvent): void {
    event.preventDefault();
    const communityId = this.communityId();

    if (communityId === null) {
      return;
    }

    const payload: PluginCreatePayload = {
      community_id: communityId,
      name: this.name(),
      slug: this.slug(),
      description: this.description() || null,
      github_repo: this.githubRepo(),
      docs_path: this.docsPath(),
      default_branch: this.defaultBranch(),
    };

    this.saving.set(true);
    this.catalog.createPlugin(payload).pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => of(null)),
    ).subscribe((response) => {
      this.saving.set(false);

      if (response === null) {
        return;
      }

      this.plugins.update((plugins) => [response.data, ...plugins]);
      this.name.set('');
      this.slug.set('');
      this.description.set('');
      this.githubRepo.set('');
    });
  }

  sync(plugin: PluginResource): void {
    this.catalog.syncPlugin(plugin.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe();
  }

  private loadPlugins(communitySlug: string | undefined): void {
    if (communitySlug === undefined) {
      return;
    }

    this.catalog.plugins(communitySlug).pipe(
      takeUntilDestroyed(this.destroyRef),
      catchError(() => of({ data: [] })),
    ).subscribe((response) => this.plugins.set(response.data));
  }
}
