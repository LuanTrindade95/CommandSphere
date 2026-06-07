import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslocoPipe } from '@jsverse/transloco';

import { SeoService } from '@app/core/seo/seo.service';
import { UiBadgeComponent } from '@app/shared/ui/badge/ui-badge.component';
import { IconName, UiIconComponent } from '@app/shared/ui/icon/ui-icon.component';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink, TranslocoPipe, UiBadgeComponent, UiIconComponent],
  template: `
    <section class="relative isolate grid min-h-[calc(100vh-7rem)] content-center overflow-hidden py-10">
      <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top_left,rgb(124_58_237_/_22%),transparent_34%),linear-gradient(180deg,rgb(2_6_23),rgb(2_6_23_/_82%))]"></div>
      <img
        class="absolute inset-x-0 bottom-0 -z-10 hidden h-[58%] w-full object-cover opacity-34 mix-blend-screen md:block"
        src="/portfolio/landing-hero.png"
        width="1200"
        height="675"
        fetchpriority="high"
        [alt]="'home.heroImageAlt' | transloco"
      />
      <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_28rem] lg:items-center">
        <div class="grid max-w-4xl gap-6">
          <app-ui-badge tone="cyan">{{ 'home.badge' | transloco }}</app-ui-badge>
          <div class="grid gap-4">
            <h1 class="max-w-5xl font-display text-4xl font-semibold leading-tight text-white sm:text-6xl">
              {{ 'home.title' | transloco }}
            </h1>
            <p class="max-w-3xl text-lg leading-8 text-slate-300">
              {{ 'home.summary' | transloco }}
            </p>
          </div>
          <div class="flex flex-wrap gap-3">
            <a class="inline-flex h-11 items-center gap-2 rounded-md bg-neon-cyan px-4 text-sm font-semibold text-deep-space" routerLink="/search">
              <app-ui-icon name="search" [size]="17" />
              {{ 'home.primaryCta' | transloco }}
            </a>
            <a class="inline-flex h-11 items-center gap-2 rounded-md border border-white/12 bg-white/6 px-4 text-sm font-semibold text-white" routerLink="/c/celem-ecosystem">
              <app-ui-icon name="book-open" [size]="17" />
              {{ 'home.secondaryCta' | transloco }}
            </a>
          </div>
        </div>

        <aside class="grid gap-3 rounded-lg border border-white/10 bg-deep-space/72 p-4 backdrop-blur">
          @for (metric of metrics; track metric.valueKey) {
            <div class="grid grid-cols-[auto_1fr] gap-3 rounded-md border border-white/8 bg-white/5 p-3">
              <app-ui-icon class="text-neon-cyan" [name]="metric.icon" [size]="18" />
              <div>
                <p class="text-sm font-semibold text-white">{{ metric.valueKey | transloco }}</p>
                <p class="text-xs leading-5 text-neutral-gray">{{ metric.labelKey | transloco }}</p>
              </div>
            </div>
          }
        </aside>
      </div>
    </section>

    <section class="grid gap-5 py-12">
      <div class="grid gap-2">
        <app-ui-badge tone="purple">{{ 'home.featuresBadge' | transloco }}</app-ui-badge>
        <h2 class="font-display text-2xl font-semibold text-white">{{ 'home.featuresTitle' | transloco }}</h2>
      </div>
      <div class="grid gap-4 md:grid-cols-3">
        @for (feature of features; track feature.titleKey) {
          <article class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/76 p-5">
            <app-ui-icon class="text-neon-cyan" [name]="feature.icon" [size]="22" />
            <h3 class="font-display text-base font-semibold text-white">{{ feature.titleKey | transloco }}</h3>
            <p class="text-sm leading-6 text-neutral-gray">{{ feature.descriptionKey | transloco }}</p>
          </article>
        }
      </div>
    </section>

    <section class="grid gap-5 py-12">
      <div class="grid gap-2">
        <app-ui-badge tone="cyan">{{ 'home.pipelineBadge' | transloco }}</app-ui-badge>
        <h2 class="font-display text-2xl font-semibold text-white">{{ 'home.pipelineTitle' | transloco }}</h2>
      </div>
      <div class="grid gap-3 rounded-lg border border-white/10 bg-surface-dark/72 p-4 md:grid-cols-4">
        @for (step of pipeline; track step.titleKey; let last = $last) {
          <div class="grid gap-3">
            <div class="flex items-center gap-3">
              <span class="grid h-10 w-10 place-items-center rounded-md border border-neon-cyan/30 bg-neon-cyan/10 text-neon-cyan">
                <app-ui-icon [name]="step.icon" [size]="18" />
              </span>
              @if (!last) {
                <span class="hidden h-px flex-1 bg-neon-cyan/28 md:block"></span>
              }
            </div>
            <div>
              <h3 class="text-sm font-semibold text-white">{{ step.titleKey | transloco }}</h3>
              <p class="mt-1 text-xs leading-5 text-neutral-gray">{{ step.descriptionKey | transloco }}</p>
            </div>
          </div>
        }
      </div>
    </section>

    <section class="grid gap-5 py-12">
      <div class="grid gap-2">
        <app-ui-badge tone="neutral">{{ 'home.stackBadge' | transloco }}</app-ui-badge>
        <h2 class="font-display text-2xl font-semibold text-white">{{ 'home.stackTitle' | transloco }}</h2>
      </div>
      <div class="flex flex-wrap gap-2">
        @for (item of stack; track item) {
          <span class="rounded-full border border-white/10 bg-white/6 px-3 py-2 text-sm font-semibold text-slate-200">{{ item }}</span>
        }
      </div>
    </section>

    <section class="grid gap-5 py-12">
      <div class="grid gap-2">
        <app-ui-badge tone="purple">{{ 'home.screenshotsBadge' | transloco }}</app-ui-badge>
        <h2 class="font-display text-2xl font-semibold text-white">{{ 'home.screenshotsTitle' | transloco }}</h2>
      </div>
      <div class="grid gap-4 lg:grid-cols-3">
        @for (shot of screenshots; track shot.src) {
          <figure class="overflow-hidden rounded-lg border border-white/10 bg-surface-dark/72">
            <img class="aspect-[16/10] w-full object-cover" [src]="shot.src" [alt]="shot.altKey | transloco" width="960" height="600" loading="lazy" decoding="async" />
            <figcaption class="border-t border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">{{ shot.titleKey | transloco }}</figcaption>
          </figure>
        }
      </div>
    </section>
  `,
})
export class HomePageComponent {
  private readonly seo = inject(SeoService);

