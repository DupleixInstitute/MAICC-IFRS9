import sys
D='C:/xampp/htdocs/MAICC-IFRS9/docs/manuals/installation/'
def load(name):
    return open(D+name,encoding='utf-8',newline='').read().replace('\r\n','\n')
def save(name,s):
    open(D+name,'w',encoding='utf-8',newline='').write(s.replace('\n','\r\n'))
def rep(name,pairs):
    s=load(name)
    for o,n in pairs:
        if s.count(o)!=1:
            raise SystemExit(f'{name}: match count {s.count(o)} for: {o[:80]}')
        s=s.replace(o,n)
    save(name,s)
