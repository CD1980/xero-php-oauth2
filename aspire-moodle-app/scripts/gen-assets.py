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


def opaque_rows(im):
    """Count of opaque pixels in each row."""
    w, h = im.size
    px = im.load()
    return [sum(1 for x in range(w) if px[x, y][3] > 128) for y in range(h)]


def find_mark_band(im):
    """The mark is the first run of non-blank rows, before the blank gap."""
    rows = opaque_rows(im)
    filled = [y for y, n in enumerate(rows) if n > 0]
    if not filled:
        sys.exit("source image is fully transparent")

    start = filled[0]
    for y in range(start, len(rows)):
        if rows[y] == 0:
            return start, y - 1
    sys.exit("no blank band found between mark and wordmark — pass --mark-rows")


def band_bbox(im, y0, y1):
    w = im.size[0]
    px = im.load()
    pts = [(x, y) for y in range(y0, y1 + 1) for x in range(w) if px[x, y][3] > 128]
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
    ap.add_argument("--mark-rows", nargs=2, type=int, metavar=("Y0", "Y1"))
    args = ap.parse_args()

    im = Image.open(args.source).convert("RGBA")
    y0, y1 = args.mark_rows if args.mark_rows else find_mark_band(im)
    print(f"source {args.source} {im.size}; mark rows {y0}-{y1}")

    if im.width < 2048:
        print(f"NOTE: source is only {im.width}px wide. The 1024px icon will be "
              f"upscaled and will look soft. Export a larger PNG from the vector "
              f"original for store-quality artwork.")

    mark = im.crop(band_bbox(im, y0, y1))
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
