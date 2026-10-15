import re, os, collections, sys
base = sys.argv[1]
tot = 0; files = 0; by = collections.Counter(); fby = collections.Counter()
for root, _, fs in os.walk(os.path.join(base, 'tests')):
    for f in fs:
        if not f.endswith('Test.php'):
            continue
        p = os.path.relpath(os.path.join(root, f), base).replace(os.sep, '/')
        s = open(os.path.join(root, f), encoding='utf-8', errors='replace').read()
        n = len(re.findall(r'public function test\w*\s*\(', s))
        n += len(re.findall(r'(?:@test\b|#\[Test\])[^{;]*?public function (?!test)\w+', s, re.S))
        parts = p.split('/')
        top = '/'.join(parts[:3]) if len(parts) > 3 else '/'.join(parts[:2])
        by[top] += n; fby[top] += 1
        if n:
            files += 1
        tot += n
print('files with tests', files, 'test methods', tot)
for k in sorted(by):
    print(k, fby[k], by[k])
