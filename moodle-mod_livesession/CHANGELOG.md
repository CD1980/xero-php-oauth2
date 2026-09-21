# Changelog

All notable changes to this plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
