import { Component, computed, input } from '@angular/core';

import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
type ButtonType = 'button' | 'submit';

@Component({
  selector: 'app-ui-button',
  standalone: true,
  imports: [UiIconComponent],
  template: `
    <button
      class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold tracking-normal transition focus:outline-none focus-visible:ring-2 focus-visible:ring-neon-cyan/70 disabled:cursor-not-allowed disabled:opacity-50"
      [class]="variantClass()"
      [attr.type]="type()"
      [attr.aria-label]="ariaLabel()"
      [disabled]="disabled() || loading()"
    >
      @if (loading()) {
        <app-ui-icon name="loader-circle" [size]="16" [spin]="true" />
      }
      <ng-content />
    </button>
  `,
})
export class UiButtonComponent {
  readonly variant = input<ButtonVariant>('primary');
  readonly type = input<ButtonType>('button');
  readonly disabled = input(false);
  readonly loading = input(false);
  readonly ariaLabel = input<string | null>(null);

  readonly variantClass = computed(() => {
    switch (this.variant()) {
      case 'primary':
        return 'bg-electric-purple text-white shadow-command-glow hover:bg-soft-purple';
      case 'secondary':
        return 'border border-white/10 bg-white/8 text-slate-100 hover:border-neon-cyan/40 hover:bg-neon-cyan/10';
      case 'danger':
        return 'border border-danger/40 bg-danger/10 text-danger hover:bg-danger/15';
      case 'ghost':
        return 'text-neutral-gray hover:bg-white/8 hover:text-white';
    }
  });
}
