import { Component } from '@angular/core';

@Component({
  selector: 'app-ui-card',
  standalone: true,
  template: `
    <section class="rounded-lg border border-white/10 bg-surface-dark/72 p-5 shadow-[0_18px_60px_rgb(2_6_23_/_36%)]">
      <ng-content />
    </section>
  `,
})
export class UiCardComponent {}
