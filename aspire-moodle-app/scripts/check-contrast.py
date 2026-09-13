def lum(h):
    h = h.lstrip('#')
    c = [int(h[i:i+2], 16) / 255 for i in (0, 2, 4)]
    c = [x / 12.92 if x <= 0.04045 else ((x + 0.055) / 1.055) ** 2.4 for x in c]
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]

def ratio(a, b):
    la, lb = lum(a), lum(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)

WHITE, BLACK = '#ffffff', '#000000'
cands = {
    'logo teal (dominant mark)': '#0079A0',
    'logo mid cyan':             '#009DCF',
    'document navy (H1/H2)':     '#1F3864',
    'document mid blue (H3)':    '#365F91',
    'brand accent (table hdr)':  '#92CDDC',
    'logo pale blue':            '#7DD3F7',
}
print(f"{'colour':30} {'hex':9} {'vs white':>9} {'vs black':>9}  verdict (needs 4.5:1 for body text)")
for name, hexv in cands.items():
    w, b = ratio(hexv, WHITE), ratio(hexv, BLACK)
    best = 'white text OK' if w >= 4.5 else ('black text OK' if b >= 4.5 else 'DECORATIVE ONLY')
    print(f"{name:30} {hexv:9} {w:8.2f}:1 {b:8.2f}:1  {best}")

print()
DARK_BG = '#282828'  # moodleapp $gray-900, the dark-theme background
print(f"dark theme: candidate primaries against background {DARK_BG}")
for name, hexv in cands.items():
    r = ratio(hexv, DARK_BG)
    print(f"  {name:30} {hexv:9} {r:6.2f}:1  {'OK' if r >= 4.5 else 'too low'}")
