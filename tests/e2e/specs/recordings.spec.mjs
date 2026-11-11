// Recordings: buttons (not inline iframes) that open a Gibbon viewer tab; href = downloadable file.
// Also covers recordings surviving lesson edits and the wrong-playback-host workaround.
import { expect, test } from '@playwright/test';
import { ADMIN, BBB_URL, GIBBON_URL } from '../support/env.mjs';
import { clickAndLoad, lessonUrl, login, q, restoreSnapshot, sql } from '../support/gibbon.mjs';
import { mock } from '../support/mock.mjs';
import { PASSWORD, fixtures } from '../support/personas.mjs';

const { personas, lessons } = fixtures();
const MEETING_ID = 'gibbon-e2e-past-meeting';
const BROKEN_HOST = 'bigbluebutton.example.org';
let recordIDs = [];

const editUrl = (id) =>
  `${GIBBON_URL}/index.php?q=/modules/Planner/planner_edit.php&gibbonPlannerEntryID=${id}&viewBy=class&gibbonCourseClassID=${personas.classID}`;

const viewerUrl = (recordID, format, id = lessons.past) =>
  `${GIBBON_URL}/modules/BigBlueButton/bbb_recording.php?gibbonPlannerEntryID=${id}&recording=${recordID}&format=${format}`;

async function setOverride(page, origin) {
  await login(page, ADMIN.username, ADMIN.password);
  await page.goto(`${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`);
  await page.fill('[name=recordingHostOverride]', origin);
  await clickAndLoad(page, page.locator('#bigBlueButtonSettings [type=submit]').first());
  expect(page.url()).toContain('return=success0');
}

async function recordingButtons(page) {
  const section = page.locator('[data-bbb=recordings]');
  await expect(section).toBeVisible();
  return section.locator('.bbb-recording');
}

test.beforeAll(async () => {
  restoreSnapshot('bbb-ready');
  await mock.reset();
  await mock.configure({ playbackHost: BROKEN_HOST });
  sql(`INSERT INTO bigBlueButtonMeeting (gibbonPlannerEntryID, meetingID, timestampCreated) VALUES (${lessons.past}, ${q(MEETING_ID)}, UTC_TIMESTAMP())`);
  const yesterday = Date.now() - 86_400_000;
  const first = await mock.seedRecording(MEETING_ID, ['summary', 'video', 'presentation', 'transcript'], { startTime: yesterday, metadata: { 'bbb-origin': 'Gibbon' } });
  const second = await mock.seedRecording(MEETING_ID, ['presentation'], { startTime: yesterday + 30 * 60_000, metadata: { 'bbb-origin': 'Gibbon' } });
  await mock.seedRecording('someone-elses-meeting', ['presentation']);
  recordIDs = [first.recordID, second.recordID];
});

test('without an override the links carry the host BigBlueButton published', async ({ page }) => {
  await login(page, personas.student, PASSWORD);
  await page.goto(lessonUrl(lessons.past));
  const rows = await recordingButtons(page);

  await expect(rows.first().getByRole('link', { name: 'Video' })).toHaveAttribute('href', new RegExp(`^https://${BROKEN_HOST}/`));
});

