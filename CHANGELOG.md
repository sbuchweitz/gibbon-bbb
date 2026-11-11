# Changelog

## 2.0.0

Rewrite of the module.

### Security
- **The settings are Administrator-only.** Upgrading restricts the settings page to the Administrator role and reminds administrators to replace the shared secret.
- **The shared secret is a password field and is never shown again.** Settings are validated before saving, and saving tests the connection.
- **The module's own pages enforce the lesson page's access rules:** class enrolment, planner guests, parents of enrolled children, and lesson visibility.
- **Recording pages look up playback URLs on the server.** No URL is taken from the request.

### Changed
- The lesson page shows **Join Class (New Tab)** instead of loading the class inline. The new tab is either a Gibbon page embedding the class (popups allowed) or BigBlueButton itself (*Direct* mode).
- `allowRequestsWithoutSession` is no longer needed on the BigBlueButton server. Embedded meetings relax the session check per meeting.
- Recording is available but starts only when a moderator presses *Record* (it used to start automatically).
- Recordings are buttons that open in a new tab. Their links point at the media file where one exists, so *Save link as…* downloads it.
- Meeting IDs are unique per Gibbon installation and stored per lesson. Recordings no longer disappear when a lesson is edited, moved to a later date or has *Video Chat* unticked. Pre-2.0 recordings stay visible.
- New **Recording Host Override** setting, for BigBlueButton servers that publish recordings with the wrong host.
- Students who join before the teacher wait on a page that joins them automatically. Only moderators start meetings.
- The BigBlueButton library is bundled: installing needs no `composer` step. A missing dependency or an unsupported PHP version shows an admin notice instead of breaking lesson pages.
- Calls to BigBlueButton have a total timeout. Recording lists are cached and still shown while BigBlueButton is unreachable.
- Fresh installs no longer grant parents the live and recorded actions.

### Requirements
- PHP 8.2+, Gibbon v30+, BigBlueButton 2.4.3+.
