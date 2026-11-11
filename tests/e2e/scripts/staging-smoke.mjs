// Smoke test against a REAL BigBlueButton server (credentials from the repo-root .env, never printed):
//   BBB_STAGING_URL=https://bbb.example.org/bigbluebutton/
//   BBB_STAGING_SECRET=...
// Uses the harness Gibbon: saves the server in the module settings, creates a live lesson, joins as teacher and
// student (embedded and direct), uses microphone and webcam inside the embedded frame, checks roles through the
// BBB API, optionally starts a recording, then ends the meeting and reopens it. Prints a pass/fail table;
// screenshots go to test-results/staging-*.png.
//   node scripts/staging-smoke.mjs [--record] [--browser=webkit]
// --browser=webkit runs the same steps in Playwright's WebKit (Safari's engine, with mock camera and microphone).
// Embedded camera/microphone need Gibbon on HTTPS (a plain-HTTP page gives the iframe no media devices): start the
// harness "tls" profile, point Gibbon's absoluteURL at it and run with GIBBON_URL=https://<GIBBON_TLS_HOST>:2007.
import { chromium, webkit } from '@playwright/test';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { ADMIN, GIBBON_URL } from '../support/env.mjs';
import { clickAndLoad, lessonUrl, login, sqlValue } from '../support/gibbon.mjs';
import { PASSWORD } from '../support/personas.mjs';
import { createLesson, pickPersonas } from '../support/seed.mjs';

const RECORD = process.argv.includes('--record');
const ENGINE = (process.argv.find((arg) => arg.startsWith('--browser=')) ?? '--browser=chromium').slice('--browser='.length);
if (!['chromium', 'webkit'].includes(ENGINE)) throw new Error(`Unsupported --browser=${ENGINE} (chromium or webkit)`);
const envFile = new URL('../../../.env', import.meta.url);
const env = existsSync(envFile)
  ? Object.fromEntries(readFileSync(envFile, 'utf8').split('\n').map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/)).filter(Boolean).map((m) => [m[1], m[2]]))
  : {};
const BBB = (process.env.BBB_STAGING_URL ?? env.BBB_STAGING_URL ?? '').replace(/\/?$/, '/');
const SECRET = process.env.BBB_STAGING_SECRET ?? env.BBB_STAGING_SECRET ?? '';
if (BBB === '/' || !SECRET) throw new Error('Set BBB_STAGING_URL and BBB_STAGING_SECRET in the repo-root .env');
const BBB_ORIGIN = new URL(BBB).origin;

/** Signed BBB API call (sha256), returning the XML text. */
async function api(call, params = {}) {
  const query = new URLSearchParams(params).toString();
  const checksum = createHash('sha256').update(call + query + SECRET).digest('hex');
  const response = await fetch(`${BBB}api/${call}?${query}${query ? '&' : ''}checksum=${checksum}`);
  return response.text();
}
const tag = (xml, name) => [...xml.matchAll(new RegExp(`<${name}>([^<]*)</${name}>`, 'g'))].map((m) => m[1]);

const results = [];
let failureShots = [];
async function step(name, fn) {
  try {
    results.push({ step: name, ok: true, detail: (await fn()) ?? '' });
  } catch (error) {
    results.push({ step: name, ok: false, detail: String(error.message).split('\n')[0].slice(0, 160) });
    for (const [i, page] of failureShots.entries()) {
      await page.screenshot({ path: shot(`failed-${results.length - 1}-${i}`) }).catch(() => {});
    }
  }
}

/** The BBB HTML5 client is "up" once its join-audio dialog or main UI renders inside the frame. */
async function waitForClient(frame, timeout = 45_000) {
  const ui = frame.locator('[data-test="audioModal"], [data-test="listenOnlyBtn"], [data-test="microphoneBtn"], [data-test="userListItem"], [data-test="actionsButton"], .ReactModal__Content');
  await ui.first().waitFor({ timeout });
}

