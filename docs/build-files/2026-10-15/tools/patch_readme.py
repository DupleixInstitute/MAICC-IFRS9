P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/bootstrap/README.md'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:60]
    s = s.replace(a, b, 1)

rep("""| `data-dictionary-2026-10-06/` |""",
"""| `ebanker-pack-2026-10-08/` | Pack 2 of 8 October 2026: the ledger and balance history since August, the interest product, security, guarantors, LOS requests, the GL_03 batch legs | step 3, with the first pack (listed in the top-level manifest) |
| `ebanker-pack-2026-10-09/` | Pack 3 of 9 October 2026, with its own manifest: the Mega Farm loan master, month-end balances, status history, rate set-up and charges; the September 2026 Loan Book Report runs; the scheme master; table counts | step 3, landed after the first pack (`landLaterPacks`) |
| `packs/ebanker-pack-2026-10-12.zip` | Pack 4 of 12 October 2026, zipped because the CSVs are 143 MB (12.4 MB zipped): the full Mega Farm book (6,974 accounts), its ledger (298,259 rows) and month-ends December 2024 to September 2026; the full charges table; fee postings; write-offs; the reschedule history; E-Banker's own trial balance rows. Unzipped into `storage/app/bootstrap-packs/` | step 3, landed after pack 3 |
| `data-dictionary-2026-10-06/` |""")
rep("""| `takeon/` | The take-on amortisation schedules mapped to E-Banker accounts, with the fee columns for Finance | `eir:bootstrap` step 4 (take-on build) |""",
"""| `takeon/` | Tamanda's take-on workbook as received (11 Sep 2026) and the mapping workbook returned by MAIIC on 13 Oct 2026 with the fees completed (101 facilities, 362,307,251.23); the 7 Oct copy sent for completion is kept under `takeon/superseded/` | `eir:bootstrap` step 4 (take-on build: the EIR recomputed from origination with the fees) |""")
rep("""| step 3; the category bridge to the audited accounts (F17) |""",
"""| committed for the category bridge to the audited accounts (F17); no code reads it yet |""")
rep("""Dates in this first pack are m/d/yyyy,""",
"""Packs 5 to 7 (12 October 2026) are not here yet: their query ids are not in the query register, and pack 7 (the ledgers from before the migration) must not join the live ledger family. Pack 8 has no results yet.

Dates in this first pack are m/d/yyyy,""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
