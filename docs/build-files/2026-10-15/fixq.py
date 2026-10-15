import sys
for p in sys.argv[1:]:
    s=open(p,encoding='utf-8').read()
    n=s.count(chr(92)+"'")
    s=s.replace(chr(92)+"'","'")
    open(p,'w',encoding='utf-8',newline='\n').write(s)
    print(p,n)