/** Closes BBB's own dialogs (e.g. "Session Details" after joining audio) that cover the client. */
async function dismissDialogs(frame) {
  for (let i = 0; i < 3; i += 1) {
    const close = frame.locator('.ReactModal__Content [data-test="closeModal"], .ReactModal__Content button[aria-label^="Close"]').first();
    if (!(await close.isVisible().catch(() => false))) return;
    await close.click().catch(() => {});
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
}

/** Fails if the client replaced itself with its crash screen. */
async function assertNoCrash(frame) {
  if (await frame.locator('[data-test="errorScreenMessage"]').count()) throw new Error('BBB client crashed ("Oops, something went wrong")');
}

const pad = (n) => String(n).padStart(2, '0');
const hms = (d) => `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:00`;

const secureOriginFlag = GIBBON_URL.startsWith('http:') ? [`--unsafely-treat-insecure-origin-as-secure=${new URL(GIBBON_URL).origin}`] : [];
// WebKit has no secure-origin flag (embedded media then needs the HTTPS front) and ships mock capture devices.
const browser = ENGINE === 'webkit'
  ? await webkit.launch()
  : await chromium.launch({ channel: 'chromium', args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', ...secureOriginFlag] });
const shot = (name) => `test-results/staging-${ENGINE}-${name}.png`;
const contextFor = () => browser.newContext({ permissions: ['camera', 'microphone'], viewport: { width: 1400, height: 900 }, ignoreHTTPSErrors: GIBBON_URL.startsWith('https:') });
mkdirSync('test-results', { recursive: true });
let meetingID = '';
let lessonID = 0;

try {
  await step('settings: save staging server + connection test', async () => {
    const page = await (await contextFor()).newPage();
    await login(page, ADMIN.username, ADMIN.password);
    await page.goto(`${GIBBON_URL}/index.php?q=/modules/BigBlueButton/bbb_settings.php`);
    await page.fill('[name=bigBlueButtonURL]', BBB);
    await page.fill('[name=bigBlueButtonCredentials]', SECRET);
    await clickAndLoad(page, page.locator('#bigBlueButtonSettings [type=submit]').first());
    const message = await page.locator('.success, .warning, .error').allInnerTexts();
    if (!page.url().includes('return=success0')) throw new Error(`not saved cleanly: ${message.join(' | ')}`);
    return message.find((m) => m.includes('Connected')) ?? 'saved';
  });

  const personas = pickPersonas();
  const videoChatID = sqlValue("SELECT gibbonCustomFieldID FROM gibbonCustomField WHERE context='Lesson Plan' AND name='Video Chat'");
  const now = new Date();
  lessonID = createLesson(personas.classID, 'Staging Smoke Test', now.toISOString().slice(0, 10), hms(new Date(now - 5 * 60_000)), hms(new Date(+now + 40 * 60_000)), { [videoChatID]: 'Include', videoLink: '' });

  const teacher = await (await contextFor()).newPage();
  const bbbRequests = { total: 0, withCookie: 0 };
  teacher.context().on('requestfinished', async (request) => {
    if (new URL(request.url()).origin !== BBB_ORIGIN) return;
    bbbRequests.total += 1;
    if (await request.headerValue('cookie').catch(() => null)) bbbRequests.withCookie += 1;
  });
  let teacherTab;
  await step('teacher: Join Class (New Tab) → embedded BBB client loads', async () => {
    await login(teacher, personas.teacher, PASSWORD);
    await teacher.goto(lessonUrl(lessonID));
    [teacherTab] = await Promise.all([teacher.context().waitForEvent('page'), teacher.getByRole('link', { name: 'Join Class (New Tab)' }).click()]);
    await teacherTab.waitForLoadState('load');
    failureShots = [teacherTab];
    const frame = teacherTab.frameLocator('iframe.bbb-frame');
    await waitForClient(frame);
    await teacherTab.screenshot({ path: shot('teacher-embedded') });
    meetingID = sqlValue(`SELECT meetingID FROM bigBlueButtonMeeting WHERE gibbonPlannerEntryID=${lessonID}`) ?? '';
    return `frame ${teacherTab.frames().find((f) => f.url().includes('/html5client'))?.url().replace(/\?.*$/, '') ?? '(url hidden)'}`;
  });

  // Safari blocks cookies in cross-site frames; the class must then run on allowRequestsWithoutSession alone.
  await step('browser: BBB cookies in the cross-site frame (informational)', async () => {
    const names = (await teacher.context().cookies(BBB_ORIGIN)).map((cookie) => cookie.name);
    const crossSite = new URL(GIBBON_URL).hostname.split('.').slice(-2).join('.') !== new URL(BBB_ORIGIN).hostname.split('.').slice(-2).join('.');
    return `${crossSite ? 'cross-site' : 'same-site'} frame; stored: ${names.join(', ') || 'none'}; sent on ${bbbRequests.withCookie}/${bbbRequests.total} BBB requests`;
  });

  await step('teacher: microphone works inside the frame (echo test)', async () => {
    const frame = teacherTab.frameLocator('iframe.bbb-frame');
    await frame.locator('[data-test="microphoneBtn"]').click({ timeout: 15_000 });
    // BBB 3.x shows audio settings first ("Join echo test"); older versions go straight to the echo test.
    await frame.locator('[data-test="joinEchoTestButton"]').click({ timeout: 10_000 }).catch(() => {});
    await frame.locator('[data-test="echoYesBtn"]').click({ timeout: 30_000 }).catch(() => {});
    await teacherTab.waitForTimeout(5_000);
    await assertNoCrash(frame);
    const voice = Number(tag(await api('getMeetingInfo', { meetingID }), 'voiceParticipantCount')[0] ?? 0);
    if (voice < 1) throw new Error(`voiceParticipantCount=${voice} (microphone did not connect)`);
    return `joined with microphone (voiceParticipantCount=${voice})`;
  });

  await step('teacher: webcam shares from inside the frame', async () => {
    const frame = teacherTab.frameLocator('iframe.bbb-frame');
    await dismissDialogs(frame);
    await frame.locator('[data-test="joinVideo"]').click({ timeout: 15_000 });
    await frame.locator('[data-test="startSharingWebcam"]').click({ timeout: 30_000 });
    await teacherTab.waitForTimeout(5_000);
    await assertNoCrash(frame);
    const videos = Number(tag(await api('getMeetingInfo', { meetingID }), 'videoCount')[0] ?? 0);
    if (videos < 1) throw new Error(`videoCount=${videos}`);
    await teacherTab.screenshot({ path: shot('teacher-av') });
    return `videoCount=${videos}`;
  });

  await step('BBB API: meeting created with our parameters, teacher is MODERATOR', async () => {
    const info = await api('getMeetingInfo', { meetingID });
    if (!info.includes('<returncode>SUCCESS</returncode>')) throw new Error(tag(info, 'message')[0] ?? 'getMeetingInfo failed');
    const roles = tag(info, 'role');
    if (!roles.includes('MODERATOR')) throw new Error(`roles: ${roles.join(',') || 'none (client did not join?)'}`);
    return `meetingID ${meetingID}; roles ${roles.join(',')}; recording=${tag(info, 'recording')[0]}`;
  });

  await step('student: joins the running class as VIEWER (embedded)', async () => {
    const student = await (await contextFor()).newPage();
    await login(student, personas.student, PASSWORD);
    await student.goto(`${GIBBON_URL}/modules/BigBlueButton/bbb_live.php?gibbonPlannerEntryID=${lessonID}`);
    const frame = student.frameLocator('iframe.bbb-frame');
    await waitForClient(frame);
    await frame.locator('[data-test="listenOnlyBtn"]').click({ timeout: 15_000 });
    await student.waitForTimeout(5_000);
    await assertNoCrash(frame);
    await student.screenshot({ path: shot('student-embedded') });
    const roles = tag(await api('getMeetingInfo', { meetingID }), 'role');
    if (!roles.includes('VIEWER')) throw new Error(`roles: ${roles.join(',')}`);
    return `roles ${roles.join(',')}`;
  });

  await step('teacher: learning analytics dashboard opens as a popup (best effort)', async () => {
    const frame = teacherTab.frameLocator('iframe.bbb-frame');
    await dismissDialogs(frame);
    await frame.locator('[data-test="manageUsers"]').click({ timeout: 15_000 });
    const [popup] = await Promise.all([teacherTab.waitForEvent('popup', { timeout: 15_000 }), frame.locator('[data-test="learningDashboard"]').click({ timeout: 10_000 })]);
    await popup.waitForLoadState('load');
    return popup.url().replace(/\?.*$/, '');
  });

  if (RECORD) {
    await step('teacher: start recording from the BBB client (best effort)', async () => {
      const frame = teacherTab.frameLocator('iframe.bbb-frame');
      await teacherTab.keyboard.press('Escape'); // close the users menu if still open
      await dismissDialogs(frame);
      await frame.locator('[data-test="recordingIndicator"]').click({ timeout: 15_000 });
      await frame.getByRole('button', { name: /^(yes|start)/i }).first().click({ timeout: 10_000 });
      await frame.locator('[data-test="recordingIndicator"][aria-label*="Pause" i], [data-test="recordingIndicator"][aria-label*="Stop" i], button[aria-label*="Pause recording" i]').first().waitFor({ timeout: 15_000 });
      await teacherTab.waitForTimeout(60_000); // something to record
      return 'recording started from the BBB client and ran for 60 s';
    });
  }

  await step('direct mode: bbb_join.php redirects the tab to BBB', async () => {
    const page = await teacher.context().newPage();
    await page.goto(`${GIBBON_URL}/modules/BigBlueButton/bbb_join.php?gibbonPlannerEntryID=${lessonID}`);
    await page.waitForURL((url) => url.origin === BBB_ORIGIN, { timeout: 30_000 });
    await waitForClient(page.mainFrame());
    await page.screenshot({ path: shot('direct') });
    return page.url().replace(/\?.*$/, '');
  });

  await step('end the meeting, then reopen it right away', async () => {
    const ended = await api('end', { meetingID });
    if (!ended.includes('SUCCESS')) throw new Error(`end: ${tag(ended, 'message')[0]}`);
    await new Promise((resolve) => setTimeout(resolve, 5_000));
    const page = await teacher.context().newPage();
    await page.goto(`${GIBBON_URL}/modules/BigBlueButton/bbb_live.php?gibbonPlannerEntryID=${lessonID}`);
    const body = await page.locator('body').innerText();
    if (body.includes('cannot be reached')) throw new Error('module reported BBB unreachable after end');
    await waitForClient(page.frameLocator('iframe.bbb-frame'), 60_000);
    await page.screenshot({ path: shot('reopened') });
    return 'reopened with the same meeting ID';
  });

  await step('cleanup: end the meeting', async () => tag(await api('end', { meetingID }), 'returncode')[0]);
} finally {
  await browser.close();
  writeFileSync(`test-results/staging-smoke-${ENGINE}.json`, JSON.stringify({ engine: ENGINE, lessonID, meetingID, results }, null, 2));
  console.table(results);
}