  protected readonly metrics: { icon: IconName; valueKey: string; labelKey: string }[] = [
    { icon: 'database', valueKey: 'home.metrics.idempotent.value', labelKey: 'home.metrics.idempotent.label' },
    { icon: 'zap', valueKey: 'home.metrics.realtime.value', labelKey: 'home.metrics.realtime.label' },
    { icon: 'shield-check', valueKey: 'home.metrics.permissions.value', labelKey: 'home.metrics.permissions.label' },
  ];
  protected readonly features: { icon: IconName; titleKey: string; descriptionKey: string }[] = [
    { icon: 'git-branch', titleKey: 'home.features.ingestion.title', descriptionKey: 'home.features.ingestion.description' },
    { icon: 'search', titleKey: 'home.features.discovery.title', descriptionKey: 'home.features.discovery.description' },
    { icon: 'activity', titleKey: 'home.features.operations.title', descriptionKey: 'home.features.operations.description' },
  ];
  protected readonly pipeline: { icon: IconName; titleKey: string; descriptionKey: string }[] = [
    { icon: 'github', titleKey: 'home.pipeline.github.title', descriptionKey: 'home.pipeline.github.description' },
    { icon: 'file-code', titleKey: 'home.pipeline.parser.title', descriptionKey: 'home.pipeline.parser.description' },
    { icon: 'database', titleKey: 'home.pipeline.index.title', descriptionKey: 'home.pipeline.index.description' },
    { icon: 'search', titleKey: 'home.pipeline.search.title', descriptionKey: 'home.pipeline.search.description' },
  ];
  protected readonly stack = ['Angular SSR', 'Laravel 12', 'GitHub API', 'Discord OAuth2', 'Meilisearch', 'Reverb', 'Redis', 'MySQL'];
  protected readonly screenshots = [
    { src: '/portfolio/command-palette.png', altKey: 'home.screenshots.paletteAlt', titleKey: 'home.screenshots.paletteTitle' },
    { src: '/portfolio/doc-viewer.png', altKey: 'home.screenshots.docsAlt', titleKey: 'home.screenshots.docsTitle' },
    { src: '/portfolio/search-page.png', altKey: 'home.screenshots.searchAlt', titleKey: 'home.screenshots.searchTitle' },
  ];

  constructor() {
    this.seo.update({
      title: 'CommandSphere - Documentação de plugins em tempo real',
      description: 'Hub SSR dark-first para ingerir Markdown do GitHub, extrair comandos, indexar no Meilisearch e operar documentação de plugins com permissões por comunidade.',
      canonicalPath: '/',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'CommandSphere',
        applicationCategory: 'DeveloperApplication',
        operatingSystem: 'Web',
        description: 'Hub de documentação inteligente para ecossistemas de plugins.',
      },
    });
  }
}
