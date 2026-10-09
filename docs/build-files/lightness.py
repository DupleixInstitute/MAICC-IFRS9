"""Share of near-white pixels in the content area (right of the sidebar) of each dark-mode capture."""
import sys, os
from PIL import Image

d = sys.argv[1]
rows = []
for f in sorted(os.listdir(d)):
    if not f.endswith('.jpg'):
        continue
    im = Image.open(os.path.join(d, f)).convert('L')
    w, h = im.size
    im = im.crop((int(w * 0.22), int(h * 0.06), w, h)).resize((max(1, (w - int(w * 0.22)) // 4), max(1, (h - int(h * 0.06)) // 4)))
    px = list(im.getdata())
    light = sum(1 for p in px if p > 215) / len(px)
    rows.append((light, f))
for light, f in sorted(rows, reverse=True):
    print(f"{light*100:6.1f}%  {f}")
