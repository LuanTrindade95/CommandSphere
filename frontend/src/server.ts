import { APP_BASE_HREF } from '@angular/common';
import { CSP_NONCE } from '@angular/core';
import { CommonEngine, isMainModule } from '@angular/ssr/node';
import compression from 'compression';
import express, { type NextFunction, type Request, type Response } from 'express';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import bootstrap from './main.server';
import { buildContentSecurityPolicy, generateCspNonce, resolveRuntimeBrowserConfig } from './server/content-security-policy';

const serverDistFolder = dirname(fileURLToPath(import.meta.url));
const browserDistFolder = resolve(serverDistFolder, '../browser');
const indexHtml = join(serverDistFolder, 'index.server.html');

const app = express();
const commonEngine = new CommonEngine({
  allowedHosts: csv(process.env['COMMANDSPHERE_ALLOWED_HOSTS'] ?? 'localhost,127.0.0.1'),
});

app.use(compression());

app.use((_req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
  next();
});

app.get('/', renderAngular);

app.get('/runtime-config.js', (_req, res) => {
  res.type('application/javascript');
  res.setHeader('Cache-Control', 'no-store');
  res.send(runtimeConfigScript());
});

/**
 * Serve static files from /browser
 */
app.get(
  '**',
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false
  }),
);

/**
 * Handle all other requests by rendering the Angular application.
 */
app.get('**', renderAngular);

function renderAngular(req: Request, res: Response, next: NextFunction): void {
  const { originalUrl, baseUrl } = req;
  const requestUrl = publicUrlFor(originalUrl);
  const nonce = generateCspNonce();

  res.setHeader('Content-Security-Policy', buildContentSecurityPolicy(resolveRuntimeBrowserConfig(process.env, publicOrigin()), nonce));
  // A response carrying a per-request style-src nonce must never be cached,
  // otherwise a cached page would ship a nonce that no longer matches any
  // future request's Content-Security-Policy header.
  res.setHeader('Cache-Control', 'no-store');

  commonEngine
    .render({
      bootstrap,
      documentFilePath: indexHtml,
      url: requestUrl,
      publicPath: browserDistFolder,
      // CommonEngine inlines critical CSS by default on every non-prerendered
      // render, independently of the `angular.json` build-time optimization.
      // That injects a `<style>` block plus a `<link ... onload="...">`
      // attribute, which would require `unsafe-inline` in style-src-elem and
      // script-src-attr. Disabled to keep the nonce-based style-src strict
      // for dynamic routes (e.g. `/c/:slug`, `/p/:slug`, `/commands/:slug`).
      inlineCriticalCss: false,
      providers: [
        { provide: APP_BASE_HREF, useValue: baseUrl },
        { provide: CSP_NONCE, useValue: nonce },
      ],
    })
    .then((html) => res.send(withCspNonceAttribute(postProcessSsr(html, requestUrl), nonce)))
    .catch((err) => next(err));
}

/**
 * Start the server if this module is the main entry point.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url)) {
  const port = process.env['PORT'] || 4000;
  app.listen(port);
}

export default app;

function postProcessSsr(html: string, requestUrl: string): string {
  return withJsonLd(withPublicOriginMetadata(html, requestUrl), requestUrl);
}

/**
 * Stamps the per-request CSP nonce onto the app root element as `ngCspNonce`.
 * Angular's `CSP_NONCE` token defaults to reading this attribute from the
 * document body's descendants, so any `<style>` tag Angular creates at
 * runtime for lazy-loaded route styles (both during this SSR pass and after
 * client hydration) is tagged with a nonce that matches the response's
 * `Content-Security-Policy` header.
 */
function withCspNonceAttribute(html: string, nonce: string): string {
  return html.replace(/<app-root(?![\w-])/, `<app-root ngCspNonce="${nonce}"`);
}

function runtimeConfigScript(): string {
  const config = resolveRuntimeBrowserConfig(process.env, publicOrigin());
  const json = JSON.stringify(config).replace(/<\/script/gi, '<\\/script');

  return `window.__COMMANDSPHERE_CONFIG__ = ${json};`;
}

function publicUrlFor(path: string): string {
  return new URL(path, publicOrigin()).toString();
}

function publicOrigin(): string {
  return process.env['COMMANDSPHERE_PUBLIC_ORIGIN'] ?? 'http://localhost:4200';
}

