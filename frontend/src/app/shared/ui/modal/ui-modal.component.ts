import { Component, input, output } from '@angular/core';

import { UiButtonComponent } from '@app/shared/ui/button/ui-button.component';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

@Component({
  selector: 'app-ui-modal',
  standalone: true,
  imports: [UiButtonComponent, UiIconComponent],
  template: `
    @if (open()) {
      <div class="fixed inset-0 z-50 grid place-items-center bg-deep-space/78 px-4 backdrop-blur-sm" (click)="close.emit()">
        <section
          class="w-full max-w-lg rounded-lg border border-white/10 bg-surface-dark p-5 shadow-command-glow"
          role="dialog"
          aria-modal="true"
          [attr.aria-label]="ariaLabel()"
          (click)="$event.stopPropagation()"
        >
          <header class="mb-4 flex items-center justify-between gap-4">
            <ng-content select="[modal-title]" />
            <app-ui-button variant="ghost" [ariaLabel]="closeLabel()" (click)="close.emit()">
              <app-ui-icon name="x" [size]="18" />
            </app-ui-button>
          </header>
          <ng-content />
        </section>
      </div>
    }
  `,
})
export class UiModalComponent {
  readonly open = input(false);
  readonly ariaLabel = input<string | null>(null);
  readonly closeLabel = input<string | null>(null);
  readonly close = output<void>();
}
