import { APP_BASE_HREF } from '@angular/common';
import { CommonEngine, isMainModule } from '@angular/ssr/node';
import express from 'express';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import bootstrap from './main.server';

const serverDistFolder = dirname(fileURLToPath(import.meta.url));
const browserDistFolder = resolve(serverDistFolder, '../browser');
const indexHtml = join(serverDistFolder, 'index.server.html');

const app = express();
const commonEngine = new CommonEngine({
  allowedHosts: ['localhost', '127.0.0.1'],
});

app.use((_req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
  next();
});

/**
 * Serve static files from /browser
 */
app.get(
  '**',
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: 'index.html'
  }),
);

/**
 * Handle all other requests by rendering the Angular application.
 */
app.get('**', (req, res, next) => {
  const { protocol, originalUrl, baseUrl, headers } = req;

  commonEngine
    .render({
      bootstrap,
      documentFilePath: indexHtml,
      url: `${protocol}://${headers.host}${originalUrl}`,
      publicPath: browserDistFolder,
      providers: [{ provide: APP_BASE_HREF, useValue: baseUrl }],
    })
    .then((html) => res.send(withJsonLd(html, `${protocol}://${headers.host}${originalUrl}`)))
    .catch((err) => next(err));
});

/**
 * Start the server if this module is the main entry point.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url)) {
  const port = process.env['PORT'] || 4000;
  app.listen(port);
}

export default app;

function withJsonLd(html: string, requestUrl: string): string {
  const url = new URL(requestUrl);
  const isCommandPage = url.pathname.startsWith('/commands/');
  const shouldReplaceCommandSchema = isCommandPage && !html.includes('SoftwareSourceCode');

  if (!shouldReplaceCommandSchema && html.includes('application/ld+json')) {
    return html;
  }

  if (!html.includes('</head>')) {
    return html;
  }

  const targetHtml = isCommandPage
    ? html.replace(/<script[^>]*type="application\/ld\+json"[^>]*>[\s\S]*?<\/script>/gi, '')
    : html;
  const title = extractTitle(html) ?? 'CommandSphere';
  const description = extractDescription(html) ?? 'Hub inteligente de documentacao para ecossistemas de plugins.';
  const schema = schemaFor(url, title, description);
  const json = JSON.stringify(schema).replace(/<\/script/gi, '<\\/script');
  const script = `<script type="application/ld+json" data-command-sphere-json-ld="true">${json}</script>`;

  return targetHtml.replace('</head>', `${script}</head>`);
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
