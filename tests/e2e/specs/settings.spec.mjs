// The settings page manages the BBB shared secret: administrators only.
import { expect, test } from '@playwright/test';
import { ADMIN, BBB_SECRET, GIBBON_URL } from '../support/env.mjs';
import { clickAndLoad, login, restoreSnapshot, sql } from '../support/gibbon.mjs';
import { PASSWORD, fixtures } from '../support/personas.mjs';

const SETTINGS = `${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`;

test.beforeAll(() => restoreSnapshot('bbb-ready'));

test('only the Administrator role holds the settings permission', () => {
  const roles = sql(`SELECT r.name FROM gibbonPermission p JOIN gibbonAction a USING (gibbonActionID) JOIN gibbonRole r USING (gibbonRoleID)
    JOIN gibbonModule m ON (m.gibbonModuleID=a.gibbonModuleID) WHERE m.name='BigBlueButton' AND a.name='BigBlueButton Settings'`).map(([r]) => r);
  expect(roles).toEqual(['Administrator']);
});

for (const persona of ['teacher', 'student', 'parent']) {
  test(`${persona} cannot open or post the settings`, async ({ page }) => {
    await login(page, fixtures().personas[persona], PASSWORD);
    await page.goto(SETTINGS);
    await expect(page.getByText('You do not have access to this action.').filter({ visible: true }).first()).toBeVisible();
    expect(await page.content()).not.toContain(BBB_SECRET);

    const response = await page.request.post(`${GIBBON_URL}/modules/BigBlueButton/bbb_settingsProcess.php`, {
      form: { enableBigBlueButton: 'Y', bigBlueButtonURL: 'https://evil.example/bigbluebutton/', joinMode: 'embedded', embeddedAllowWithoutSession: 'Y', recordingHostOverride: '' },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    expect(sql("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='bigBlueButtonURL'")[0][0]).not.toContain('evil.example');
  });
}

test('admin sees the settings without the secret echoed back', async ({ page }) => {
  await login(page, ADMIN.username, ADMIN.password);
  await page.goto(SETTINGS);

  await expect(page.locator('[name=bigBlueButtonURL]')).toHaveValue(/\/bigbluebutton\/$/);
  await expect(page.locator('[name=bigBlueButtonCredentials]')).toHaveAttribute('type', 'password');
  await expect(page.locator('[name=bigBlueButtonCredentials]')).toHaveValue('');
  expect(await page.content()).not.toContain(BBB_SECRET);
});

test('invalid settings are rejected with a message', async ({ page }) => {
  await login(page, ADMIN.username, ADMIN.password);
  await page.goto(SETTINGS);
  await page.fill('[name=recordingHostOverride]', 'javascript:alert(1)');
  await clickAndLoad(page, page.locator('#bigBlueButtonSettings [type=submit]').first());

  await expect(page.getByText('The recording host override must be an origin')).toBeVisible();
  expect(sql("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='recordingHostOverride'")[0]?.[0] ?? '').toBe('');
});
