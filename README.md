# BigBlueButton

Module for [Gibbon][gibbon]: BigBlueButton video classes and recordings for lesson plans.

[gibbon]: https://gibbonedu.org

## What it does

- **Video Chat per lesson.** Tick *Video Chat* on a lesson. From 10 minutes before it starts until 10 minutes after it ends, the lesson page shows **Join Class (New Tab)**. The class never loads inside the lesson page.
- **Roles follow the lesson.** Class teachers, assistants and technicians (and planner guest teachers/assistants/technicians) join as moderators and start the meeting. Everyone else joins as a viewer. Viewers who arrive early see a waiting page that joins them automatically once the teacher has started.
- **Teachers decide what is recorded.** Recording is available, but nothing is recorded until a moderator presses *Record* in BigBlueButton.
- **Recordings stay with the lesson.** Each lesson keeps one BigBlueButton meeting ID for good. Its recordings stay listed even if the lesson is edited, moved to another date, or *Video Chat* is unticked afterwards.
- **Recordings as buttons.** Each recording has one button per playback format (Presentation, Video, Transcript, Summary…). A click opens it in a new tab. For formats that are a file, such as *Video* when BigBlueButton's `video` format is enabled, right-click → *Save link as…* downloads the file.
- Supports transcripts and summaries from [bbb-transcript-and-summary](https://github.com/fvlasie/bbb-transcript-and-summary). Tick *Presentation Only* on a lesson to show only the presentation.

## Requirements

- Gibbon v30 or newer
- PHP 8.2 or newer with the `curl`, `json`, `mbstring` and `simplexml` extensions
- BigBlueButton 2.4.3 or newer (2.6+ recommended)

## Installation

1. Put the module in `GIBBON_HOME/modules/BigBlueButton` (the folder must be named `BigBlueButton`), for example with `git clone https://github.com/sbuchweitz/gibbon-bbb.git GIBBON_HOME/modules/BigBlueButton`. Dependencies are included in `vendor/`, so **no `composer` step is needed.**
2. In Gibbon, go to *System Admin › Manage Modules* and install **BigBlueButton**.
3. Open *Admin › BigBlueButton Settings*:
   - enable BigBlueButton;
   - enter the server URL and shared secret, as shown by `bbb-conf --secret` on the BigBlueButton server.

   Saving tests the connection.

### BigBlueButton server

- **`allowRequestsWithoutSession` is not needed** in `bbb-web.properties`. In *Embedded* mode the module relaxes BigBlueButton's session-cookie check only for the meetings it creates. If you set the server-wide flag only for Gibbon, you can remove it.
- **Recordings that fail to load** with an error such as *cannot connect to bigbluebutton.example.org* mean BigBlueButton published them with the wrong `playback_host`.
  - Fix the server with `bbb-conf --setip <your-bbb-host>`. This also rewrites existing recordings.
  - Until then, set **Recording Host Override** in the module settings (for example `https://bbb.school.org`).

## Join modes

| Mode | Join Class (New Tab) opens… | Notes |
|---|---|---|
| **Embedded** (default) | a Gibbon page with the class in an iframe | Popups (such as the learning analytics dashboard) are allowed. A *Trouble joining?* link opens the class without the frame. |
| **Direct** | BigBlueButton itself | Nothing to relax. The most compatible option (Safari, Scalelite). |

*Embedded* needs Gibbon on **HTTPS**. Inside a plain-HTTP page the browser gives the class no camera or microphone, and BigBlueButton's client fails. The class page detects this and opens the class directly instead.

*Allow embedded join without session cookie* (default **Yes**) is needed when Gibbon and BigBlueButton are on different domains, because a cross-site iframe cannot use BigBlueButton's session cookie. Set it to **No** if both share a domain (for example `gibbon.school.org` and `bbb.school.org`).

## Permissions

| Action | Default roles (fresh install) |
|---|---|
| BigBlueButton Settings | Administrator only. The page manages the shared secret. |
| View live sessions | Administrator, Teacher, Student, Support Staff |
| View recorded sessions | Administrator, Teacher, Student, Support Staff |

Parents are off by default, because recordings show other children. Grant *View recorded sessions* in *User Admin › Manage Permissions* if your school wants parents to watch them.

## Upgrading from 1.x

Update the module in *System Admin › Manage Modules*. The upgrade:

- **restricts the settings to Administrators** and asks you to replace the BigBlueButton shared secret: run `bbb-conf --setsecret <new-secret>` on the BigBlueButton server and enter the new secret in the settings. Gibbon reminds administrators until you do.
- **keeps recordings made with 1.x** (meeting ID `planner<lessonID>`) visible on their lessons, as buttons.
  - In 1.x, unticking *Video Chat* or moving a lesson to a later date hid its recordings. Moving no longer hides them. For a pre-upgrade lesson whose *Video Chat* was unticked, tick it again.
  - If the BigBlueButton server is shared with other Gibbon sites, turn off *Show Recordings Made Before Version 2.0*. 1.x recordings carry nothing that tells sites apart.
- **leaves existing permissions for the live and recorded actions unchanged** (for example, parent access you already had).

## Known limitations

- **Recording URLs are not secret.** BigBlueButton serves recordings without authentication, so anyone with a playback or download link can watch it. Gibbon controls who is shown the links.
- **Scalelite and other load balancers** may redirect an embedded class to a different host. If camera or microphone permissions don't reach the class, use *Direct* mode.

## Development

- **Tests.** Run `tests/harness/compose.yml` on Docker. It brings up Gibbon v30, MariaDB and a mock BigBlueButton server.
  - `cp tests/harness/.env.example tests/harness/.env`, then set the two addresses.
  - `docker compose -f tests/harness/compose.yml up -d --build` starts the stack.
  - `docker compose -f tests/harness/compose.yml run --rm tests php tools/vendor/bin/phpunit` runs the unit and integration tests on PHP 8.2.
  - `cd tests/e2e && npm ci && npm run install-gibbon && npm test` runs the Playwright end-to-end tests. The upgrade test also needs `tests/e2e/scripts/fetch-v161.sh`, which downloads the original 1.x module.
  - `node tests/e2e/scripts/staging-smoke.mjs [--record]` checks a **real** BigBlueButton server. Put `BBB_STAGING_URL` and `BBB_STAGING_SECRET` in the git-ignored `.env` at the repository root. The script never prints the secret. It covers:
    - the settings connection test
    - embedded and direct joins
    - microphone and webcam inside the frame
    - viewer role
    - the analytics popup
    - recording
    - ending and reopening the meeting
  - The `tls` profile (`docker compose -f tests/harness/compose.yml --profile tls up -d gibbon-tls`) puts Gibbon behind HTTPS on port 2007, as production runs it.
- **Dev tooling** (PHPUnit, PHPStan) lives in `tools/`: run `cd tools && composer install`. It is never shipped.
- **Bundled dependencies.** `vendor/` is committed. After changing `composer.json`, rebuild it with `bin/build-vendor.sh`.

For support visit [https://gibbonedu.org/support](https://gibbonedu.org/support).

## Credits

gibbon-bbb replaces the BigBlueButton module by Wenlan Cui and Father Vlasie ([wenlanc/BigBlueButton](https://github.com/wenlanc/BigBlueButton)) and upgrades its installations in place. It is an independent rewrite and contains none of that module's code.

## License

Apache License 2.0, see [LICENSE](LICENSE) and [NOTICE](NOTICE). The bundled `vendor/bigbluebutton/bigbluebutton-api-php` is licensed under the LGPL-3.0-or-later.

https://github.com/sbuchweitz/gibbon-bbb
