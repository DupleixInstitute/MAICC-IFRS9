import sys
p=sys.argv[1]
s=open(p,encoding='utf-8').read()
rep=[
(r'<b>No report matches "..." . Try a shorter word, such as ECL, stage or RBM.</b>', r'<b>No report matches "...". Try a shorter word, such as ECL, stage or RBM.</b>'),
(r'<li><b>High Utilisation counts loans with no facility.</b> A loan with no recorded utilisation rate is not counted; only a recorded rate of 0.9 or more is.</li>', r'<li><b>High Utilisation reads 0.</b> Only a recorded utilisation rate of 0.9 or more is counted; a loan with no utilisation rate on the loan book is not.</li>'),
(r'<li>Stage 3 carries a PD of one hundred per cent by construction; read its coverage as the LGD in force.</li>', r'<li>Read Coverage % by stage: it should rise from Stage 1 to Stage 3. A Stage 3 coverage below the Stage 2 coverage is worth a question to the model owner.</li>'),
(r'Data quality and exceptions and the standalone movement tables, as a section headed', r'Data quality and exceptions and the standalone Opening to closing ECL and ECL charge or release tables, as a section headed'),
(r'<li><b>Run</b> (a play icon), on the Concentration and large exposures tab of ECL by segment only: reloads that tab with the large-exposure threshold typed beside it.</li>', r'<li><b>Run</b> (a play icon), on the concentration report only (the Concentration and large exposures tab of ECL by segment): reloads it with the large-exposure threshold typed beside it.</li>'),
(r"<li><b>Does not tie.</b> The loan counts or amounts of the two books do not agree with the movements, usually because a contract changed portfolio between the months (it then counts as gone in one and new in the other only if both books are this portfolio\'s). Check the Difference in loans first.</li>", r"<li><b>Does not tie.</b> The bridge ties by construction when each contract appears once in each month. A difference almost always means a contract appears more than once in one month\'s book for the portfolio, so it is counted twice; the IFRS 9 note\'s checks name duplicated contracts. Correct the loan book and run again.</li>"),
]
for a,b in rep:
    n=s.count(a)
    print(n, a[:50])
    s=s.replace(a,b)
open(p,'w',encoding='utf-8',newline='\n').write(s)
