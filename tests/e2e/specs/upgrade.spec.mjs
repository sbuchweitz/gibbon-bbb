// Upgrade from v1.6.1 (the last release before the rewrite) to 2.0.0 through Gibbon's module updater.
// Also characterises v1.6.1's "recordings disappear after editing the lesson" report.
import { expect, test } from '@playwright/test';
import { existsSync } from 'node:fs';
import { ADMIN, BBB_SECRET, BBB_URL, GIBBON_URL } from '../support/env.mjs';
import { lessonUrl, login, restoreSnapshot, sql, sqlValue } from '../support/gibbon.mjs';
import { mountModule } from '../support/harness.mjs';
import { mock } from '../support/mock.mjs';
import { PASSWORD } from '../support/personas.mjs';
import { createLesson, pickPersonas } from '../support/seed.mjs';

const V161 = '../../.scratch/v161'; // relative to tests/harness; created by scripts/fetch-v161.sh
test.describe.configure({ mode: 'serial', timeout: 240_000 });
test.skip(!existsSync(new URL('../../../.scratch/v161/manifest.php', import.meta.url)), 'Run tests/e2e/scripts/fetch-v161.sh first');

const settingsRoles = () => sql(`SELECT r.name FROM gibbonPermission p JOIN gibbonAction a USING (gibbonActionID) JOIN gibbonRole r USING (gibbonRoleID)
  JOIN gibbonModule m ON (m.gibbonModuleID=a.gibbonModuleID) WHERE m.name='BigBlueButton' AND a.name='BigBlueButton Settings' ORDER BY r.name`).map(([r]) => r);

let personas;
let lessonID;
let recordID;

test.beforeAll(async () => {
  restoreSnapshot('gibbon-fresh');
  await mock.reset();
  await mountModule(V161);
});

test.afterAll(async () => {
  await mountModule('../..');
});

async function asStudent(browser, fn) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await login(page, personas.student, PASSWORD);
  try {
    return await fn(page);
  } finally {
    await context.close();
  }
}

/** v1.6.1 renders recordings as iframes on the lesson page. */
const v1RecordingVisible = (browser) => asStudent(browser, async (page) => {
  await page.goto(lessonUrl(lessonID));
  return (await page.locator(`iframe[src*="${recordID}"]`).count()) > 0;
});

async function teacherSavesLesson(browser, change) {
  const context = await browser.newContext();
  const page = await context.newPage();
  await login(page, personas.teacher, PASSWORD);
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/Planner/planner_edit.php&gibbonPlannerEntryID=${lessonID}&viewBy=class&gibbonCourseClassID=${personas.classID}`);
  if (change) await change(page);
  await Promise.all([page.waitForLoadState('load'), page.locator('form [type=submit]').last().click()]);
  const saved = page.url().includes('return=success');
  await context.close();
  return saved;
}

test('install and use v1.6.1', async ({ page, browser }) => {
  await login(page, ADMIN.username, ADMIN.password);
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/System Admin/module_manage.php`);
  await Promise.all([page.waitForLoadState('load'), page.locator('a[href*="module_manage_installProcess.php?name=BigBlueButton"]').first().click()]);
  expect(sqlValue("SELECT version FROM gibbonModule WHERE name='BigBlueButton'")).toBe('1.6.1');

  await page.goto(`${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`);
  await page.locator('[name=enableBigBlueButton]').evaluate((el) => { el.value = 'Y'; el.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.fill('[name=bigBlueButtonURL]', `${BBB_URL}/bigbluebutton/`);
  await page.fill('[name=bigBlueButtonCredentials]', BBB_SECRET);
  await Promise.all([page.waitForLoadState('load'), page.locator('form [type=submit]').last().click()]);

  personas = pickPersonas();
  const videoChatID = sqlValue("SELECT gibbonCustomFieldID FROM gibbonCustomField WHERE context='Lesson Plan' AND name='Video Chat'");
  const yesterday = new Date(Date.now() - 86_400_000).toISOString().slice(0, 10);
  lessonID = createLesson(personas.classID, 'Upgrade Lesson', yesterday, '09:00:00', '09:45:00', { [videoChatID]: 'Include', videoLink: '' });
  recordID = (await mock.seedRecording(`planner${lessonID}`, ['presentation', 'video'], { startTime: Date.now() - 86_400_000 })).recordID;

  expect(await v1RecordingVisible(browser)).toBe(true);
});

