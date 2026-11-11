// Live class: the lesson page only links to it; the class opens in a new tab (Gibbon page + BBB iframe).
import { expect, test } from '@playwright/test';
import { BBB_URL, GIBBON_URL } from '../support/env.mjs';
import { lessonUrl, login, restoreSnapshot, sql, sqlValue } from '../support/gibbon.mjs';
import { mock } from '../support/mock.mjs';
import { PASSWORD, fixtures } from '../support/personas.mjs';

const { personas, lessons } = fixtures();
const liveUrl = (id, extra = '') => `${GIBBON_URL}/modules/BigBlueButton/bbb_live.php?gibbonPlannerEntryID=${id}${extra}`;

test.beforeEach(async () => {
  restoreSnapshot('bbb-ready');
  await mock.reset();
});

async function openJoinTab(page) {
  const joinLink = page.getByRole('link', { name: 'Join Class (New Tab)' });
  await expect(joinLink).toHaveAttribute('target', '_blank');
  const [tab] = await Promise.all([page.context().waitForEvent('page'), joinLink.click()]);
  await tab.waitForLoadState('load');
  return tab;
}

test('lesson page shows a Join button and never embeds the class', async ({ page }) => {
  await login(page, personas.teacher, PASSWORD);
  await page.goto(lessonUrl(lessons.live));

  await expect(page.locator('[data-bbb=live]')).toBeVisible();
  await expect(page.locator(`[data-bbb] iframe, iframe[src^="${BBB_URL}"]`)).toHaveCount(0); // core's own editors may use iframes
  expect((await mock.state()).requests.filter((r) => r.call !== '(index)')).toEqual([]); // no BBB calls on lesson view
});

test('teacher joins as moderator; meeting records only when started, popups allowed', async ({ page }) => {
  await login(page, personas.teacher, PASSWORD);
  await page.goto(lessonUrl(lessons.live));
  const tab = await openJoinTab(page);

  expect(tab.url()).toContain('/modules/BigBlueButton/bbb_live.php');
  const frame = tab.locator('iframe.bbb-frame');
  await expect(frame).toHaveAttribute('sandbox', /allow-popups allow-popups-to-escape-sandbox/);
  const allow = await frame.getAttribute('allow');
  expect(allow).toContain('camera');
  expect(allow).not.toContain('*');

  const client = tab.frameLocator('iframe.bbb-frame');
  await expect(client.locator('#status')).toHaveAttribute('data-state', 'joined');
  await expect(client.locator('#status')).toHaveAttribute('data-role', 'MODERATOR');

  const create = await mock.lastRequest('create');
  expect(create.params).toMatchObject({ record: 'true', autoStartRecording: 'false', allowStartStopRecording: 'true', allowRequestsWithoutSession: 'true', 'meta_gibbon-planner-entry': String(lessons.live) });
  expect(create.params.meetingID).toMatch(new RegExp(`^gibbon-[0-9a-f]{12}-${lessons.live}-[0-9a-f]{8}$`));
  expect(sqlValue(`SELECT meetingID FROM bigBlueButtonMeeting WHERE gibbonPlannerEntryID=${lessons.live}`)).toBe(create.params.meetingID);

  const [popup] = await Promise.all([tab.waitForEvent('popup'), client.locator('#dashboard').click()]);
  await expect(popup.locator('#dashboard-title')).toContainText(create.params.meetingID);
});

test('student waits for the teacher, then joins automatically as viewer', async ({ page, browser }) => {
  await login(page, personas.student, PASSWORD);
  await page.goto(lessonUrl(lessons.live));
  const tab = await openJoinTab(page);
  await expect(tab.getByText('Waiting for the teacher to start the class')).toBeVisible();
  expect((await mock.state()).meetings).toEqual([]);

  const teacherContext = await browser.newContext();
  const teacher = await teacherContext.newPage();
  await login(teacher, personas.teacher, PASSWORD);
  await teacher.goto(liveUrl(lessons.live));
  await expect(teacher.frameLocator('iframe.bbb-frame').locator('#status')).toHaveAttribute('data-role', 'MODERATOR');
  await teacherContext.close();

  await expect(tab.frameLocator('iframe.bbb-frame').locator('#status')).toHaveAttribute('data-role', 'VIEWER', { timeout: 30_000 });
});

test('people outside the class are refused, even with the URL', async ({ page }) => {
  await login(page, personas.outsider, PASSWORD);
  const response = await page.goto(liveUrl(lessons.live));

  expect(response?.status()).toBe(403);
  await expect(page.getByText('you do not have access to it')).toBeVisible();
});

test('parents have no live-class permission on a fresh install', async ({ page }) => {
  await login(page, personas.parent, PASSWORD);
  await page.goto(lessonUrl(lessons.live, `&search=${personas.studentID}`));
  await expect(page.locator('[data-bbb=live]')).toHaveCount(0);

  const response = await page.goto(liveUrl(lessons.live, `&search=${personas.studentID}`));
  expect(response?.status()).toBe(403);
});

test('lessons without Video Chat, and future lessons', async ({ page }) => {
  await login(page, personas.teacher, PASSWORD);
  await page.goto(lessonUrl(lessons.plain));
  await expect(page.locator('[data-bbb]')).toHaveCount(0);
  expect((await page.goto(liveUrl(lessons.plain)))?.status()).toBe(404);

  await page.goto(lessonUrl(lessons.future));
  await expect(page.locator('[data-bbb=live]')).toContainText('The class opens on');
});

test('Direct mode sends the tab straight to BigBlueButton', async ({ page }) => {
  sql("UPDATE gibbonSetting SET value='direct' WHERE scope='BigBlueButton' AND name='joinMode'");
  await login(page, personas.teacher, PASSWORD);
  await page.goto(lessonUrl(lessons.live));
  await expect(page.getByRole('link', { name: 'Join Class (New Tab)' })).toHaveAttribute('href', /bbb_join\.php/);

  const tab = await openJoinTab(page);
  await expect(tab.locator('#status')).toHaveAttribute('data-role', 'MODERATOR');
  expect(new URL(tab.url()).pathname).toBe('/html5client/join');
  expect((await mock.lastRequest('create')).params.allowRequestsWithoutSession).toBeUndefined();
});
