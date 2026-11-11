// In-memory state for the mock BigBlueButton server. Reset via POST /__mock/reset.
import { randomBytes } from 'node:crypto';

export const randomId = (bytes = 12) => randomBytes(bytes).toString('hex');

export function createState(initialConfig) {
  const state = {
    config: { ...initialConfig },
    meetings: new Map(), // meetingID -> meeting
    sessions: new Map(), // sessionToken -> { meetingID, fullName, role, cookie, userID }
    recordings: [], // published recordings, see addRecording()
    requests: [], // API request log (most recent last)
  };

  state.reset = () => {
    state.config = { ...initialConfig };
    state.meetings.clear();
    state.sessions.clear();
    state.recordings = [];
    state.requests = [];
  };

  state.logRequest = (entry) => {
    state.requests.push({ at: Date.now(), ...entry });
    if (state.requests.length > 500) state.requests.shift();
  };

  return state;
}

export function playbackBase(state) {
  const { playbackHost, publicUrl } = state.config;
  if (!playbackHost) return publicUrl;
  return /^https?:\/\//.test(playbackHost) ? playbackHost.replace(/\/+$/, '') : `https://${playbackHost}`;
}

// Builds the playback formats BBB (plus the transcript add-on) would publish for a recording.
export function buildFormats(state, recordID, types) {
  const base = playbackBase(state);
  const urls = {
    presentation: `${base}/playback/presentation/2.3/${recordID}`,
    video: `${base}/playback/video/${recordID}/`,
    summary: `${base}/presentation/${recordID}/summary/summary.html`,
    transcript: `${base}/presentation/${recordID}/transcript/transcript.txt`,
  };
  return types.map((type) => ({ type, url: urls[type] ?? `${base}/playback/${type}/${recordID}/` }));
}

export function addRecording(state, { meetingID, formats = ['presentation'], startTime, endTime, name, metadata, published = true }) {
  const meeting = state.meetings.get(meetingID);
  const internalMeetingID = `${randomId(20)}-${Date.now()}`;
  const recordID = internalMeetingID;
  const start = startTime ?? Date.now() - 45 * 60 * 1000;
  const recording = {
    recordID,
    meetingID,
    internalMeetingID,
    name: name ?? meeting?.name ?? meetingID,
    published,
    state: published ? 'published' : 'unpublished',
    startTime: start,
    endTime: endTime ?? start + 40 * 60 * 1000,
    participants: meeting?.joinedCount ?? 3,
    metadata: metadata ?? meeting?.metadata ?? {},
    formats: buildFormats(state, recordID, formats),
  };
  state.recordings.push(recording);
  return recording;
}
