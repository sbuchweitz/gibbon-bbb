// Fresh Gibbon (snapshot from scripts/install-gibbon.mjs) → install + configure the module through the UI →
// seed personas and lessons → snapshot "bbb-ready" that every spec restores.
import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { ADMIN, BBB_SECRET, BBB_URL, GIBBON_URL } from './support/env.mjs';
import { clickAndLoad, login, restoreSnapshot, saveSnapshot, snapshotExists, sqlValue } from './support/gibbon.mjs';
import { mock } from './support/mock.mjs';
import { pickPersonas, seedLessons } from './support/seed.mjs';

export const FIXTURES_FILE = new URL('./.auth/fixtures.json', import.meta.url);

async function installModule(page) {
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/System Admin/module_manage.php`);
  await clickAndLoad(page, page.locator('a[href*="module_manage_installProcess.php?name=BigBlueButton"]').first());
  const installed = sqlValue("SELECT version FROM gibbonModule WHERE name='BigBlueButton'");
  if (installed !== '2.0.0') throw new Error(`Module install failed (version=${installed}): ${await page.locator('body').innerText()}`);
}

async function configure(page) {
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`);
  await page.locator('[name=enableBigBlueButton]').evaluate((el) => { el.value = 'Y'; el.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.fill('[name=bigBlueButtonURL]', `${BBB_URL}/bigbluebutton/`);
  await page.fill('[name=bigBlueButtonCredentials]', BBB_SECRET);
  await clickAndLoad(page, page.locator('#bigBlueButtonSettings input[type=submit], #bigBlueButtonSettings button[type=submit]').first());
  if (!page.url().includes('return=success0')) throw new Error(`Saving settings failed: ${page.url()}\n${await page.locator('body').innerText()}`);
}

export default async function globalSetup() {
  if (!snapshotExists('gibbon-fresh')) throw new Error('No fresh Gibbon snapshot: run `npm run install-gibbon` first');
  restoreSnapshot('gibbon-fresh');
  await mock.reset();

  const browser = await chromium.launch();
  const page = await browser.newPage();
  try {
    await login(page, ADMIN.username, ADMIN.password);
    await installModule(page);
    await configure(page);
  } finally {
    await browser.close();
  }

  const personas = pickPersonas();
  const lessons = seedLessons(personas.classID);
  mkdirSync(new URL('./.auth/', import.meta.url), { recursive: true });
  writeFileSync(FIXTURES_FILE, JSON.stringify({ personas, lessons }, null, 2));
  saveSnapshot('bbb-ready');
}
