import sys,re
orig=open(sys.argv[1],encoding='utf-8').read().split('\n')
target=sys.argv[2]
new=open(target,encoding='utf-8').read()
def block(title):
    start=None
    for i,l in enumerate(orig):
        if l.startswith("        '"+title+"' => ["):
            start=i;break
    assert start is not None,title
    for j in range(start+1,len(orig)):
        if orig[j]=="        ],":
            return '\n'.join(orig[start:j+1])
    raise Exception(title)
for t in ['Signing in and the security check','Your profile, password and two-factor authentication']:
    ph='/*KEEP:'+t+'*/'
    assert ph in new
    new=new.replace(ph,block(t))
open(target,'w',encoding='utf-8',newline='\n').write(new)
print('ok')
