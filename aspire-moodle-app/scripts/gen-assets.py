#!/usr/bin/env python3
"""
Regenerates the app artwork in branding/ from a single source logo.

    ./scripts/gen-assets.py path/to/aspire-logo.png [--mark-rows Y0 Y1]

The source should be the full horizontal lockup (mark + wordmark). The square
app icon needs the mark on its own, so the script splits the lockup by finding
the blank row band between the mark and the wordmark. If that guess is wrong,
pass --mark-rows with the row range the mark occupies.

SVG/EPS input is not supported directly — export a large PNG (>=2048px wide,
transparent background) from the vector original first.
"""
import argparse
import json
import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "branding"
WHITE = (255, 255, 255, 255)


def opaque_profile(im, axis):
    """Opaque-pixel count along each row (axis='y') or column (axis='x')."""
    w, h = im.size
    px = im.load()
    if axis == "y":
        return [sum(1 for x in range(w) if px[x, y][3] > 128) for y in range(h)]
    return [sum(1 for y in range(h) if px[x, y][3] > 128) for x in range(w)]


def first_band(profile):
    """(start, end) of the first run of non-blank slices, or None if it never ends."""
    filled = [i for i, n in enumerate(profile) if n > 0]
    if not filled:
        sys.exit("source image is fully transparent")

    start = filled[0]
    for i in range(start, len(profile)):
        if profile[i] == 0:
            return start, i - 1
    return None


def find_mark(im, layout):
    """
    Locate the logo mark, which sits either above the wordmark (a stacked
    lockup) or to its left (a horizontal one).

    Returns (axis, start, end) where axis is 'y' for a stacked lockup and 'x'
    for a horizontal one. 'auto' tries a row split first, since a stacked
    lockup usually has no blank column band, while a horizontal lockup has no
    blank row band — so whichever split succeeds identifies the layout.
    """
    if layout in ("auto", "stacked"):
        band = first_band(opaque_profile(im, "y"))
        if band:
            return ("y", *band)
        if layout == "stacked":
            sys.exit("no blank row band found — is this a horizontal lockup? "
                     "try --layout horizontal, or pass --mark-rows")

    band = first_band(opaque_profile(im, "x"))
    if band:
        return ("x", *band)

    sys.exit("could not separate mark from wordmark: no blank row or column "
             "band found. Pass --mark-rows to set the split by hand.")


def band_bbox(im, axis, a0, a1):
    """Tight bounding box of the opaque pixels inside the given band."""
    w, h = im.size
    px = im.load()
    if axis == "y":
        span = [(x, y) for y in range(a0, a1 + 1) for x in range(w)]
    else:
        span = [(x, y) for x in range(a0, a1 + 1) for y in range(h)]
    pts = [(x, y) for x, y in span if px[x, y][3] > 128]
    xs, ys = [p[0] for p in pts], [p[1] for p in pts]
    return min(xs), min(ys), max(xs) + 1, max(ys) + 1


def fit(img, box_w, box_h):
    r = min(box_w / img.width, box_h / img.height)
    return img.resize((max(1, round(img.width * r)), max(1, round(img.height * r))), Image.LANCZOS)


def centred(img, size, bg, inset):
    canvas = Image.new("RGBA", (size, size), bg)
    scaled = fit(img, round(size * inset), round(size * inset))
    canvas.alpha_composite(scaled, ((size - scaled.width) // 2, (size - scaled.height) // 2))
    return canvas


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("source", type=Path)
    ap.add_argument("--mark-rows", nargs=2, type=int, metavar=("Y0", "Y1"),
                    help="rows the mark occupies, overriding auto-detection "
                         "(stacked lockups only)")
    ap.add_argument("--layout", choices=("auto", "stacked", "horizontal"),
                    default="auto",
                    help="mark above the wordmark (stacked) or beside it "
                         "(horizontal); default auto-detects")
    args = ap.parse_args()

    im = Image.open(args.source).convert("RGBA")
    if args.mark_rows:
        axis, a0, a1 = "y", *args.mark_rows
    else:
        axis, a0, a1 = find_mark(im, args.layout)

    shape = "stacked, mark rows" if axis == "y" else "horizontal, mark columns"
    print(f"source {args.source} {im.size}; {shape} {a0}-{a1}")

    if im.width < 2048:
        print(f"NOTE: source is only {im.width}px wide. The 1024px icon will be "
              f"upscaled and will look soft. Export a larger PNG from the vector "
              f"original for store-quality artwork.")

    mark = im.crop(band_bbox(im, axis, a0, a1))
    lockup = im.crop(im.getbbox())

    (OUT / "resources" / "android").mkdir(parents=True, exist_ok=True)
    (OUT / "src" / "assets" / "img").mkdir(parents=True, exist_ok=True)

    # App icon: opaque (iOS rejects alpha), mark at 70% to survive the corner mask.
    centred(mark, 1024, WHITE, 0.70).convert("RGB").save(OUT / "resources/icon.png")

    # Splash: Cordova centre-crops this square to every device ratio, so the art
    # has to sit well inside the middle.
    centred(lockup, 2732, WHITE, 0.30).convert("RGB").save(OUT / "resources/splash.png")

    # Android adaptive icon: 108dp canvas, only the central 72dp is guaranteed visible.
    centred(mark, 1024, (0, 0, 0, 0), 0.60).save(OUT / "resources/android/icon-foreground.png")
    Image.new("RGB", (1024, 1024), WHITE[:3]).save(OUT / "resources/android/icon-background.png")

    # In-app logos keep alpha so they sit on light and dark grounds alike.
    fit(lockup, 1200, 840).save(OUT / "src/assets/img/login_logo.png")
    fit(lockup, 600, 420).save(OUT / "src/assets/img/top_logo.png")

    for p in sorted(OUT.rglob("*.png")):
        i = Image.open(p)
        print(f"  {p.relative_to(OUT)!s:44} {str(i.size):14} {i.mode}")


if __name__ == "__main__":
    main()