test('recordings are buttons; click opens the viewer tab, href downloads the video', async ({ page }) => {
  await setOverride(page, BBB_URL);
  await login(page, personas.student, PASSWORD);
  await page.goto(lessonUrl(lessons.past));
  const rows = await recordingButtons(page);

  await expect(page.locator(`[data-bbb] iframe, iframe[src^="${BBB_URL}"]`)).toHaveCount(0); // core's own editors may use iframes
  await expect(rows).toHaveCount(2);
  await expect(rows.first().locator('a')).toHaveText(['Presentation', 'Video', 'Transcript', 'Summary']);
  await expect(rows.nth(1).locator('a')).toHaveText(['Presentation']);

  const video = rows.first().getByRole('link', { name: 'Video' });
  const href = await video.getAttribute('href');
  expect(href).toBe(`${BBB_URL}/playback/video/${recordIDs[0]}/video-0.m4v`);
  const file = await page.request.get(href);
  expect(file.headers()['content-type']).toBe('video/mp4');

  const [tab] = await Promise.all([page.context().waitForEvent('page'), rows.first().getByRole('link', { name: 'Presentation' }).click()]);
  await tab.waitForLoadState('load');
  expect(tab.url()).toContain('/modules/BigBlueButton/bbb_recording.php');
  await expect(tab.locator('iframe.bbb-frame')).toHaveAttribute('src', `${BBB_URL}/playback/presentation/2.3/${recordIDs[0]}`);
  await expect(tab.frameLocator('iframe.bbb-frame').locator('#playback')).toHaveAttribute('data-format', 'presentation');
  await expect(page.url()).toContain('planner_view_full.php'); // the lesson tab stays where it was

  await tab.getByRole('link', { name: 'Video', exact: true }).click();
  await expect(tab.frameLocator('iframe.bbb-frame').locator('#playback')).toHaveAttribute('data-format', 'video');
  await expect(tab.getByRole('link', { name: 'Download' })).toHaveAttribute('href', href);

  // A middle click opens the viewer as well, not the media file the href points at.
  const [middle] = await Promise.all([page.context().waitForEvent('page'), video.click({ button: 'middle' })]);
  await middle.waitForLoadState('load');
  expect(middle.url()).toContain('/modules/BigBlueButton/bbb_recording.php');
  expect(middle.url()).toContain('format=video');
});

test('the viewer only shows recordings of that lesson, to people who can see it', async ({ page }) => {
  await login(page, personas.student, PASSWORD);
  const foreign = (await mock.state()).recordings.find((r) => r.meetingID === 'someone-elses-meeting');

  expect((await page.goto(viewerUrl(foreign.recordID, 'presentation')))?.status()).toBe(404);
  expect((await page.goto(viewerUrl(recordIDs[0], 'presentation', lessons.live)))?.status()).toBe(404);
  expect((await page.goto(viewerUrl('../../etc/passwd', 'presentation')))?.status()).toBe(404);
  expect((await page.goto(viewerUrl(recordIDs[0], 'podcast')))?.status()).toBe(404);

  await login(page, personas.outsider, PASSWORD);
  expect((await page.goto(viewerUrl(recordIDs[0], 'presentation')))?.status()).toBe(403);
});

test('editing and saving the lesson keeps its recordings', async ({ page, browser }) => {
  await login(page, personas.teacher, PASSWORD);
  const edit = editUrl(lessons.past);
  const save = async () => {
    await clickAndLoad(page, page.locator('form#action [type=submit], form [type=submit]').last());
    expect(page.url()).toMatch(/return=success/);
  };

  await page.goto(edit);
  await save(); // unchanged save
  await page.goto(edit);
  await page.locator('input[type=checkbox][name^="custom"][value="Include"]').uncheck(); // untick Video Chat
  await save();

  const studentContext = await browser.newContext();
  const student = await studentContext.newPage();
  await login(student, personas.student, PASSWORD);
  await student.goto(lessonUrl(lessons.past));
  await expect(await recordingButtons(student)).toHaveCount(2);
  await studentContext.close();
});

test('moving the lesson to a later date keeps its recordings visible', async ({ page }) => {
  sql(`UPDATE gibbonPlannerEntry SET date=DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE gibbonPlannerEntryID=${lessons.past}`);
  await login(page, personas.student, PASSWORD);
  await page.goto(lessonUrl(lessons.past));

  await expect(await recordingButtons(page)).toHaveCount(2);
});

test('Presentation Only lessons show just the presentation', async ({ page }) => {
  // Ticked in Gibbon's own lesson form: how teachers set it, and older Gibbon versions store the field differently.
  await login(page, personas.teacher, PASSWORD);
  await page.goto(editUrl(lessons.past));
  await page.locator('input[type=checkbox][name^="custom"][value="Yes"]').check();
  await clickAndLoad(page, page.locator('form#action [type=submit], form [type=submit]').last());
  expect(page.url()).toMatch(/return=success/);

  await login(page, personas.student, PASSWORD);
  await page.goto(lessonUrl(lessons.past));

  await expect((await recordingButtons(page)).first().locator('a')).toHaveText(['Presentation']);
  expect((await page.goto(viewerUrl(recordIDs[0], 'video')))?.status()).toBe(404);
});
