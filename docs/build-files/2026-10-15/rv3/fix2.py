p='report.py'; L=open(p,encoding='utf-8').read().split('\n')
Q=chr(92)+"'"
L[574] = "w(f'| Mega Farm loan book, 31 Dec 2025 (GLs 1050301 to 1050307) | {ties[\"mf_2025_12\"][\"ca\"]:,.2f} | {mc[\"model_ead\"]:,.2f} (MAIIC" + Q + "s Mega Farm model) | Ties within {ties[\"mf_2025_12\"][\"ca\"] - mc[\"model_ead\"]:,.2f} kwacha (one account in credit); {n(mc[\"matched\"])} of {n(mc[\"rows\"])} accounts by number |')"
L[576] = "w(f'| Mega Farm ledger against E-Banker" + Q + "s Loan Book Report, every account-month with a report run | 77,842 of 78,023 tie to the cent (rebuild check, 14 October) | {n(mftie.get(\"1\", 0))} account-months where the report prints a credit balance as a debit: the ledger stands, flagged | The one remaining account-month has no ledger posting and keeps the report" + Q + "s figure, flagged |')"
open(p,'w',encoding='utf-8').write('\n'.join(L))
