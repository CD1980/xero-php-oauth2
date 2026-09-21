# Live session (mod_livesession)

A Moodle activity module that schedules a Zoom video call for a course session, runs it
**inside the course page**, and turns attendance into a gradebook mark automatically.

- Teachers schedule the session from the normal "Add an activity" flow. The Zoom meeting
  is created through the Zoom API when the activity is saved.
- Students click into the activity and join from a button on the page. The meeting renders
  in a panel on the Moodle page — the Zoom desktop app is never launched and no new tab or
  window is opened.
- Attendance is captured while the student is in the meeting: time attended, IP address,
  username and Moodle sign-in time. The resulting mark is written to the gradebook with
  that evidence attached as feedback.

## Requirements

| | |
|---|---|
| Moodle | 4.3 or later (`$plugin->requires = 2023100900`) |
| PHP | 8.0 or later |
| Zoom plan | Pro or higher — the free plan does not expose the participant report used for reconciliation |
| Zoom apps | One **Server-to-Server OAuth** app and one **Meeting SDK** app |

## Zoom setup

Two separate apps are needed. They do different jobs and the plugin keeps both secrets
server-side; the browser only ever receives a short-lived signed token.

### 1. Server-to-Server OAuth app

Creates, updates and deletes the meetings, and reads the participant report afterwards.

At https://marketplace.zoom.us → **Develop → Build App → Server-to-Server OAuth**, create
the app and grant these scopes:

| Purpose | Granular scope | Classic scope |
|---|---|---|
| Create a meeting | `meeting:write:meeting:admin` | `meeting:write:admin` |
| Update a meeting | `meeting:update:meeting:admin` | `meeting:write:admin` |
| Read a meeting | `meeting:read:meeting:admin` | `meeting:read:admin` |
| Delete a meeting | `meeting:delete:meeting:admin` | `meeting:write:admin` |
| Past meeting instances | `meeting:read:list_past_instances:admin` | `meeting:read:admin` |
| Participant report | `report:read:list_meeting_participants:admin` | `report:read:admin` |

Activate the app, then copy the **Account ID**, **Client ID** and **Client Secret**.

### 2. General App (supplies the Meeting SDK credentials)

Signs the token that lets a browser into the meeting. Without it the meeting cannot be
embedded and nothing will render on the page.

Zoom has retired the standalone **Meeting SDK** app type and folded it into the **General
App**, so there is no Meeting SDK entry in the Build App list any more. At
**Develop → Build App → General App**:

1. Fill in the basic information. The Meeting SDK never runs the OAuth flow, but the form
   still requires a redirect URL — your Moodle site URL is fine.
2. Open the **Embed** tab and switch **Meeting SDK** on.
3. Copy the **Client ID** and **Client Secret** from **App Credentials**.

No scopes are required for the Meeting SDK.

> **Use the development credentials.** A General App issues separate development and
> production credential pairs. While the app is unpublished — which it will be for
> internal use — only the **development** pair produces a signature Zoom will accept.
> Switching to the production pair before publishing the app makes every join fail.

### 3. Enter the credentials in Moodle

*Site administration → Plugins → Activity modules → Live session*

Fill in both credential pairs, set the default Zoom host (an email address, a Zoom user id,
or `me` for the account the server-to-server app belongs to), and review the privacy
settings before going live.

## Installation

Copy the plugin into `mod/livesession` in your Moodle root:

```bash
git clone https://github.com/CD1980/moodle-mod_livesession.git /path/to/moodle/mod/livesession
```

Or download a ZIP of this repository and install it through
*Site administration → Plugins → Install plugins*. Whichever route you take, the folder
**must** be named `livesession`.

Then visit *Site administration → Notifications* to run the database upgrade.

## How attendance is captured

1. The student clicks **Join the session**. The browser asks the server for a signature;
   the server mints it, opens an attendance record, and stamps it with the request's IP
   address, the browser user agent, the student's username and their last Moodle sign-in
   time.
2. While the meeting is open the page calls home once per *attendance check-in interval*
   (60 seconds by default). Each call credits the time since the previous one.
3. When the student leaves, the record is closed. If the browser disappears without saying
   so, a scheduled task closes the record at its last check-in — so a closed laptop earns
   at most one *presume departed after* window (5 minutes by default), not the whole session.
4. Ten minutes after the scheduled end, a scheduled task pulls Zoom's own participant
   report and takes Zoom's duration where it is longer than what the browser reported.
   Participants are matched on email address.
