"""Replace browser confirm()/alert() with the shared styled dialogs.
usage: python popconv.py file.vue [...]
- confirm('x')  -> await confirmDialog({ title: 'x', confirmLabel: 'Yes, continue' })
  and the enclosing function is made async.
- alert('... success ...') lines are dropped (the server flash or the reload shows it)
- alert(x)      -> notice(x)
Imports are added after the first import line of <script>."""
import re, sys, os
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save

FUNC_PATTERNS = [
    re.compile(r'(const\s+\w+\s*=\s*)(\([^()]*\)\s*=>\s*\{)'),          # const f = (a) => {
    re.compile(r'(const\s+\w+\s*=\s*)(\w+\s*=>\s*\{)'),                  # const f = a => {
    re.compile(r'(^|\n)(\s*)(function\s+\w+\s*\([^()]*\)\s*\{)'),        # function f(a) {
    re.compile(r'(^|\n)(\s*)((?!if\b|for\b|while\b|switch\b|catch\b)\w+\s*\([^()]*\)\s*\{)'),  # method f(a) {
]

def make_async(s, pos):
    """Make the function that encloses position pos async."""
    best = None
    for pat in FUNC_PATTERNS:
        for m in pat.finditer(s, 0, pos):
            if best is None or m.start() > best[0].start():
                best = (m, pat)
    if not best:
        return s, 0
    m, pat = best
    text = m.group(0)
    if 'async' in s[max(0, m.start()-12):m.end()]:
        return s, 0
    if pat is FUNC_PATTERNS[0] or pat is FUNC_PATTERNS[1]:
        new = m.group(1) + 'async ' + m.group(2)
    else:
        new = m.group(1) + m.group(2) + 'async ' + m.group(3)
    return s[:m.start()] + new + s[m.end():], len(new) - len(text)

def convert(path):
    s = load(path)
    sc = s.index('<script')
    head, body = s[:sc], s[sc:]
    n_conf = n_alert = 0
    # confirm(...)
    while True:
        m = re.search(r'(?<![\w.])confirm\(\s*', body)
        if not m:
            break
        # find matching paren
        depth, i = 0, m.end() - 1
        while i < len(body):
            if body[i] == '(':
                depth += 1
            elif body[i] == ')':
                depth -= 1
                if depth == 0:
                    break
            i += 1
        arg = body[m.end():i].strip()
        rep = 'await confirmDialog({ title: %s })' % arg
        body = body[:m.start()] + rep + body[i+1:]
        body, delta = make_async(body, m.start())
        n_conf += 1
    # alert(...)
    out_lines = []
    for line in body.split('\n'):
        mm = re.search(r'(?<![\w.])alert\(', line)
        if mm and re.search(r'success', line, re.I) and re.match(r'^\s*alert\(.*\);?\s*$', line):
            n_alert += 1
            continue
        if mm:
            line = line[:mm.start()] + 'notice(' + line[mm.end():]
            n_alert += 1
        out_lines.append(line)
    body = '\n'.join(out_lines)
    imports = []
    if n_conf and 'confirmDialog' not in body.split('export default')[0]:
        imports.append("import { confirmDialog } from '@/Components/confirmDialog'")
    if n_alert and "from '@/Components/Maiic/notice'" not in body:
        imports.append("import { notice } from '@/Components/Maiic/notice'")
    if imports:
        im = re.search(r'\n(\s*)import ', body)
        body = body[:im.start()] + '\n' + im.group(1) + ('\n' + im.group(1)).join(imports) + body[im.start():]
    save(path, head + body)
    print(path, 'confirm', n_conf, 'alert', n_alert)

for p in sys.argv[1:]:
    convert(p)
