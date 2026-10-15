import sys
def edit(path, old, new, count=1):
    s=open(path,encoding='utf-8',newline='').read()
    crlf='\r\n' in s
    if crlf:
        old=old.replace('\r\n','\n').replace('\n','\r\n'); new=new.replace('\r\n','\n').replace('\n','\r\n')
    n=s.count(old)
    if n!=count: raise SystemExit(f"{path}: found {n} of {old[:70]!r}")
    s=s.replace(old,new)
    open(path,'w',encoding='utf-8',newline='').write(s)
    print("ok",path,"crlf" if crlf else "lf")
