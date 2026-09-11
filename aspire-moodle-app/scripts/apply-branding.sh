#!/usr/bin/env bash
#
# Applies the Aspire branding overlay to a checkout of the official Moodle app.
#
#   ./scripts/apply-branding.sh [target-dir]
#
# Clones moodlehq/moodleapp at the tag pinned in branding/brand.json (unless the
# target already exists), copies the overlay files over it, then rewrites the
# config keys we own. Re-running is safe and idempotent.
#
# Upstream upgrades: bump upstreamRef in brand.json, delete the target dir (or
# `git fetch && git checkout` the new tag inside it), and re-run.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-$REPO_ROOT/app}"
UPSTREAM_URL="https://github.com/moodlehq/moodleapp"

UPSTREAM_REF="$(node -p "require('$REPO_ROOT/branding/brand.json').upstreamRef")"

echo "==> Aspire branding overlay"
echo "    upstream : $UPSTREAM_URL @ $UPSTREAM_REF"
echo "    target   : $TARGET"

if [ ! -d "$TARGET/.git" ]; then
    echo "==> Cloning upstream (shallow, single tag)"
    git clone --depth 1 --branch "$UPSTREAM_REF" "$UPSTREAM_URL" "$TARGET"
else
    echo "==> Reusing existing checkout"
    echo "    at $(git -C "$TARGET" describe --tags --always)"
fi

echo "==> Copying overlay files"
# -a preserves the tree shape; the overlay mirrors the app's own layout so this
# is a plain overwrite of the handful of files we own.
cp -av "$REPO_ROOT/branding/src/."       "$TARGET/src/"       | sed 's/^/    /'
cp -av "$REPO_ROOT/branding/resources/." "$TARGET/resources/" | sed 's/^/    /'

echo "==> Patching config"
node "$REPO_ROOT/scripts/patch-config.mjs" "$TARGET"

echo "==> Verifying brand colour contrast"
python3 "$REPO_ROOT/scripts/check-contrast.py"

cat <<EOF

==> Done. Next:

    cd "$TARGET"
    npm ci
    npx ionic serve                 # run in the browser
    npx cordova platform add android ios
    npm run prod:android            # release build

  Icons and splash screens are regenerated from resources/ by the app's own
  Cordova resource step during the platform build.
EOF
