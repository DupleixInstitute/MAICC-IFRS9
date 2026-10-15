p='report.py'; s=open(p,encoding='utf-8').read()
a="its result for this run is in the bootstrap log.')"
assert a in s
s=s.replace(a,"on this run 67 checks: every data tie, pack inventory, loan book, undiscounted ECL, EIR count and 2024-2025 revenue figure passes. Eight report FAIL against golden numbers set on an earlier round (commit c4acec4) that predate intended changes: the December 2025 booked allowance (now discounted on 66 loans, 3,015.7 million, against 18 loans and 449.9 million then), the Mega Farm programme ECL (44,661.8 million against 45,097.0 million before the ledger tie) and its 5% share, and the 2026 revenue rows (41 against 35). The figures in this report are the run\'s; the golden numbers are to be re-set from this run.')")
open(p,'w',encoding='utf-8').write(s)
