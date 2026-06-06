import { Component, input } from '@angular/core';

@Component({
  selector: 'app-ui-skeleton',
  standalone: true,
  template: `
    <span
      class="block animate-pulse rounded-md bg-white/8"
      [style.width]="width()"
      [style.height]="height()"
    ></span>
  `,
})
export class UiSkeletonComponent {
  readonly width = input('100%');
  readonly height = input('1rem');
}
