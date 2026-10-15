import glob, re
for f in sorted(glob.glob('C:/xampp/htdocs/MAICC-IFRS9/docs/manuals/installation/*.md')):
    lines = open(f, encoding='utf-8').read().split('\n')
    incode = False
    hdr = None
    for i, l in enumerate(lines, 1):
        l = l.rstrip('\r')
        if l.startswith('```'):
            incode = not incode
            continue
        if incode:
            continue
        if l.startswith('|'):
            t = re.sub(r'`[^`]*`', lambda m: m.group(0).replace('|', 'x'), l)
            t = t.replace('\\|', 'x')
            n = t.count('|')
            if hdr is None:
                hdr = n
            elif n != hdr:
                print(f, i, n, hdr, l[:90])
        else:
            hdr = None
print('done')
