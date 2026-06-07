import { expect, Page, test } from '@playwright/test';

async function devLogin(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('E-mail de desenvolvimento').fill('admin@demo');
  await page.getByRole('button', { name: 'Entrar em dev' }).click();
  await expect(page.getByText('admin.demo')).toBeVisible();
}

test('dev login searches a command opens it and favorites it', async ({ page }) => {
  await devLogin(page);

  await page.getByRole('link', { name: 'Busca', exact: true }).click();
  await page.getByLabel('Termo').fill('blood');
  await expect(page.getByText('resultados estimados')).toBeVisible();
  await page.locator('a[href^="/commands/"]').first().click();

  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  await page.getByRole('button', { name: /Favoritar/ }).click();
  await expect(page.getByRole('button', { name: /Remover/ })).toBeVisible();
});

test('dev login opens a plugin document and favorites it', async ({ page }) => {
  await devLogin(page);

  await page.getByRole('link', { name: 'Celem Ecosystem' }).click();
  await page.getByRole('link').filter({ hasText: 'Blood Economy' }).click();
  await expect(page.getByRole('heading', { name: 'Blood Economy' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2 }).first()).toBeVisible();

  if (await page.getByRole('button', { name: /Favoritar/ }).count() > 0) {
    await page.getByRole('button', { name: /Favoritar/ }).click();
  }

  await expect(page.getByRole('button', { name: /Remover/ })).toBeVisible();
});

test('admin triggers sync and sees an ingestion run', async ({ page }) => {
  await devLogin(page);

  await page.getByRole('link', { name: 'Plugins', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Plugins', level: 1 })).toBeVisible();
  await page.getByRole('button', { name: 'Sincronizar' }).first().click();

  await page.getByRole('link', { name: 'Ingestões' }).click();
  await expect(page.getByRole('heading', { name: 'Ingestões' })).toBeVisible();
  await expect(page.getByText(/Run #/).first()).toBeVisible();
});
