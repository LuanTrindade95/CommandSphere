import { Component, computed, input } from '@angular/core';

const DEFAULT_WIDTH = '100%';
const DEFAULT_HEIGHT = '1rem';

/**
 * Fixed width/height -> Tailwind arbitrary-value class lookups.
 *
 * Every call site in this codebase passes a literal `width`/`height` string
 * (see `ui-skeleton.component.spec.ts` for the enforced set), so sizing is
 * expressed through static utility classes compiled into the stylesheet at
 * build time instead of an inline `style="width:...;height:..."` attribute.
 * This keeps the CSP `style-src-attr` directive free of `unsafe-inline`.
 * Every class token below must appear literally so Tailwind's content
 * scanner generates it; add a new entry here before using a new value.
 */
const WIDTH_CLASSES: Record<string, string> = {
  [DEFAULT_WIDTH]: 'w-full',
  '45%': 'w-[45%]',
  '70%': 'w-[70%]',
  '18rem': 'w-[18rem]',
};

const HEIGHT_CLASSES: Record<string, string> = {
  [DEFAULT_HEIGHT]: 'h-[1rem]',
  '1.25rem': 'h-[1.25rem]',
  '1.75rem': 'h-[1.75rem]',
  '2rem': 'h-[2rem]',
  '4rem': 'h-[4rem]',
  '24rem': 'h-[24rem]',
  '30rem': 'h-[30rem]',
};

@Component({
  selector: 'app-ui-skeleton',
  standalone: true,
  template: `
    <span class="block animate-pulse rounded-md bg-white/8" [class]="sizeClass()"></span>
  `,
})
export class UiSkeletonComponent {
  readonly width = input(DEFAULT_WIDTH);
  readonly height = input(DEFAULT_HEIGHT);

  readonly sizeClass = computed(() => {
    const widthClass = WIDTH_CLASSES[this.width()] ?? WIDTH_CLASSES[DEFAULT_WIDTH];
    const heightClass = HEIGHT_CLASSES[this.height()] ?? HEIGHT_CLASSES[DEFAULT_HEIGHT];

    return `${widthClass} ${heightClass}`;
  });
}
