"""Strip scoped copies of the shorthand classes now defined globally in app.css.
usage: python strip.py file1.vue file2.vue ...
Keeps any other rule (form-input, field, action, sub-th, ...)."""
import re, sys

DROP = re.compile(r'^(\.th|\.td|\.primary-btn|\.secondary-btn|\.danger-btn|\.form-label|\.badge-(green|blue|yellow|red|gray|grey)|tbody tr:nth-child\(even\)|tbody tr:hover)\s*\{')

def split_rules(css):
    rules, depth, cur = [], 0, ''
    for ch in css:
        cur += ch
        if ch == '{':
            depth += 1
        elif ch == '}':
            depth -= 1
            if depth == 0:
                rules.append(cur.strip()); cur = ''
    if cur.strip():
        rules.append(cur.strip())
    return rules

for path in sys.argv[1:]:
    s = open(path, encoding='utf-8').read()
    m = re.search(r'<style( scoped)?>(.*?)</style>', s, re.S)
    if not m:
        print(path, 'no style'); continue
    rules = split_rules(m.group(2))
    keep = [r for r in rules if not DROP.match(r)]
    dropped = len(rules) - len(keep)
    if keep:
        new = '<style scoped>\n' + '\n'.join(keep) + '\n</style>'
    else:
        new = ''
    s = s[:m.start()] + new + s[m.end():]
    s = re.sub(r'\n{3,}$', '\n', s)
    if not s.endswith('\n'):
        s += '\n'
    open(path, 'w', encoding='utf-8').write(s)
    print(path, 'dropped', dropped, 'kept', len(keep))
