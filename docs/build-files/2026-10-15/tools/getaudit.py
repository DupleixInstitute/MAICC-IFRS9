import json
F = 'C:/Users/DELL/.claude/projects/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421.jsonl'
OUT = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/auditB.txt'
found = []

def walk(o):
    if isinstance(o, str):
        if 'report: the portfolio rows have no portfolio name' in o:
            found.append(o)
    elif isinstance(o, list):
        for x in o: walk(x)
    elif isinstance(o, dict):
        for v in o.values(): walk(v)

for line in open(F, encoding='utf-8'):
    try:
        walk(json.loads(line))
    except Exception:
        pass
found.sort(key=len)
t = found[-1] if found else ''
open(OUT, 'w', encoding='utf-8').write(t)
print(len(found), len(t))
