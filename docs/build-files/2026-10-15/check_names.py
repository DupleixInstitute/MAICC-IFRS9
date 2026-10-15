import re, os, sys, subprocess
base = sys.argv[1]
files = sys.argv[2:]
src = []
for d in ['app', 'config', 'database', 'routes', 'tests', 'scripts', 'docker', '.github', 'resources/js', 'docs/bootstrap']:
    for root, _, fs in os.walk(os.path.join(base, d)):
        if 'node_modules' in root or 'ebanker-pack' in root or 'data-dictionary' in root:
            continue
        for f in fs:
            if f.endswith(('.php', '.js', '.vue', '.yml', '.sh', '.conf', '.json', '.md', '.xml')):
                p = os.path.join(root, f)
                try:
                    src.append(open(p, encoding='utf-8', errors='replace').read())
                except Exception:
                    pass
for extra in ['phpunit.xml', '.env.example', 'vite.config.js', 'composer.json']:
    src.append(open(os.path.join(base, extra), encoding='utf-8', errors='replace').read())
blob = '\n'.join(src)
missing = set()
for f in files:
    text = open(f, encoding='utf-8').read()
    for tok in re.findall(r'`([^`]+)`', text):
        t = tok.strip()
        cands = []
        if '/' in t and ' ' not in t and not t.startswith('-'):
            p = t.split('{')[0].rstrip('/')
            if not os.path.exists(os.path.join(base, p)) and not os.path.exists(os.path.join(base, 'tests', p)):
                missing.add('PATH ' + t)
            continue
        head = t.split(' ')[0].split('(')[0].split('=')[0]
        head = head.split('::')[-1] if '::' in head else head
        if re.match(r'^[A-Za-z_][\w:.\-]*$', head) and len(head) > 2:
            if head not in blob:
                missing.add('TOK ' + t)
for m in sorted(missing):
    print(m)
