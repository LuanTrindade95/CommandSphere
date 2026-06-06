import { Component } from '@angular/core';
import { TranslocoPipe } from '@jsverse/transloco';

import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { UiCardComponent } from '@app/shared/ui/card/ui-card.component';
import { UiCodeBlockComponent } from '@app/shared/ui/code-block/ui-code-block.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';
import { UiSkeletonComponent } from '@app/shared/ui/skeleton/ui-skeleton.component';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [TranslocoPipe, UiBadgeComponent, UiCardComponent, UiCodeBlockComponent, UiIconComponent, UiSkeletonComponent],
  template: `
    <section class="grid gap-6 lg:grid-cols-[1fr_24rem]">
      <div class="grid content-start gap-6">
        <section class="rounded-lg border border-white/10 bg-[linear-gradient(135deg,rgb(17_24_39_/_92%),rgb(2_6_23_/_96%))] p-6 shadow-command-glow">
          <app-ui-badge tone="cyan">{{ 'home.badge' | transloco }}</app-ui-badge>
          <div class="mt-5 grid gap-3">
            <h1 class="font-display text-3xl font-semibold tracking-normal text-white sm:text-4xl">
              {{ 'home.title' | transloco }}
            </h1>
            <p class="max-w-3xl text-base leading-7 text-neutral-gray">
              {{ 'home.summary' | transloco }}
            </p>
          </div>
        </section>

        <div class="grid gap-4 md:grid-cols-3">
          @for (item of stackItems; track item) {
            <app-ui-card>
              <div class="grid gap-3">
                <app-ui-icon class="text-neon-cyan" name="terminal" [size]="20" />
                <h2 class="text-sm font-semibold text-white">{{ item + '.title' | transloco }}</h2>
                <p class="text-sm leading-6 text-neutral-gray">{{ item + '.description' | transloco }}</p>
              </div>
            </app-ui-card>
          }
        </div>
      </div>

      <aside class="grid content-start gap-4">
        <app-ui-card>
          <div class="grid gap-4">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-sm font-semibold text-white">{{ 'home.foundationStatus' | transloco }}</h2>
              <app-ui-badge tone="success">{{ 'home.ready' | transloco }}</app-ui-badge>
            </div>
            <div class="grid gap-2">
              <app-ui-skeleton height="0.75rem" width="72%" />
              <app-ui-skeleton height="0.75rem" width="52%" />
              <app-ui-skeleton height="0.75rem" width="64%" />
            </div>
          </div>
        </app-ui-card>

        <app-ui-code-block [code]="'home.codePreview' | transloco" />
      </aside>
    </section>
  `,
})
export class HomePageComponent {
  protected readonly stackItems = ['home.stack.angular', 'home.stack.laravel', 'home.stack.search'];
}
