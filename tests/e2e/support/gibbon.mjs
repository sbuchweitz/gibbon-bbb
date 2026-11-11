// Helpers for driving the harness Gibbon: SQL access, DB snapshots, sign-in.
import { execFileSync } from 'node:child_process';
import { COMPOSE_FILE, GIBBON_URL } from './env.mjs';

const compose = (args, input) =>
  execFileSync('docker', ['compose', '-f', COMPOSE_FILE, ...args], { input, maxBuffer: 1 << 30 }).toString();

/** Runs SQL in the Gibbon database; returns rows as arrays of tab-separated strings. */
export function sql(query) {
  const out = compose(['exec', '-T', 'db', 'mariadb', '-uroot', '-proot', '-N', '-B', 'gibbon'], query);
  return out.split('\n').filter(Boolean).map((line) => line.split('\t'));
}

export const sqlValue = (query) => sql(query)[0]?.[0] ?? null;

/** Quotes a value for SQL built in tests (test data only). */
export const q = (value) => (value === null ? 'NULL' : `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`);

export function saveSnapshot(name) {
  compose(['exec', '-T', 'db', 'sh', '-c', `mariadb-dump -uroot -proot --single-transaction gibbon > /tmp/${name}.sql`]);
}

export function restoreSnapshot(name) {
  compose(['exec', '-T', 'db', 'sh', '-c', `mariadb -uroot -proot -e 'DROP DATABASE IF EXISTS gibbon; CREATE DATABASE gibbon' && mariadb -uroot -proot gibbon < /tmp/${name}.sql`]);
}

export function snapshotExists(name) {
  try {
    compose(['exec', '-T', 'db', 'test', '-s', `/tmp/${name}.sql`]);
    return true;
  } catch {
    return false;
  }
}

/** Gibbon stores sha256(salt + password); test personas share one known password. */
export function setPassword(username, password) {
  sql(`UPDATE gibbonPerson SET passwordStrongSalt='harness', passwordStrong=SHA2(CONCAT('harness', ${q(password)}), 256),
       passwordForceReset='N', canLogin='Y', failCount=0 WHERE username=${q(username)};`);
}

export async function login(page, username, password) {
  await page.context().clearCookies();
  await page.goto(`${GIBBON_URL}/`);
  await page.fill('input[name=username]', username);
  await page.fill('input[name=password]', password);
  await Promise.all([page.waitForLoadState('load'), page.locator('form input[type=submit], form button[type=submit]').first().click()]);
  if (await page.locator('input[name=password]').isVisible().catch(() => false)) {
    throw new Error(`Login failed for ${username}`);
  }
}

export const lessonUrl = (id, extra = '') =>
  `${GIBBON_URL}/index.php?q=/modules/Planner/planner_view_full.php&gibbonPlannerEntryID=${id}&viewBy=date${extra}`;
