import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-ui-input',
  standalone: true,
  template: `
    <label class="grid gap-2 text-sm text-slate-300">
      @if (label()) {
        <span class="font-medium">{{ label() }}</span>
      }
      <input
        class="h-11 w-full rounded-md border border-white/10 bg-surface-dark/80 px-3 text-sm text-white outline-none transition placeholder:text-neutral-gray/70 focus:border-neon-cyan/60 focus:ring-2 focus:ring-neon-cyan/15"
        [attr.type]="type()"
        [attr.placeholder]="placeholder()"
        [attr.autocomplete]="autocomplete()"
        [value]="value()"
        [disabled]="disabled()"
        (input)="onInput($event)"
      />
      @if (error()) {
        <span class="text-xs font-medium text-danger">{{ error() }}</span>
      }
    </label>
  `,
})
export class UiInputComponent {
  readonly label = input<string | null>(null);
  readonly placeholder = input<string | null>(null);
  readonly error = input<string | null>(null);
  readonly value = input('');
  readonly type = input('text');
  readonly autocomplete = input<string | null>(null);
  readonly disabled = input(false);
  readonly valueChange = output<string>();

  onInput(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLInputElement) {
      this.valueChange.emit(target.value);
    }
  }
}
