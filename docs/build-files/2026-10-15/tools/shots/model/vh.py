"""Small helpers for editing Vue files: whitespace-tolerant replace."""
import re

def load(p):
    s = open(p, encoding='utf-8').read().replace('\r\n', '\n')
    # trailing spaces on lines make exact matching brittle
    return re.sub(r'[ \t]+\n', '\n', s)

def save(p, s):
    open(p, 'w', encoding='utf-8', newline='\n').write(s)

def rep(s, a, b, count=1):
    if a in s:
        return s.replace(a, b, count)
    # whitespace-tolerant fallback
    pat = r'\s+'.join(re.escape(x) for x in a.split())
    m = re.search(pat, s)
    if not m:
        raise SystemExit('NOT FOUND: ' + a[:140])
    return s[:m.start()] + b + s[m.end():]

def rx(s, pat, b, flags=re.S):
    n = re.subn(pat, lambda m: b, s, count=1, flags=flags)
    if n[1] == 0:
        raise SystemExit('REGEX NOT FOUND: ' + pat[:140])
    return n[0]
