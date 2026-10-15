import sys
p, newp = sys.argv[1], sys.argv[2]
s = open(p, encoding='utf-8').read()
new = open(newp, encoding='utf-8').read()
start = s.index('### 2.3 Navigation')
end = s.index('### 2.5 Design system')
s = s[:start] + new + s[end:]
s = s.replace('### 2.7 Conventions', '### 2.9 Conventions').replace('### 2.6 Module map', '### 2.8 Module map').replace('### 2.5 Design system', '### 2.7 Design system')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
