import { Component, inject } from '@angular/core';
import { TranslocoPipe } from '@jsverse/transloco';

import { ToastService } from '@app/core/toast/toast.service';
import { UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

@Component({
  selector: 'app-ui-toast-container',
  standalone: true,
  imports: [TranslocoPipe, UiIconComponent],
  template: `
    <div class="fixed right-4 top-4 z-50 grid w-[min(24rem,calc(100vw-2rem))] gap-3">
      @for (message of toast.messages(); track message.id) {
        <button
          type="button"
          class="grid grid-cols-[auto_1fr] items-center gap-3 rounded-lg border bg-surface-dark/95 px-4 py-3 text-left text-sm shadow-command-glow backdrop-blur"
          [class.border-danger]="message.tone === 'danger'"
          [class.border-success]="message.tone === 'success'"
          [class.border-neon-cyan]="message.tone === 'info'"
          (click)="toast.dismiss(message.id)"
        >
          <app-ui-icon [name]="message.tone === 'danger' ? 'alert-triangle' : 'check'" [size]="18" />
          <span class="font-medium text-slate-100">{{ message.translationKey | transloco }}</span>
        </button>
      }
    </div>
  `,
})
export class UiToastContainerComponent {
  protected readonly toast = inject(ToastService);
}
