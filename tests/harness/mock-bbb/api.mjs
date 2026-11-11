// BigBlueButton API endpoints (/bigbluebutton/api/<call>) with real checksum validation.
import { createHash, timingSafeEqual } from 'node:crypto';
import { randomId } from './state.mjs';
import { failure, meetingXml, recordingXml, success } from './xml.mjs';

const ALGORITHMS = { 40: 'sha1', 64: 'sha256', 96: 'sha384', 128: 'sha512' };

// BBB signs `callName + queryStringWithoutChecksum + secret`; the checksum may use any supported algorithm.
export function checksumValid(call, rawQuery, secret) {
  const parts = rawQuery.split('&');
  const checksumPart = parts.find((p) => p.startsWith('checksum='));
  if (!checksumPart) return false;
  const given = checksumPart.slice('checksum='.length).toLowerCase();
  const algorithm = ALGORITHMS[given.length];
  if (!algorithm) return false;
  const signed = parts.filter((p) => p !== checksumPart).join('&');
  const expected = createHash(algorithm).update(call + signed + secret).digest('hex');
  return timingSafeEqual(Buffer.from(expected), Buffer.from(given));
}

const metaParams = (params) =>
  Object.fromEntries([...params].filter(([k]) => k.startsWith('meta_')).map(([k, v]) => [k.slice(5).toLowerCase(), v]));

function create(params, state) {
  const meetingID = params.get('meetingID');
  if (!meetingID) return failure('missingParamMeetingID', 'You must specify a meeting ID for the meeting.');
  const existing = state.meetings.get(meetingID);
  if (existing) {
    return success({ meetingID, internalMeetingID: existing.internalMeetingID, attendeePW: existing.attendeePW, moderatorPW: existing.moderatorPW,
      createTime: existing.createTime, hasBeenForciblyEnded: 'false', messageKey: 'duplicateWarning',
      message: 'This conference was already in existence and may currently be in progress.' });
  }
  const meeting = {
    meetingID,
    internalMeetingID: `${randomId(20)}-${Date.now()}`,
    name: params.get('name') ?? meetingID,
    attendeePW: params.get('attendeePW') ?? randomId(4),
    moderatorPW: params.get('moderatorPW') ?? randomId(4),
    createTime: Date.now(),
    voiceBridge: 70000 + Math.floor(Math.random() * 9999),
    duration: Number(params.get('duration') ?? 0),
    record: params.get('record') === 'true',
    autoStartRecording: params.get('autoStartRecording') === 'true',
    allowStartStopRecording: params.get('allowStartStopRecording') !== 'false',
    logoutURL: params.get('logoutURL') ?? '',
    allowRequestsWithoutSession: params.get('allowRequestsWithoutSession') === 'true',
    metadata: metaParams(params),
    createParams: Object.fromEntries(params),
    attendees: [],
    joinedCount: 0,
  };
  state.meetings.set(meetingID, meeting);
  return success({ meetingID, internalMeetingID: meeting.internalMeetingID, attendeePW: meeting.attendeePW, moderatorPW: meeting.moderatorPW,
    createTime: meeting.createTime, voiceBridge: meeting.voiceBridge, dialNumber: '613-555-1234', createDate: new Date(meeting.createTime).toUTCString(),
    hasUserJoined: 'false', duration: meeting.duration, hasBeenForciblyEnded: 'false', messageKey: '', message: '' });
}

function joinRole(params, meeting) {
  const role = (params.get('role') ?? '').toUpperCase();
  if (role === 'MODERATOR' || role === 'VIEWER') return role;
  const password = params.get('password');
  if (password === meeting.moderatorPW) return 'MODERATOR';
  if (password === meeting.attendeePW) return 'VIEWER';
  return null;
}

function join(params, state, res) {
  const meeting = state.meetings.get(params.get('meetingID') ?? '');
  if (!meeting) return { xml: failure('invalidMeetingIdentifier', 'The meeting ID that you supplied did not match any existing meetings') };
  const role = joinRole(params, meeting);
  if (!role) return { xml: failure('invalidPassword', 'You either did not supply a password or the password supplied is neither the attendee or moderator password for this conference.') };
  const sessionToken = randomId(8);
  const cookie = randomId(16);
  const userID = `w_${randomId(5)}`;
  const fullName = params.get('fullName') ?? 'Anonymous';
  state.sessions.set(sessionToken, { meetingID: meeting.meetingID, fullName, role, cookie, userID });
  meeting.attendees.push({ userID, fullName, role });
  meeting.joinedCount += 1;
  // Like bbb-web: session cookie without SameSite (browsers treat it as Lax, so it is not sent from cross-site iframes).
  res.setHeader('Set-Cookie', `JSESSIONID=${cookie}; Path=/; HttpOnly`);
  return { redirect: `${state.config.publicUrl}/html5client/join?sessionToken=${sessionToken}` };
}

