// Shared harness locations and credentials (test-only values, never production).
// Addresses come from the environment or tests/harness/.env (see .env.example).
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const envFile = new URL('../../harness/.env', import.meta.url);
const fileEnv = existsSync(envFile)
  ? Object.fromEntries(readFileSync(envFile, 'utf8').split('\n').map((line) => line.match(/^\s*([A-Z_]+)\s*=\s*(.*?)\s*$/)).filter(Boolean).map((m) => [m[1], m[2]]))
  : {};
const setting = (name) => {
  const value = process.env[name] ?? fileEnv[name];
  if (!value) throw new Error(`Set ${name} in tests/harness/.env (see .env.example)`);
  return value.replace(/\/+$/, '');
};

export const GIBBON_URL = setting('GIBBON_URL');
export const BBB_URL = setting('BBB_PUBLIC_URL');
export const BBB_SECRET = process.env.BBB_SECRET ?? 'mock-secret-0123456789';
export const COMPOSE_FILE = fileURLToPath(new URL('../../harness/compose.yml', import.meta.url));

export const ADMIN = {
  username: process.env.ADMIN_USER ?? 'admin',
  password: process.env.ADMIN_PASSWORD ?? 'Harness-Admin-2026!',
};