test('v1.6.1 characterisation: what hides recordings', async ({ browser }) => {
  const findings = {};
  findings.unchangedSave = (await teacherSavesLesson(browser)) && (await v1RecordingVisible(browser));

  const originalDate = sqlValue(`SELECT date FROM gibbonPlannerEntry WHERE gibbonPlannerEntryID=${lessonID}`);
  sql(`UPDATE gibbonPlannerEntry SET date=DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE gibbonPlannerEntryID=${lessonID}`);
  findings.movedToLaterDate = await v1RecordingVisible(browser);
  sql(`UPDATE gibbonPlannerEntry SET date='${originalDate}' WHERE gibbonPlannerEntryID=${lessonID}`);

  const fieldsBefore = sqlValue(`SELECT fields FROM gibbonPlannerEntry WHERE gibbonPlannerEntryID=${lessonID}`);
  await teacherSavesLesson(browser, (page) => page.locator('input[type=checkbox][name^="custom"][value="Include"]').uncheck());
  findings.videoChatUnticked = await v1RecordingVisible(browser);
  sql(`UPDATE gibbonPlannerEntry SET fields='${fieldsBefore}' WHERE gibbonPlannerEntryID=${lessonID}`);

  test.info().annotations.push({ type: 'v1.6.1 recordings still visible after', description: JSON.stringify(findings) });
  console.log('v1.6.1 recordings still visible after:', findings);
  expect(await v1RecordingVisible(browser)).toBe(true);
});

test('upgrade to 2.0.0 through the module updater', async ({ page }) => {
  await mountModule('../..');
  await login(page, ADMIN.username, ADMIN.password);
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/System Admin/module_manage.php`);
  const updateLink = page.locator('a[href*="module_manage_update.php"]').filter({ has: page.locator('xpath=self::*') }).first();
  await Promise.all([page.waitForLoadState('load'), updateLink.click()]);
  await Promise.all([page.waitForLoadState('load'), page.locator('form [type=submit]').last().click()]);

  expect(sqlValue("SELECT version FROM gibbonModule WHERE name='BigBlueButton'")).toBe('2.0.0');
  expect(settingsRoles()).toEqual(['Administrator']);
  expect(sql("SHOW TABLES LIKE 'bigBlueButton%'").map(([t]) => t).sort()).toEqual(['bigBlueButtonMeeting', 'bigBlueButtonRecordingCache']);
  expect(Number(sqlValue("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='legacyMaxPlannerEntryID'"))).toBeGreaterThanOrEqual(lessonID);
  expect(sqlValue("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='joinMode'")).toBe('embedded');
  expect(sqlValue("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='bigBlueButtonCredentials'")).toBe(BBB_SECRET);
  expect(sqlValue("SELECT value FROM gibbonSetting WHERE scope='BigBlueButton' AND name='secretRotationRequired'")).toBe('Y');

  await page.goto(`${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`);
  await expect(page.getByText('please replace the BigBlueButton shared secret')).toBeVisible();
  await expect(page.locator('[name=bigBlueButtonCredentials]')).toHaveValue('');
  expect(await page.content()).not.toContain(BBB_SECRET);
});

test('after the upgrade, pre-upgrade recordings show as buttons', async ({ browser }) => {
  await asStudent(browser, async (page) => {
    await page.goto(lessonUrl(lessonID));
    const section = page.locator('[data-bbb=recordings]');
    await expect(section.locator('.bbb-recording')).toHaveCount(1);
    await expect(section.locator('a')).toHaveText(['Presentation', 'Video']);
    await expect(page.locator(`iframe[src*="${recordID}"]`)).toHaveCount(0);
  });
});
