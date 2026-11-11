// Switches which module folder the harness Gibbon serves (e.g. v1.6.1 for upgrade tests).
// Recreating the container would lose config.php (written by the installer), so it is carried across.
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { COMPOSE_FILE, GIBBON_URL } from './env.mjs';

const compose = (args, env = {}) => execFileSync('docker', ['compose', '-f', COMPOSE_FILE, ...args], { env: { ...process.env, ...env }, stdio: 'pipe' });

/** @param {string} moduleDir path relative to tests/harness (the compose file's directory) */
export async function mountModule(moduleDir) {
  const dir = mkdtempSync(join(tmpdir(), 'bbb-config-'));
  try {
    compose(['cp', 'gibbon:/var/www/html/config.php', join(dir, 'config.php')]);
    compose(['up', '-d', '--force-recreate', '--no-deps', 'gibbon'], { MODULE_DIR: moduleDir });
    compose(['cp', join(dir, 'config.php'), 'gibbon:/var/www/html/config.php']);
    compose(['exec', '-T', 'gibbon', 'chown', 'www-data:www-data', '/var/www/html/config.php']);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
  for (let i = 0; i < 30; i += 1) {
    const ok = await fetch(`${GIBBON_URL}/`).then((r) => r.ok).catch(() => false);
    if (ok) return;
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error('Gibbon did not come back after remounting the module');
}
