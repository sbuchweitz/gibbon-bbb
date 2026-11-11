// Drives Gibbon's 3-step web installer (with demo data) against the harness, then snapshots the DB.
//   node scripts/install-gibbon.mjs
// Addresses from tests/harness/.env; optional ADMIN_USER/ADMIN_PASSWORD.
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { ADMIN, GIBBON_URL, COMPOSE_FILE } from '../support/env.mjs';

async function submit(page) {
  await Promise.all([page.waitForLoadState('load'), page.locator('form input[type=submit], form button[type=submit]').last().click()]);
  const error = page.locator('.error, [class*="bg-red"]').first();
  if (await error.isVisible().catch(() => false)) throw new Error(`Installer error: ${(await error.innerText()).trim()}`);
}

// Sets a form field whatever widget Gibbon renders for it (text, select, or hidden input behind a toggle).
async function setField(page, name, value) {
  const field = page.locator(`[name="${name}"]`).first();
  if ((await field.count()) === 0) return;
  const tag = await field.evaluate((el) => el.tagName);
  if (tag === 'SELECT') {
    const options = await field.locator('option').evaluateAll((els) => els.map((o) => ({ value: o.value, label: o.textContent.trim() })));
    const match = options.find((o) => o.value === value) ?? options.find((o) => o.value.startsWith(value) || o.label.includes(value)) ?? options.find((o) => o.value !== '');
    return field.selectOption(match.value);
  }
  if (await field.isVisible()) return (await field.isEditable()) ? field.fill(value) : undefined; // skip read-only fields
  return field.evaluate((el, v) => { el.value = v; }, value);
}

const compose = (...args) => execFileSync('docker', ['compose', '-f', COMPOSE_FILE, ...args], { maxBuffer: 1 << 30 });

// Start from an empty database and config so the installer always runs from step 1.
function resetEnvironment() {
  compose('exec', '-T', 'db', 'mariadb', '-uroot', '-proot', '-e', 'DROP DATABASE IF EXISTS gibbon; CREATE DATABASE gibbon; GRANT ALL ON gibbon.* TO gibbon@"%";');
  compose('exec', '-T', 'gibbon', 'sh', '-c', ': > /var/www/html/config.php && chown www-data:www-data /var/www/html/config.php');
}

async function install() {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  page.setDefaultTimeout(30_000);
  page.setDefaultNavigationTimeout(180_000);
  try {
    await runWizard(page);
  } catch (err) {
    await page.screenshot({ path: 'test-results/install-failure.png', fullPage: true }).catch(() => {});
    throw err;
  } finally {
    await browser.close();
  }
}

async function runWizard(page) {
  await page.goto(`${GIBBON_URL}/installer/install.php`);
  await submit(page); // Step 1: locale (en_GB default)

  await setField(page, 'databaseServer', 'db');
  await setField(page, 'databaseName', 'gibbon');
  await setField(page, 'databaseUsername', 'gibbon');
  await setField(page, 'databasePassword', 'gibbon');
  await setField(page, 'demoData', 'Y');
  await submit(page); // Step 2: database + demo data

  await setField(page, 'title', 'Mr.');
  await setField(page, 'surname', 'Admin');
  await setField(page, 'firstName', 'Harness');
  await setField(page, 'email', 'admin@harness.test');
  await page.locator('[name=support]').uncheck({ timeout: 2000 }).catch(() => {});
  await setField(page, 'username', ADMIN.username);
  await setField(page, 'passwordNew', ADMIN.password);
  await setField(page, 'passwordConfirm', ADMIN.password);
  await setField(page, 'absoluteURL', GIBBON_URL);
  await setField(page, 'absolutePath', '/var/www/html');
  await setField(page, 'installType', 'Testing');
  await setField(page, 'statsCollection', 'N');
  await setField(page, 'cuttingEdgeCode', 'N');
  await setField(page, 'organisationName', 'Harness School');
  await setField(page, 'organisationNameShort', 'HS');
  await setField(page, 'country', 'United Kingdom');
  await setField(page, 'currency', 'GBP');
  await setField(page, 'timezone', 'UTC');
  await submit(page); // Step 3: admin account + system settings

  const body = await page.locator('body').innerText();
  if (!/installation was successful|successfully installed|Congratulations/i.test(body)) {
    throw new Error(`Installer did not report success:\n${body.slice(0, 1500)}`);
  }
}

function snapshot() {
  const dump = compose('exec', '-T', 'db', 'mariadb-dump', '-uroot', '-proot', '--single-transaction', '--routines', 'gibbon');
  execFileSync('docker', ['compose', '-f', COMPOSE_FILE, 'exec', '-T', 'db', 'sh', '-c', 'cat > /tmp/gibbon-fresh.sql'], { input: dump });
  console.log(`DB snapshot saved in db container: /tmp/gibbon-fresh.sql (${(dump.length / 1e6).toFixed(1)} MB)`);
}

resetEnvironment();
await install();
console.log(`Gibbon installed at ${GIBBON_URL} (admin: ${ADMIN.username})`);
snapshot();
