import { Component, computed, input } from '@angular/core';

type BadgeTone = 'purple' | 'cyan' | 'neutral' | 'success' | 'danger';

@Component({
  selector: 'app-ui-badge',
  standalone: true,
  template: `
    <span
      class="inline-flex h-7 items-center rounded-full border px-2.5 text-xs font-semibold tracking-normal"
      [class]="toneClass()"
    >
      <ng-content />
    </span>
  `,
})
export class UiBadgeComponent {
  readonly tone = input<BadgeTone>('neutral');

  readonly toneClass = computed(() => {
    switch (this.tone()) {
      case 'purple':
        return 'border-electric-purple/40 bg-electric-purple/12 text-soft-purple';
      case 'cyan':
        return 'border-neon-cyan/40 bg-neon-cyan/10 text-neon-cyan';
      case 'success':
        return 'border-success/40 bg-success/10 text-success';
      case 'danger':
        return 'border-danger/40 bg-danger/10 text-danger';
      case 'neutral':
        return 'border-white/10 bg-white/6 text-neutral-gray';
    }
  });
}
