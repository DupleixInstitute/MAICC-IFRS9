d = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/e123/'
p = 'tests/Feature/Eir/ScheduleShapeGovernanceTest.php'
raw = open(p, 'rb').read().decode('utf-8')
crlf = '\r\n' in raw
s = raw.replace('\r\n', '\n').rstrip()
assert s.endswith('}')
s = s[:-1].rstrip() + '\n' + open(d + 'schedtests.txt', encoding='utf-8').read()
if crlf:
    s = s.replace('\n', '\r\n')
open(p, 'wb').write(s.encode('utf-8'))
print('ok', crlf)
