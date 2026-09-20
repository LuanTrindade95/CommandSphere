import { expect, Page, test } from '@playwright/test';

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
