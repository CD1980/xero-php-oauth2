# Changelog

All notable changes to this plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-21

### Changed

- The meeting now opens in **speaker view**. Set through `defaultViewType` at
  initialisation, and asked for again with `setViewType('speaker')` once joined,
  because a meeting that remembers a previous layout can otherwise come back in
  gallery view.
- The meeting canvas is created at the width of the activity frame rather than a
  fixed box, and `isResizable` is off so it stays fitted.
- The meeting **scales with the window**. Zoom fixes the canvas size when the
  client initialises and offers no way to change it afterwards, so the panel is
  laid out as a fluid frame wrapping a fixed-size stage: the stage keeps the size
  the meeting was created at and is scaled to whatever width the page currently
  has, with the frame's height following so no dead space is left. A `resize`,
  `orientationchange` and `ResizeObserver` all trigger a refit, the last of which
  catches the frame changing width on its own — a block drawer opening, say.
- Scaling is downward only. Enlarging a fixed-size canvas blurs it, so a window
  made wider than it was at join leaves the meeting at its original size; reload
  to get a larger canvas.

Measured in a real browser with the meeting live, resizing the window from 1600
down to 700 and back: the canvas always fits, overflow stays at 0px, and no
horizontal page scrollbar appears at any width.

## [1.0.9] - 2026-09-21

### Fixed

- "Could not load the Zoom Meeting SDK", finally diagnosed: the bundle was
  throwing `ReferenceError: React is not defined` as it evaluated. The CDN build
  of the Meeting SDK **externalises** React — it expects `React`, `ReactDOM`,
  `Redux` and `Lodash` to already be global. The npm package's bundle inlines
  them, which is what made this easy to misread. The plugin now loads the vendor
  scripts from `https://source.zoom.us/{version}/lib/vendor/`, in the order
  Zoom's own CDN sample uses, before the SDK bundle, and skips them entirely when
  React is already on the page.

### Added

- A **Meeting SDK vendor directory** site setting, for the case where Zoom moves
  those files, mirroring the existing SDK URL override.

Verified in a real browser as a controlled pair: with the vendor scripts absent
the failure reproduces exactly, naming each missing file and "React is still not
defined"; with them present the page reaches "You are in the meeting. Your
attendance is being recorded."

## [1.0.8] - 2026-09-21

### Fixed

- Two filters in the SDK failure reporting hid the only useful information.
  The error listener kept an event only when its `filename` mentioned
  `zoom.us`, but a cross-origin script's exceptions are reduced to a bare
  "Script error." with no filename, so every one was discarded. The CSP
  listener kept a violation only when its `blockedURI` mentioned `zoom.us`,
  but an `eval` or WebAssembly refusal reports `blockedURI` as "eval" or
  "wasm-eval", so exactly the violation that stops the bundle running was
  discarded. Both now keep everything raised while the script is loading.
- The script tag now sets `crossorigin="anonymous"`, without which the browser
  refuses to give a cross-origin script's real error message, file or line.
  Zoom's CDN sends CORS headers, so this costs nothing.

### Changed

- The Content-Security-Policy advice now names what the SDK actually needs -
  `script-src https://source.zoom.us`, `'wasm-unsafe-eval'`, `worker-src blob:`
  and `connect-src https://*.zoom.us wss://*.zoom.us` - rather than mentioning
  only the script host.

Verified in a real browser: a bundle that throws now reports the exception with
its file and line, a CSP `eval` refusal reports the blocked URI and the
directive, and a correct bundle still joins.

## [1.0.7] - 2026-09-21

### Changed

- When the SDK script loads but no usable SDK appears, the failure now reports
  what actually happened rather than restating that it failed. It lists the
  globals the bundle published, if any; re-reads the response and gives the HTTP
  status, content-type, byte count and opening characters; names any exception
  thrown while the bundle evaluated, which a script tag's `onload` otherwise
  hides; and says so if the page is not a secure context, since the Meeting SDK
  requires HTTPS.
- A 200 response whose content-type is not JavaScript is now called out as such.
  The browser refuses to execute those, which previously looked identical to a
  404.

All three paths were exercised against a real browser: a bundle publishing an
unexpected global, one publishing nothing, and a correct one, which still joins.

## [1.0.6] - 2026-09-21

### Fixed

- "Could not load the Zoom Meeting SDK ... loaded, but did not register the SDK".
  Moodle puts RequireJS on every page, so `define.amd` is defined. A UMD build
  detects that and registers itself as an anonymous AMD module rather than
  assigning a browser global, and RequireJS discards it because nothing asked
  for it - the script downloads and executes perfectly and the SDK global never
  appears. The loader now hides `define.amd` for the duration of the script load
  and restores it immediately afterwards, which forces the bundle down its
  browser-global branch. It also accepts either global the SDK may publish
  (`ZoomMtgEmbedded` or `ReactWidgets`) and, when neither appears, reports which
  it looked for.

### Added

- `tests/manual/browser-smoke.js`, a Playwright script that drives a real browser
  against a live Moodle and asserts the SDK registers. PHPUnit cannot see this
  class of failure; it only exists on a real page with RequireJS present.

## [1.0.5] - 2026-09-21

### Changed

