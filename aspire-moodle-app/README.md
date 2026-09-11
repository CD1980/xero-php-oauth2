# Aspire Learning — mobile app

A branded build of the official Moodle app ([`moodlehq/moodleapp`](https://github.com/moodlehq/moodleapp))
for Aspire Education and Training.

The app is the official Moodle client, so it has full feature parity with the
site from day one — courses, SCORM, quizzes, assignments, forums, grades,
offline content and calendar all work because they are Moodle's own
implementations, not re-creations.

## How this repo is laid out

This repo holds **only the Aspire-specific changes**, not a copy of the app:

```
branding/
  brand.json                       single source of truth — names, colours, IDs, site URL
  src/theme/globals.custom.scss    brand colour overrides
  src/theme/theme.custom.scss      header/login styling
  src/assets/img/                  login + header logos
  resources/                       app icon, splash, Android adaptive icon
scripts/
  apply-branding.sh                clone upstream at the pinned tag, apply the overlay
  patch-config.mjs                 rewrite the config keys we own
  check-contrast.py                WCAG check for the brand palette
app/                               generated — the working app tree (git-ignored)
```

`globals.custom.scss` and `theme.custom.scss` are files upstream ships **empty,
specifically for downstream branding**. `globals.custom.scss` is imported before
`globals.variables.scss`, where every variable is declared `!default`, so our
values win without editing a single upstream file.

That is the point of the whole arrangement: **no upstream file is modified**, so
picking up a new Moodle app release is a tag bump, not a merge conflict.

## Build it

```bash
./scripts/apply-branding.sh     # writes ./app
cd app
npm ci
npx ionic serve                 # run it in a browser
```

For device builds:

```bash
cd app
npx cordova platform add android ios
npm run prod:android
npm run prod:ios
```

## Upgrading to a new Moodle app release

1. Bump `upstreamRef` in `branding/brand.json` (e.g. `v5.4.0`).
2. `rm -rf app && ./scripts/apply-branding.sh`
3. Check `git -C app diff --stat` — it should touch only our ten files.
4. Rebuild and smoke-test login, a course, and an offline SCORM attempt.

If step 3 shows upstream has changed the variables we override, adjust
`branding/src/theme/` and re-run.

## Before the first release — open items

### 1. The site URL is a placeholder

`branding/brand.json` currently points at `https://moodle.aeat.com.au`, which is
a guess. Set `site.url` to the real Moodle site. `apply-branding.sh` prints a
warning until it is changed.

With `site.lockToThisSiteOnly: true` the app skips the site picker and goes
straight to the Aspire login, and refuses to connect anywhere else.

### 2. The artwork is upscaled from a 263×184 PNG

The only logo available was a 263×184 raster. The hexagon mark in it is ~89×92px
and the app icon needs 1024×1024, so `resources/icon.png` is an ~11× upscale and
is visibly soft. It is structurally correct — right mark, right padding, no
alpha — but it should be regenerated from the **vector original** (`.ai`, `.eps`
or `.svg`) before store submission. Drop the vector in and re-run
`scripts/gen-assets.py`.

### 3. Push notifications need your own Firebase project **and** your own Airnotifier

This is the one that surprises people. Moodle's public push service only serves
the *official* app; a custom-branded build cannot use it. To get push working
you need:

- a Firebase project for `au.com.aeat.aspirelearning`, replacing the upstream
  `google-services.json` and `GoogleService-Info.plist` (these are Moodle's own
  and are git-ignored here so they are never committed);
- your own [Airnotifier](https://github.com/moodlehq/airnotifier) instance, with
  its URL set in *Site administration → Messaging → Mobile notifications*.

Without these the app works fine — it just won't push.

### 4. Moodle server settings

In *Site administration → General → Mobile app → Mobile settings*:

- **Enable web services for mobile devices** must be on.
- Add the app's URL scheme (`aspirelearning`) so site links open in the app.

### 5. Store listings and the Moodle trademark

The app is GPLv3, and re-branding it is expressly allowed — but the **Moodle name
and logo are trademarks** and cannot appear in the app name, icon, or store
listing. "Aspire Learning" is fine; "Aspire Moodle" is not. You may say it
"connects to your Moodle site" in the description.

Both stores also need:

- Apple and Google developer accounts under Aspire's business identity
  (Apple requires a D-U-N-S number for organisation accounts);
- a published privacy policy URL — `brand.json` points at
  `https://www.aeat.com.au/privacy-policy`, which needs to exist and to cover
  what the app collects;
- the numeric Apple ID in `brand.stores.ios` once the listing exists, so
  in-app "rate this app" links resolve.

## Brand palette

Verified with `scripts/check-contrast.py`:

| Role | Colour | Contrast | Use |
|---|---|---|---|
| Primary (light theme) | `#0079A0` | 4.95:1 on white | buttons, active tabs, progress |
| Primary (dark theme) | `#009DCF` | 4.72:1 on `#282828` | same, on dark |
| Accent | `#92CDDC` | 1.75:1 on white | **surfaces only** — never text |
| Navy | `#1F3864` | 11.62:1 on white | headings |

The logo teal `#0079A0` drops to 2.98:1 on the dark background, which is why the
dark theme uses the lighter `#009DCF` instead. Both auto-derive their button text
colour through upstream's `get_contrast_color()`.
