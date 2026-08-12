import { Component, input } from '@angular/core';

import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

@Component({
  selector: 'app-ui-empty-state',
  standalone: true,
  imports: [UiIconComponent],
  template: `
    <section class="grid gap-3 rounded-lg border border-dashed border-white/12 bg-white/4 p-6 text-center">
      <app-ui-icon class="mx-auto text-neutral-gray" [name]="icon()" [size]="24" />
      <div class="grid gap-1">
        <h2 class="text-sm font-semibold text-slate-100">{{ title() }}</h2>
        <p class="text-sm leading-6 text-neutral-gray">{{ description() }}</p>
      </div>
    </section>
  `,
})
export class UiEmptyStateComponent {
  readonly title = input.required<string>();
  readonly description = input.required<string>();
  readonly icon = input<'search' | 'file-text' | 'terminal'>('search');
}
