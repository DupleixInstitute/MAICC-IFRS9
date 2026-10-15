import glob
BS = chr(92)
for f in sorted(glob.glob('C:/xampp/htdocs/MAICC-IFRS9/docs/manuals/installation/*.md')):
    for i, l in enumerate(open(f, encoding='utf-8'), 1):
        if l.startswith('|'):
            parts = l.split('`')
            for k in range(1, len(parts), 2):
                sp = parts[k]
                for j, ch in enumerate(sp):
                    if ch == '|' and (j == 0 or sp[j-1] != BS):
                        print(f[-40:], i, sp)
