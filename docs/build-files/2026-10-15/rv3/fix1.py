import re
p='report.py'; s=open(p,encoding='utf-8').read()
BS = chr(92)
rep = [
("Infracon, Ebenezer Midian and Anchor Processors five or six instalments missed", "Infracon, Ebenezer Midian and Anchor Processors four to six instalments missed"),
("Stage 1 defaults include MMC (four instalments missed)", "Stage 1 defaults include MMC (five instalments missed), Happie Foods (disputed: E-Banker holds two schedules, question C1)"),
("typed bracket | {n(cz[", "typed bracket (some then also held in Stage 3 by the instalment trigger) | {n(cz["),
("| Held in Stage 3 by the four-missed-instalments trigger, or in Stage 2 by the cure period |", "| Same ageing bracket, but held higher by the four-missed-instalments trigger or the cure period |"),
("(four loans moved down to agree with MAIIC, three added by the trigger on the chart copies)", "(four loans moved down to agree with MAIIC after the verification, partly offset by the instalment trigger read on E-Banker" + BS + "'s dated chart copies)"),
]
for a,b in rep:
    assert a in s, a[:60]
    s=s.replace(a,b)
open(p,'w',encoding='utf-8').write(s)
p='md2docx.py'; s=open(p,encoding='utf-8').read()
a="""        size = 8 if ncol >= 9 else 8.5
"""
b="""        size = 8 if ncol >= 9 else 8.5
        numcol = {}
        for ci in range(1, ncol):
            vals = [r_[ci].replace('**', '') for r_ in tbl[1:] if ci < len(r_) and r_[ci].strip()]
            numcol[ci] = bool(vals) and sum(1 for x in vals if NUM.match(x) or re.match(r'^[-+]?[0-9][0-9,.]*%? (on|[(]|against)', x)) >= len(vals) * 0.6
"""
assert a in s; s=s.replace(a,b,1)
a="""                    plain = txt.replace('**', '')
                    if ci > 0 and NUM.match(plain or 'x'):
                        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
                if ri == 0 and ci > 0:
                    nums = [r_[ci].replace('**', '') for r_ in tbl[1:] if ci < len(r_)]
                    if nums and sum(1 for x in nums if NUM.match(x or 'x')) >= max(1, len(nums) * 0.6):
                        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT"""
b="""                if ci > 0 and numcol.get(ci):
                    p.alignment = WD_ALIGN_PARAGRAPH.RIGHT"""
assert a in s; s=s.replace(a,b,1)
open(p,'w',encoding='utf-8').write(s)
print('ok')
