// Browser-facing pages: a fake HTML5 client, the learning dashboard popup and recording playback.
import { escapeXml as esc } from './xml.mjs';

const page = (title, body) => `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${esc(title)}</title>
<style>body{font-family:system-ui,sans-serif;margin:0;padding:24px;background:#0f1d33;color:#fff}button,a.btn{display:inline-block;margin:6px 6px 0 0;padding:8px 14px;border:0;border-radius:4px;background:#2b7de9;color:#fff;font:inherit;cursor:pointer;text-decoration:none}#status{padding:8px 0}</style>
</head><body>${body}</body></html>`;

function clientPage(token) {
  return page('Mock BBB client', `<h1>Mock BigBlueButton client</h1>
<div id="status" data-state="loading">Connecting…</div>
<div id="actions" hidden>
  <button id="dashboard" type="button">Open learning dashboard</button>
  <button id="leave" type="button">Leave meeting</button>
</div>
<script>
const token = ${JSON.stringify(token)};
const status = document.getElementById('status');
fetch('/bigbluebutton/api/enter?sessionToken=' + encodeURIComponent(token), { credentials: 'include' })
  .then((r) => r.text())
  .then((text) => {
    const xml = new DOMParser().parseFromString(text, 'text/xml');
    const get = (tag) => xml.querySelector(tag)?.textContent ?? '';
    if (get('returncode') !== 'SUCCESS') {
      status.dataset.state = 'error';
      status.textContent = 'Session error: ' + get('message');
      return;
    }
    status.dataset.state = 'joined';
    status.dataset.role = get('role');
    status.textContent = 'Joined ' + get('meetingID') + ' as ' + get('fullname') + ' (' + get('role') + ')';
    document.getElementById('actions').hidden = false;
    document.getElementById('dashboard').onclick = () =>
      window.open('/learning-analytics-dashboard/?meeting=' + encodeURIComponent(get('meetingID')), '_blank');
    document.getElementById('leave').onclick = () => { window.location.href = '/__mock/logout?sessionToken=' + encodeURIComponent(token); };
  });
</script>`);
}

const routes = [
  [/^\/html5client\/join$/, (url) => ({ html: clientPage(url.searchParams.get('sessionToken') ?? '') })],
  [/^\/learning-analytics-dashboard\/?$/, (url) => ({ html: page('Learning Dashboard', `<h1 id="dashboard-title">Learning dashboard for ${esc(url.searchParams.get('meeting') ?? '')}</h1>`) })],
  [/^\/playback\/presentation\/2\.3\/([\w-]+)$/, (_url, [, id]) => ({ html: page('Presentation playback', `<h1 id="playback" data-format="presentation">Presentation playback ${esc(id)}</h1>`) })],
  [/^\/playback\/video\/([\w-]+)\/$/, (_url, [, id]) => ({ html: page('Video playback', `<h1 id="playback" data-format="video">Video playback ${esc(id)}</h1><video controls src="video-0.m4v"></video>`) })],
  [/^\/playback\/video\/([\w-]+)\/video-0\.m4v$/, (_url, [, id]) => ({ body: Buffer.from(`mock video ${id}`), type: 'video/mp4' })],
  [/^\/presentation\/([\w-]+)\/summary\/summary\.html$/, (_url, [, id]) => ({ html: page('Summary', `<h1 id="playback" data-format="summary">Summary ${esc(id)}</h1>`) })],
  [/^\/presentation\/([\w-]+)\/transcript\/transcript\.txt$/, (_url, [, id]) => ({ body: Buffer.from(`Transcript for ${id}\n`), type: 'text/plain; charset=utf-8' })],
];

export function handlePages(_req, res, url) {
  for (const [pattern, render] of routes) {
    const match = url.pathname.match(pattern);
    if (!match) continue;
    const out = render(url, match);
    res.writeHead(200, { 'content-type': out.html ? 'text/html; charset=utf-8' : out.type });
    return res.end(out.html ?? out.body);
  }
  res.writeHead(404, { 'content-type': 'text/plain' });
  return res.end('mock-bbb: not found');
}
