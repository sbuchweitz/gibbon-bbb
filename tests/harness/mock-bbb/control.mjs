// Test-control endpoints (/__mock/*): inspect state, seed recordings, change config, simulate logout.
import { addRecording } from './state.mjs';

const readJson = (req) =>
  new Promise((resolve, reject) => {
    let data = '';
    req.on('data', (chunk) => { data += chunk; if (data.length > 1e6) reject(new Error('body too large')); });
    req.on('end', () => { try { resolve(data ? JSON.parse(data) : {}); } catch (err) { reject(err); } });
    req.on('error', reject);
  });

const json = (res, status, body) => { res.writeHead(status, { 'content-type': 'application/json' }); res.end(JSON.stringify(body)); };

export async function handleControl(req, res, url, state) {
  const route = `${req.method} ${url.pathname}`;
  switch (route) {
    case 'GET /__mock/health':
      return json(res, 200, { ok: true });
    case 'POST /__mock/reset':
      state.reset();
      return json(res, 200, { ok: true });
    case 'GET /__mock/state':
      return json(res, 200, { config: state.config, meetings: [...state.meetings.values()], recordings: state.recordings, requests: state.requests });
    case 'POST /__mock/config': {
      const body = await readJson(req);
      const allowed = ['playbackHost', 'allowRequestsWithoutSession', 'secret', 'setJSessionIdOnApi'];
      for (const key of allowed) if (key in body) state.config[key] = body[key];
      return json(res, 200, { config: state.config });
    }
    case 'POST /__mock/recordings': {
      const body = await readJson(req);
      if (!body.meetingID) return json(res, 400, { error: 'meetingID required' });
      return json(res, 201, addRecording(state, body));
    }
    case 'GET /__mock/logout': {
      const session = state.sessions.get(url.searchParams.get('sessionToken') ?? '');
      const meeting = session && state.meetings.get(session.meetingID);
      if (!meeting?.logoutURL) return json(res, 200, { loggedOut: true });
      res.writeHead(302, { location: meeting.logoutURL });
      return res.end();
    }
    default:
      return json(res, 404, { error: `unknown control route ${route}` });
  }
}
