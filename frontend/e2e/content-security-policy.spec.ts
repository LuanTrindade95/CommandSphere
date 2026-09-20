import { expect, Page, request, test } from '@playwright/test';

/**
 * Every prerendered HTML file Angular writes to disk, plus the dynamically
 * generated runtime config script. All of these are full documents (or, for
 * `/runtime-config.js`, a script the document depends on) reachable directly
 * over HTTP in production (`docker-compose.prod.yml` publishes the Express
 * server with no reverse proxy in front), so each one must carry the same
 * strict CSP as every SSR-rendered route, and never a long-lived cache.
 */
const PRERENDERED_HTML_PATHS = [
  '/index.html',
  '/search/index.html',
  '/login/index.html',
  '/analytics/index.html',
  '/favorites/index.html',
  '/admin/plugins/index.html',
  '/admin/ingestions/index.html',
  '/index.csr.html',
];

interface CspViolation {
  directive: string;
  blockedURI: string;
  path: string;
}

/**
 * Installs a `securitypolicyviolation` collector before any page script runs,
 * so a violation triggered during hydration or a lazy route load is captured
 * even though `page.evaluate` only reads it back afterwards.
 */
async function collectCspViolations(page: Page): Promise<() => Promise<CspViolation[]>> {
  await page.addInitScript(() => {
    (window as unknown as { __cspViolations: CspViolation[] }).__cspViolations = [];
    document.addEventListener('securitypolicyviolation', (event) => {
      (window as unknown as { __cspViolations: CspViolation[] }).__cspViolations.push({
        directive: event.violatedDirective,
        blockedURI: event.blockedURI,
        path: window.location.pathname,
      });
    });
  });

  return () => page.evaluate(() => (window as unknown as { __cspViolations: CspViolation[] }).__cspViolations);
}

async function devLogin(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('E-mail de desenvolvimento').fill('admin@demo');
  await page.getByRole('button', { name: 'Entrar em dev' }).click();
  await expect(page.getByRole('link', { name: 'Plugins', exact: true })).toBeVisible();
}

test('the login page response carries a strict, applied Content-Security-Policy', async ({ page }) => {
  const response = await page.goto('/login');
  const csp = response?.headers()['content-security-policy'] ?? '';

  expect(csp).toContain("default-src 'self'");
  expect(csp).toMatch(/style-src 'self' 'nonce-[^']+'/);
  expect(csp).not.toContain('unsafe-inline');
  expect(csp).not.toContain('unsafe-eval');
  expect(response?.headers()['content-security-policy-report-only']).toBeUndefined();
});

test('every prerendered HTML file and runtime-config.js carry the strict CSP and are never cached', async ({ baseURL }) => {
  const context = await request.newContext({ baseURL });

  for (const path of [...PRERENDERED_HTML_PATHS, '/runtime-config.js']) {
    const response = await context.get(path);
    const headers = response.headers();

    expect(response.status(), `${path} status`).toBe(200);
    expect(headers['content-security-policy'], `${path} missing CSP`).toBeDefined();
    expect(headers['content-security-policy'], `${path} allows unsafe-inline`).not.toContain('unsafe-inline');
    expect(headers['cache-control'], `${path} cache-control`).toContain('no-store');
  }

  for (const path of PRERENDERED_HTML_PATHS) {
    const response = await context.get(path);
    const csp = response.headers()['content-security-policy'] ?? '';

    expect(csp, `${path} script-src`).toMatch(/script-src 'self'/);
    expect(csp, `${path} style-src nonce`).toMatch(/style-src 'self' 'nonce-[^']+'/);
  }

  await context.dispose();
});

test('an inline script and an <img onerror> injected into a prerendered HTML file do not execute', async ({ page, baseURL }) => {
  for (const path of ['/index.html', '/index.csr.html']) {
    await page.route('**' + path, async (route) => {
      const response = await route.fetch();
      const body = (await response.text()).replace(
        '</body>',
        `<script>window.__cspAuditMarker = 'executed';</script><img src="x" onerror="window.__cspAuditImgFired = true">` +
          '</body>',
      );

      await route.fulfill({ response, body, headers: response.headers() });
    });

    const readViolations = await collectCspViolations(page);

    await page.goto(new URL(path, baseURL).toString(), { waitUntil: 'networkidle' });

    const marker = await page.evaluate(() => (window as unknown as { __cspAuditMarker?: string }).__cspAuditMarker);
    const imgFired = await page.evaluate(() => (window as unknown as { __cspAuditImgFired?: boolean }).__cspAuditImgFired);
    const violations = await readViolations();

    expect(marker, `${path} inline script executed`).toBeUndefined();
    expect(imgFired, `${path} inline onerror executed`).toBeUndefined();
    expect(violations.length, `${path} expected a reported CSP violation`).toBeGreaterThan(0);

    await page.unroute('**' + path);
  }
});

test('hydration, search, the command palette, the doc viewer, and admin realtime status raise no CSP violation', async ({ page }) => {
  const readViolations = await collectCspViolations(page);

  await devLogin(page);

  // Command palette (Ctrl+K equivalent trigger button).
  await page.getByRole('button', { name: 'Abrir busca global' }).click();
  await page.getByPlaceholder('Buscar comando, sintaxe ou plugin').fill('balance');
  await expect(page.getByText(/resultados estimados/)).toBeVisible();
  await page.locator('.fixed.inset-0.z-40').click({ position: { x: 5, y: 5 } });
  await expect(page.getByRole('dialog')).toBeHidden();

  // Search page.
  await page.getByRole('link', { name: 'Busca', exact: true }).click();
  await page.getByLabel('Termo').fill('balance');
  await expect(page.getByText('resultados estimados')).toBeVisible();

  // Command detail page.
  await page.locator('a[href^="/commands/"]').first().click();
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

  // Document viewer.
  await page.getByRole('link', { name: 'Celem Ecosystem' }).click();
  await page.getByRole('link').filter({ hasText: 'Blood Economy' }).click();
  await expect(page.getByRole('heading', { name: 'Blood Economy' })).toBeVisible();

  // Admin realtime ingestion status.
  await page.getByRole('link', { name: 'Plugins', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Plugins', level: 1 })).toBeVisible();
  await page.getByRole('link', { name: /Ingest/ }).click();
  await expect(page.getByRole('heading', { name: /Ingest/ })).toBeVisible();

  const violations = await readViolations();

  expect(violations).toEqual([]);
});
