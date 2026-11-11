// Minimal XML helpers producing responses shaped like BigBlueButton's API.
export const escapeXml = (value) =>
  String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' })[c]);

// Serialises a plain object: strings/numbers/booleans become text nodes, objects nest,
// arrays repeat the parent tag name for each item ({ attendee: [{...}, {...}] }).
export function toXml(obj) {
  return Object.entries(obj)
    .map(([tag, value]) => {
      if (value === null || value === undefined) return `<${tag}/>`;
      if (Array.isArray(value)) return value.map((item) => toXml({ [tag]: item })).join('');
      if (typeof value === 'object') return `<${tag}>${toXml(value)}</${tag}>`;
      return `<${tag}>${escapeXml(value)}</${tag}>`;
    })
    .join('');
}

export const response = (body) => `<?xml version="1.0" encoding="UTF-8"?><response>${toXml(body)}</response>`;

export const success = (body = {}) => response({ returncode: 'SUCCESS', ...body });

export const failure = (messageKey, message) => response({ returncode: 'FAILED', messageKey, message });

export function meetingXml(meeting) {
  return {
    meetingName: meeting.name,
    meetingID: meeting.meetingID,
    internalMeetingID: meeting.internalMeetingID,
    createTime: meeting.createTime,
    createDate: new Date(meeting.createTime).toUTCString(),
    voiceBridge: meeting.voiceBridge,
    dialNumber: '613-555-1234',
    attendeePW: meeting.attendeePW,
    moderatorPW: meeting.moderatorPW,
    running: meeting.joinedCount > 0 ? 'true' : 'false',
    duration: meeting.duration,
    hasUserJoined: meeting.joinedCount > 0 ? 'true' : 'false',
    recording: 'false',
    hasBeenForciblyEnded: 'false',
    startTime: meeting.createTime,
    endTime: 0,
    participantCount: meeting.attendees.length,
    listenerCount: 0,
    voiceParticipantCount: 0,
    videoCount: 0,
    maxUsers: 0,
    moderatorCount: meeting.attendees.filter((a) => a.role === 'MODERATOR').length,
    attendees: { attendee: meeting.attendees.map((a) => ({ userID: a.userID, fullName: a.fullName, role: a.role })) },
    metadata: meeting.metadata,
    isBreakout: 'false',
  };
}

export function recordingXml(recording) {
  return {
    recordID: recording.recordID,
    meetingID: recording.meetingID,
    internalMeetingID: recording.internalMeetingID,
    name: recording.name,
    isBreakout: 'false',
    published: recording.published ? 'true' : 'false',
    state: recording.state,
    startTime: recording.startTime,
    endTime: recording.endTime,
    participants: recording.participants,
    rawSize: 1048576,
    metadata: recording.metadata,
    size: 2097152,
    playback: {
      format: recording.formats.map((f) => ({ type: f.type, url: f.url, processingTime: 1200, length: 40, size: 1048576 })),
    },
    data: null,
  };
}
