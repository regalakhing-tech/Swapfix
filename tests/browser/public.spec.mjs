import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const publicPages = [
  { route: '/', heading: /Give good tech/, navLabel: 'Home' },
  { route: '/market', heading: 'Find a gadget worth keeping.', navLabel: 'Buy & Sell' },
  { route: '/exchange', heading: 'Trade the tech you have.', navLabel: 'Exchange' },
  { route: '/repair', heading: 'Find a technician.', navLabel: 'Repair' },
  { route: '/login', heading: 'Sign in to SwapFix' },
  { route: '/register', heading: 'Create your account' },
];

for (const { route, heading, navLabel } of publicPages) {
  test(`${route} renders with working assets and navigation`, async ({ page }, testInfo) => {
    const pageErrors = [];
    const failedAssets = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('response', response => {
      if (new URL(response.url()).pathname.startsWith('/assets/') && response.status() >= 400) {
        failedAssets.push(`${response.status()} ${response.url()}`);
      }
    });

    const response = await page.goto(route);
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading);
    await expect(page.getByRole('link', { name: 'SwapFix home' }).first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);

    const mobile = testInfo.project.name !== 'desktop';
    const navigation = page.getByRole('navigation', { name: mobile ? 'Quick navigation' : 'Main navigation' });
    await expect(navigation).toBeVisible();
    for (const { navLabel: label, route: href } of publicPages.filter(entry => entry.navLabel)) {
      await expect(navigation.getByRole('link', { name: label, exact: true })).toHaveAttribute('href', href);
    }
    if (navLabel) {
      await expect(navigation.getByRole('link', { name: navLabel, exact: true })).toHaveAttribute('aria-current', 'page');
    }
    expect(pageErrors).toEqual([]);
    expect(failedAssets).toEqual([]);
  });

  test(`${route} has no automatically detected WCAG A or AA violations`, async ({ page }) => {
    await page.goto(route);
    const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(violations.map(({ id, nodes }) => ({ id, nodes: nodes.map(node => node.target) }))).toEqual([]);
  });
}

test('account forms support keyboard validation and the password visibility control', async ({ page }) => {
  await page.goto('/register?type=technician');
  const form = page.locator('form[action="/register"]');
  await expect(form.getByRole('radio', { name: /Technician/ })).toBeChecked();
  await expect(form.getByLabel('Email', { exact: true })).toHaveAttribute('autocomplete', 'email');
  await expect(form.getByLabel('Password', { exact: true })).toHaveAttribute('autocomplete', 'new-password');
  expect(await form.evaluate(element => element.checkValidity())).toBe(false);
  await form.getByRole('button', { name: 'Show password' }).focus();
  await page.keyboard.press('Enter');
  await expect(form.getByLabel('Password', { exact: true })).toHaveAttribute('type', 'text');
  await expect(form.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true');
  await page.keyboard.press('Space');
  await expect(form.getByLabel('Password', { exact: true })).toHaveAttribute('type', 'password');
});

test('market search retains the query and publishing routes lead to sign in', async ({ page }) => {
  await page.goto('/market');
  const search = page.getByRole('search');
  await search.getByRole('searchbox').fill('SwapFix browser test');
  await search.getByRole('button', { name: 'Search' }).click();
  await expect(page).toHaveURL(/\/market\?[^#]*q=SwapFix\+browser\+test/);
  await expect(search.getByRole('searchbox')).toHaveValue('SwapFix browser test');
  await page.getByRole('link', { name: 'List a gadget' }).click();
  await expect(page).toHaveURL(/\/login\?next=%2Fmarket%2Fnew/);
  await expect(page.getByRole('heading', { name: 'Sign in to SwapFix' })).toBeVisible();
});
