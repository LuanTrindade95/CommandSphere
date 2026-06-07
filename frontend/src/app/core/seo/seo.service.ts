import { DOCUMENT } from '@angular/common';
import { inject, Injectable } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';

export interface SeoTags {
  title: string;
  description: string;
  canonicalPath?: string;
  image?: string;
  type?: 'website' | 'article';
  jsonLd?: Record<string, unknown>;
}

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  update(tags: SeoTags): void {
    const origin = this.document.location?.origin ?? 'http://localhost:4200';
    const url = tags.canonicalPath === undefined ? origin : new URL(tags.canonicalPath, origin).toString();
    const image = tags.image === undefined ? new URL('/portfolio/og-command-sphere.png', origin).toString() : new URL(tags.image, origin).toString();

    this.title.setTitle(tags.title);
    this.meta.updateTag({ name: 'description', content: tags.description });
    this.meta.updateTag({ name: 'robots', content: 'index,follow' });
    this.meta.updateTag({ property: 'og:title', content: tags.title });
    this.meta.updateTag({ property: 'og:description', content: tags.description });
    this.meta.updateTag({ property: 'og:type', content: tags.type ?? 'website' });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:image', content: image });
    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });
    this.meta.updateTag({ name: 'twitter:title', content: tags.title });
    this.meta.updateTag({ name: 'twitter:description', content: tags.description });
    this.meta.updateTag({ name: 'twitter:image', content: image });
    this.setCanonical(url);
    this.setJsonLd(tags.jsonLd);
  }

  private setCanonical(url: string): void {
    const existing = this.document.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    const link = existing ?? this.document.createElement('link');

    link.setAttribute('rel', 'canonical');
    link.setAttribute('href', url);

    if (existing === null) {
      this.document.head.appendChild(link);
    }
  }

  private setJsonLd(data: Record<string, unknown> | undefined): void {
    const existing = this.document.querySelector<HTMLScriptElement>('script[data-command-sphere-json-ld]');
    existing?.remove();

    if (data === undefined) {
      return;
    }

    const json = JSON.stringify(data).replace(/<\/script/gi, '<\\/script');
    this.document.head.insertAdjacentHTML(
      'beforeend',
      `<script type="application/ld+json" data-command-sphere-json-ld="true">${json}</script>`,
    );
  }
}
