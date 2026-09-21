# Changelog

All notable changes to this plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
