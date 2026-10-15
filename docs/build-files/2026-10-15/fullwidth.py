import re, pathlib
root = pathlib.Path(r'c:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages')
cap = re.compile(r'^max-w-(4xl|5xl|6xl|7xl|screen-(lg|xl|2xl))$')
changed = 0
def fix(m):
    global changed
    cls = m.group(1).split()
    if 'mx-auto' not in cls or not any(cap.match(c) for c in cls):
        return m.group(0)
    if any(c.startswith(('max-h', 'shadow', 'rounded', 'fixed', 'absolute')) for c in cls):
        return m.group(0)  # a dialog or card, not a page wrapper
    keep = [c for c in cls if not cap.match(c) and c not in ('mx-auto', 'sm:px-6', 'lg:px-8')]
    if 'w-full' not in keep:
        keep.insert(0, 'w-full')
    changed += 1
    return 'class="' + ' '.join(keep) + '"'
files = 0
for f in root.rglob('*.vue'):
    s = f.read_text(encoding='utf-8')
    n = re.sub(r'class="([^"]*)"', fix, s)
    if n != s:
        f.write_text(n, encoding='utf-8', newline='') if False else open(f, 'w', encoding='utf-8', newline='').write(n)
        files += 1
print(files, 'files,', changed, 'wrappers widened')
