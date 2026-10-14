import re,collections,sys
a=open('orig.md',encoding='utf-8').read()
b=open(sys.argv[1],encoding='utf-8').read()
def fm(t): return t.split('\n---\n',1)[0]
print('frontmatter same:',fm(a)==fm(b))
ha=[l for l in a.splitlines() if l.startswith('#')]; hb=[l for l in b.splitlines() if l.startswith('#')]
print('headings',len(ha),len(hb),ha==hb)
ba=collections.Counter(re.findall(r'`[^`\n]+`',a)); bb=collections.Counter(re.findall(r'`[^`\n]+`',b))
print('backticks missing:',ba-bb,'added:',bb-ba)
def tables(t):
    out=[];cur=0
    for l in t.splitlines()+['']:
        if l.startswith('|'): cur+=1
        elif cur: out.append(cur);cur=0
    return out
ta,tb=tables(a),tables(b); print('tables',len(ta),len(tb),ta==tb,ta)
def cols(t): return [l.count('|') for l in t.splitlines() if l.startswith('|')]
print('pipe counts same:',cols(a)==cols(b))
def firstcol(t): return [l.split('|')[1] for l in t.splitlines() if l.startswith('|')]
fa,fb=firstcol(a),firstcol(b); print('first col diffs:',[(x,y) for x,y in zip(fa,fb) if x!=y])
fig=lambda t:re.findall(r'!\[.*?\]\((.*?)\)',t); print('figs',fig(a)==fig(b),len(fig(b)))
ia=a.index('## Annex'); ib=b.index('## Annex')
ra=a[ia:].split('**Read every month**')[1]; rb=b[ib:].split('**Read every month**')[1]
print('annex tables identical:',ra==rb)
ga=[l for l in a.splitlines() if l.startswith('- `GRANT') or l.startswith('- `CREATE')]; gb=[l for l in b.splitlines() if l.startswith('- `GRANT') or l.startswith('- `CREATE')]
print('grant list same',ga==gb,len(ga))
print('words',len(a.split()),len(b.split()), round(len(b.split())/len(a.split())*100-100,1),'%')
print('Barry count',a.count('Barry'),b.count('Barry'))
nums=lambda t:collections.Counter(re.findall(r'\d[\d,\.]*',t))
print('numbers missing:',nums(a)-nums(b)); print('numbers added:',nums(b)-nums(a))
