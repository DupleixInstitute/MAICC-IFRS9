import re,os
B=chr(92)
root="C:/xampp/htdocs/MAICC-IFRS9"
names=set()
pat=re.compile("Tests[" + B+B + "]Feature[" + B+B + "][A-Za-z" + B+B + "]+(?:::[a-z_0-9]+)?")
for s in ["Contract_Schedule1_Acceptance_Audit","IFRS9_EIR_Amortised_Cost_Compliance_Audit","IFRS9_Impairment_Compliance_Audit","IFRS7_IAS1_Disclosure_Compliance_Audit"]:
    names|=set(pat.findall(open(root+"/docs/compliance/"+s+".md",encoding="utf8").read()))
for t in sorted(names):
    cls,_,m=t.partition("::")
    p=root+"/tests/"+cls[6:].replace(B,"/")+".php"
    if not os.path.exists(p): print("MISSING",t); continue
    src=open(p,encoding="utf8").read()
    if m: print("OK" if ("function "+m) in src else "NOMETHOD",t)
    else: print("OK",t,len(re.findall("function test",src)))