function enter(params, state, cookieHeader) {
  const session = state.sessions.get(params.get('sessionToken') ?? '');
  if (!session) return failure('missingSession', 'Invalid session token');
  const cookieMatches = (cookieHeader ?? '').split(/;\s*/).includes(`JSESSIONID=${session.cookie}`);
  const relaxed = state.config.allowRequestsWithoutSession || state.meetings.get(session.meetingID)?.allowRequestsWithoutSession;
  if (!cookieMatches && !relaxed) return failure('checksumError', 'No session cookie: set allowRequestsWithoutSession=true to allow cross-site embedding');
  return success({ fullname: session.fullName, role: session.role, meetingID: session.meetingID, userID: session.userID });
}

function getRecordings(params, state) {
  const csv = (name) => (params.get(name) ? params.get(name).split(',').map((s) => s.trim()).filter(Boolean) : null);
  const meetingIDs = csv('meetingID');
  const recordIDs = csv('recordID');
  const states = csv('state') ?? ['published', 'unpublished'];
  const meta = metaParams(params);
  const list = state.recordings.filter((r) =>
    (!meetingIDs || meetingIDs.includes(r.meetingID)) &&
    (!recordIDs || recordIDs.some((id) => r.recordID.startsWith(id))) &&
    (states.includes('any') || states.includes(r.state)) &&
    Object.entries(meta).every(([k, v]) => r.metadata[k] === v));
  if (list.length === 0) return success({ recordings: null, messageKey: 'noRecordings', message: 'There are no recordings for the meeting(s).' });
  return success({ recordings: { recording: list.map(recordingXml) } });
}

export function handleApi(req, res, url, rawQuery, state) {
  const call = url.pathname.replace(/^\/bigbluebutton\/api\/?/, '');
  const params = url.searchParams;
  const send = (xml) => {
    // bbb-web (Grails) can answer API calls with a session cookie; clients must cope with it.
    if (state.config.setJSessionIdOnApi && !res.hasHeader('Set-Cookie')) res.setHeader('Set-Cookie', `JSESSIONID=${randomId(16)}; Path=/; HttpOnly`);
    res.writeHead(200, { 'content-type': 'text/xml; charset=utf-8' });
    res.end(xml);
  };
  state.logRequest({ call: call || '(index)', params: Object.fromEntries([...params].filter(([k]) => k !== 'checksum')) });

  if (call === '') return send(success({ version: '2.0', apiVersion: '2.0', bbbVersion: '3.0-mock' }));
  if (call === 'enter') return send(enter(params, state, req.headers.cookie));
  if (!checksumValid(call, rawQuery, state.config.secret)) return send(failure('checksumError', 'Checksums do not match'));

  const meeting = state.meetings.get(params.get('meetingID') ?? '');
  switch (call) {
    case 'create':
      return send(create(params, state));
    case 'join': {
      const result = join(params, state, res);
      if (result.xml) return send(result.xml);
      res.writeHead(302, { location: result.redirect });
      return res.end();
    }
    case 'isMeetingRunning':
      return send(success({ running: meeting && meeting.joinedCount > 0 ? 'true' : 'false' }));
    case 'getMeetingInfo':
      return send(meeting ? success(meetingXml(meeting)) : failure('notFound', 'We could not find a meeting with that meeting ID'));
    case 'end':
      if (!meeting) return send(failure('notFound', 'We could not find a meeting with that meeting ID - perhaps the meeting is not yet running?'));
      state.meetings.delete(meeting.meetingID);
      return send(success({ messageKey: 'sentEndMeetingRequest', message: 'A request to end the meeting was sent.' }));
    case 'getMeetings':
      return send(success({ meetings: state.meetings.size ? { meeting: [...state.meetings.values()].map(meetingXml) } : null }));
    case 'getRecordings':
      return send(getRecordings(params, state));
    default:
      return send(failure('unsupportedRequest', `This request is not supported by the mock: ${call}`));
  }
}
