// Seeded by global-setup.mjs; every persona shares this test-only password.
import { readFileSync } from 'node:fs';

export const PASSWORD = 'Harness-Persona-2026!';

export function fixtures() {
  return JSON.parse(readFileSync(new URL('../.auth/fixtures.json', import.meta.url), 'utf8'));
}
