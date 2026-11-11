// Mock BigBlueButton server for integration and E2E tests. No dependencies; run with `node server.mjs`.
import http from 'node:http';
import { handleApi } from './api.mjs';
import { handleControl } from './control.mjs';
import { handlePages } from './pages.mjs';
import { createState } from './state.mjs';

const config = {
  port: Number(process.env.PORT ?? 8080),
  secret: process.env.BBB_SECRET ?? 'mock-secret',
  publicUrl: (process.env.PUBLIC_URL ?? 'http://localhost:8080').replace(/\/+$/, ''),
  // Host baked into recording playback URLs. Empty = publicUrl (a correctly configured server).
  playbackHost: process.env.PLAYBACK_HOST ?? '',
  allowRequestsWithoutSession: process.env.ALLOW_REQUESTS_WITHOUT_SESSION === 'true',
};

const state = createState(config);

http
  .createServer(async (req, res) => {
    try {
      const url = new URL(req.url, config.publicUrl);
      const queryStart = req.url.indexOf('?');
      const rawQuery = queryStart >= 0 ? req.url.slice(queryStart + 1) : '';
      if (url.pathname.startsWith('/__mock/')) return await handleControl(req, res, url, state);
      if (url.pathname.startsWith('/bigbluebutton/api')) return handleApi(req, res, url, rawQuery, state);
      return handlePages(req, res, url);
    } catch (err) {
      console.error(err);
      res.writeHead(500, { 'content-type': 'text/plain' });
      res.end(`mock-bbb error: ${err.message}`);
    }
  })
  .listen(config.port, () => console.log(`mock-bbb listening on :${config.port} (public ${config.publicUrl})`));