5. The mark is recalculated at each step and written to the gradebook, along with the
   evidence as feedback. The gradebook is only touched when the mark or status actually
   changes, so heartbeats do not hammer it.

**Grading options**

- *Not graded* — attendance is recorded, no mark is written.
- *All or nothing* — full marks once the attendance requirement is met, zero otherwise.
- *Proportional* — the mark is the fraction of the scheduled duration attended.

The requirement is a percentage of the scheduled duration, or an absolute number of
minutes if you set one.

**Corrections** — a teacher with `mod/livesession:manageattendance` can correct any record
from the attendance report. A corrected record is left alone by the automation until the
correction is removed.

## Privacy and compliance

This plugin records personal data as evidence of attendance: **IP addresses**, browser user
agents, usernames, sign-in times and a one-way hash of the Moodle session. Before switching
it on:

- Make sure your privacy notice and your students' consent cover IP address collection. In
  Australia this is personal information under the Privacy Act; under GDPR it is personal
  data.
- Decide whether the evidence belongs in the gradebook. The *Show the attendance evidence
  in the gradebook* setting writes it into the feedback field, which **the student can see
  as well as the teacher**. Turn it off to keep the evidence in the attendance report only.
- IP recording can be disabled site-wide or per session. Disabling it stops new addresses
  being recorded; it does not delete addresses already stored.
- The plugin implements the Moodle Privacy API in full, so attendance data is exported and
  deleted correctly by subject access and erasure requests.
- The IP recorded is the address Moodle sees. If Moodle sits behind a reverse proxy or load
  balancer, configure `$CFG->getremoteaddrconf` so the real client address is recorded
  rather than the proxy's.

## Known limitations

- **Component View feature set.** The embedded Zoom client does not expose every feature of
  the desktop app. Breakout rooms in particular are limited. If a session depends on them,
  the host can use the *Open in the Zoom app instead* link (visible to hosts only).
- **Performance.** The Zoom Web SDK performs best on a cross-origin-isolated page
  (`Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Embedder-Policy:
  require-corp`). Moodle does not set those headers, and turning them on site-wide breaks
  other embedded content, so the plugin enables the SDK's `patchJsMedia` fallback instead.
  Video quality and the maximum number of visible participants are lower than in a fully
  isolated page.
- **Content Security Policy.** If your site sets a CSP, it must allow `https://source.zoom.us`
  for scripts and `wss://*.zoom.us` for websockets. The SDK also needs WebAssembly and
  blob workers, so `'wasm-unsafe-eval'`, `worker-src blob:` and `media-src blob:` are
  needed too.
- **RequireJS.** Moodle puts RequireJS on every page, so `define.amd` is defined. A UMD
  build of the SDK detects that and registers as an anonymous AMD module instead of
  setting a global, which RequireJS then discards — the script loads and the SDK simply
  never appears. The loader hides `define.amd` for the duration of the script load and
  restores it immediately afterwards. `tests/manual/browser-smoke.js` guards this.
- **Reconciliation matching.** Zoom's participant report is matched to Moodle users by
  email. A student whose Zoom account uses a different address is not matched, and only
  their browser-reported time counts.
- **Mobile.** Embedding is not supported in the Moodle mobile app; students on mobile should
  use a browser.
- **Restore.** A restored copy does not inherit the original Zoom meeting — the meeting may
  belong to another Zoom account. Open and save the restored activity to create a fresh one.

## Development

```bash
# From the Moodle root, with moodle-plugin-ci installed:
vendor/bin/moodle-plugin-ci phplint mod/livesession
vendor/bin/moodle-plugin-ci phpcs mod/livesession
vendor/bin/moodle-plugin-ci phpunit mod/livesession
vendor/bin/moodle-plugin-ci grunt mod/livesession
```

The JavaScript source is `amd/src/embed.js`. Moodle serves AMD modules from `amd/build`, so
after editing the source, regenerate the build artifact with `grunt amd` from the Moodle
root and commit both files.

## Capabilities

| Capability | Default roles |
|---|---|
| `mod/livesession:addinstance` | editingteacher, manager |
| `mod/livesession:view` | all |
| `mod/livesession:join` | student, teacher, editingteacher, manager |
| `mod/livesession:host` | teacher, editingteacher, manager |
| `mod/livesession:viewattendance` | teacher, editingteacher, manager |
| `mod/livesession:manageattendance` | teacher, editingteacher, manager |

## Licence

GNU GPL v3 or later. See [LICENSE](LICENSE).