- The SDK loader now tries the confirmed Zoom CDN path first. Zoom's 6.x bundle
  names itself `zoom-meeting-embedded-{version}.min.js` in its own license
  header, and Zoom's CDN sample confirms the versioned directory layout, so
  `https://source.zoom.us/{version}/zoom-meeting-embedded-{version}.min.js` is
  the correct URL. That bundle has no AMD branch and sets `window.ZoomMtgEmbedded`
  directly, with React bundled in, so no vendor scripts are needed.
- When loading fails, the message now distinguishes the causes instead of
  reporting every URL identically. A Content-Security-Policy refusal is caught
  from the `securitypolicyviolation` event and named along with the directive
  that blocked it; any other failure is probed with a HEAD request so the report
  carries the HTTP status. A script tag's error event exposes neither, which is
  why a wrong version and a blocked request previously looked the same.

## [1.0.4] - 2026-09-21

### Fixed

- "Could not load the Zoom Meeting SDK" when starting a session. The default SDK
  version was 3.13.2, several generations behind the current release, and the
  CDN URL was built as
  `source.zoom.us/{version}/zoom-meeting-embedded-{version}.umd.min.js`, which
  is not a path Zoom serves. The default version is now 6.5.0.

### Changed

- The loader now tries the known Zoom CDN paths in turn rather than relying on
  one hard-coded pattern, and the failure message names every URL it tried, so
  the fix is visible instead of guessable.

### Added

- A **Meeting SDK URL** site setting. Left blank the plugin uses its built-in
  candidates; set it to pin an exact URL, with `{version}` substituted from the
  version setting. This means a future Zoom CDN change can be worked around in
  site administration rather than needing a plugin release.

## [1.0.3] - 2026-09-21

### Added

- **Zoom connection test**, linked from the plugin settings. It reports the
  plugin version on disk against the version in the database (so a half-applied
  upgrade is obvious), shows which credentials are set without printing any of
  them, then contacts Zoom live - bypassing the cached token - and reports what
  Zoom actually said. It also reads the default host's meeting list, so the
  granted scopes and the host itself are checked, not just authentication.

### Changed

- The error on an activity page now says plainly that it is what Zoom said the
  last time that activity was saved, not a live check, and points at the
  connection test. The old wording read as a current failure, which made a
  stale message indistinguishable from a real one.

## [1.0.2] - 2026-09-21

### Fixed

- Every Zoom call failed at the first step with "Zoom rejected the plugin's
  credentials: Bad Request". The token request passed its parameters to
  Moodle's `curl::post()` as an array, and Moodle hands an array straight to
  `CURLOPT_POSTFIELDS`, so libcurl built a `multipart/form-data` body and
  appended its boundary to the `application/x-www-form-urlencoded` header the
  plugin had set. Zoom could not parse the result. The body is now a
  pre-encoded string, and `client::build_token_body()` is covered by a test.
- The scheduled reconciliation task would have died on "Class curl not found".
  That class lives in `lib/filelib.php`, which is not autoloadable and which
  `setup.php` only loads when `$CFG->proxyfixunsafe` is set. A web request
  pulls it in incidentally; cron does not. All curl instances now go through
  `make_curl()`, which requires filelib first.
- A blocked or unreachable host was reported as rejected credentials, because
  Moodle's curl sets `->error` without setting an errno in that case. Transport
  failures now say so, and token errors include the HTTP status and Zoom's own
  error code.

## [1.0.1] - 2026-09-21

### Fixed

- Creating a live session failed with "Invalid course module ID". The calendar
  event was built with `format_module_intro(..., 0, ...)`, and that function
  resolves a module context from the course module id it is given. During
  `add_instance` the course module row exists but its instance column is still
  zero, so the call threw, `add_instance` never returned an id, and Moodle was
  left with a course module pointing at nothing. The calendar description now
  uses the stored intro, and a calendar failure can no longer abort the save.
- Viewing the course's list of live sessions failed with "Cannot instantiate
  abstract class core\event\course_module_instance_list_viewed". Added the
  per-module subclass that Moodle requires.
- The browser user agent was never recorded against attendance. It was read
  through `core_useragent`, which is a browser-detection helper rather than a
  source of the raw header; the request header is now stored verbatim.
- A zero attended duration displayed as "now", because that is what
  `format_time(0)` returns. It now reads "None".

### Added

- `mod_livesession_core_calendar_provide_event_action`, required because the
  calendar entry is an action event, so the session appears in the timeline
  block with a Join action until its join window closes.

### Changed

- Brought the whole plugin to the Moodle coding standard (moodle-cs): zero
  errors and zero warnings.

## [1.0.0] - 2026-09-21

### Added

- Live session activity type: schedule a Zoom meeting for a course session from the
  standard activity form, with the meeting created through the Zoom API on save.
- In-page meeting, rendered with the Zoom Meeting SDK Component View. Students join from
  a button inside the course page; the Zoom application is never launched.
- Automatic attendance capture from the embedded client: time attended, IP address,
  browser, username and Moodle sign-in time, with a full audit trail of joins,
  departures and corrections.
- Gradebook integration with all-or-nothing and proportional grading, and the attendance
  evidence written into the gradebook feedback field.
- Post-session reconciliation against Zoom's participant report, and a sweep that closes
  records abandoned by a browser that went away.
- Attendance report with a CSV export, and per-participant manual correction.
- Attendance-based activity completion, calendar integration, course reset support,
  backup and restore, and a full Privacy API implementation.
