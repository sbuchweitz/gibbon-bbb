// Client for the mock BBB server's control routes (tests/harness/mock-bbb).
import { BBB_URL } from './env.mjs';

async function call(method, path, body, attempt = 1) {
  let response;
  try {
    // No keep-alive: a pooled socket the mock already closed fails with "other side closed".
    response = await fetch(`${BBB_URL}${path}`, {
      method,
      headers: { 'content-type': 'application/json', connection: 'close' },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    if (attempt < 2) return call(method, path, body, attempt + 1);
    throw error;
  }
  if (!response.ok) throw new Error(`mock ${method} ${path}: ${response.status} ${await response.text()}`);
  return response.json();
}

export const mock = {
  reset: () => call('POST', '/__mock/reset'),
  configure: (config) => call('POST', '/__mock/config', config),
  state: () => call('GET', '/__mock/state'),
  seedRecording: (meetingID, formats, extra = {}) => call('POST', '/__mock/recordings', { meetingID, formats, ...extra }),
  async lastRequest(callName) {
    const { requests } = await this.state();
    return requests.filter((r) => r.call === callName).at(-1);
  },
};