function csv(value: string): string[] {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function withJsonLd(html: string, requestUrl: string): string {
  const url = new URL(requestUrl);
  if (!html.includes('</head>')) {
    return html;
  }

  const targetHtml = html.replace(/<script[^>]*type="application\/ld\+json"[^>]*>[\s\S]*?<\/script>/gi, '');
  const title = extractTitle(html) ?? 'CommandSphere';
  const description = extractDescription(html) ?? 'Hub inteligente de documentacao para ecossistemas de plugins.';
  const schema = schemaFor(url, title, description);
  const json = JSON.stringify(schema).replace(/<\/script/gi, '<\\/script');
  const script = `<script type="application/ld+json" data-command-sphere-json-ld="true">${json}</script>`;

  return targetHtml.replace('</head>', `${script}</head>`);
}

function withPublicOriginMetadata(html: string, requestUrl: string): string {
  if (!html.includes('</head>')) {
    return html;
  }

  const url = new URL(requestUrl);
  const publicOrigin = url.origin;
  const currentUrl = escapeAttribute(url.toString());
  const currentOgImage = extractMetaContent(html, 'property', 'og:image');
  const currentTwitterImage = extractMetaContent(html, 'name', 'twitter:image') ?? currentOgImage;
  const imageUrl = escapeAttribute(publicAssetUrl(currentOgImage, publicOrigin));
  const twitterImageUrl = escapeAttribute(publicAssetUrl(currentTwitterImage, publicOrigin));

  let next = html;
  next = upsertHeadTag(next, /<link(?=[^>]*\brel=["']canonical["'])[^>]*>/i, `<link rel="canonical" href="${currentUrl}">`);
  next = upsertHeadTag(next, /<meta(?=[^>]*\bproperty=["']og:url["'])[^>]*>/i, `<meta property="og:url" content="${currentUrl}">`);
  next = upsertHeadTag(next, /<meta(?=[^>]*\bproperty=["']og:image["'])[^>]*>/i, `<meta property="og:image" content="${imageUrl}">`);
  next = upsertHeadTag(next, /<meta(?=[^>]*\bname=["']twitter:image["'])[^>]*>/i, `<meta name="twitter:image" content="${twitterImageUrl}">`);

  return next;
}

function upsertHeadTag(html: string, tag: RegExp, replacement: string): string {
  if (tag.test(html)) {
    return html.replace(tag, replacement);
  }

  return html.replace('</head>', `${replacement}</head>`);
}

function extractMetaContent(html: string, attribute: 'name' | 'property', value: string): string | null {
  const escapedValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const tag = new RegExp(`<meta(?=[^>]*\\b${attribute}=["']${escapedValue}["'])[^>]*>`, 'i').exec(html)?.[0];

  return tag === undefined ? null : /content=["']([^"']*)["']/i.exec(tag)?.[1] ?? null;
}

function publicAssetUrl(value: string | null, publicOrigin: string): string {
  const fallbackPath = '/portfolio/og-command-sphere.png';

  if (value === null || value.trim() === '') {
    return new URL(fallbackPath, publicOrigin).toString();
  }

  try {
    const url = new URL(value, publicOrigin);
    return new URL(`${url.pathname}${url.search}${url.hash}`, publicOrigin).toString();
  } catch {
    return new URL(fallbackPath, publicOrigin).toString();
  }
}

function escapeAttribute(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');
}

function schemaFor(url: URL, title: string, description: string): Record<string, unknown> {
  if (url.pathname.startsWith('/commands/')) {
    return {
      '@context': 'https://schema.org',
      '@type': 'SoftwareSourceCode',
      name: title.replace(' - Comando CommandSphere', ''),
      description,
      url: url.toString(),
      programmingLanguage: 'Command',
    };
  }

  if (url.pathname.includes('/p/')) {
    return {
      '@context': 'https://schema.org',
      '@type': 'TechArticle',
      headline: title.replace(' - Documentação CommandSphere', ''),
      description,
      url: url.toString(),
      isPartOf: {
        '@type': 'WebSite',
        name: 'CommandSphere',
      },
    };
  }

  return {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: 'CommandSphere',
    applicationCategory: 'DeveloperApplication',
    operatingSystem: 'Web',
    description,
    url: url.toString(),
  };
}

function extractTitle(html: string): string | null {
  return /<title>(.*?)<\/title>/i.exec(html)?.[1] ?? null;
}

function extractDescription(html: string): string | null {
  return /<meta name="description" content="([^"]*)"/i.exec(html)?.[1] ?? null;
}
