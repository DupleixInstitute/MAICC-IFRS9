# Bootstrap inputs

The client input files a clean install of MAICC-IFRS9 is bootstrapped from, committed so that a
server builds the whole EIR data foundation without an external file (spec v4, section 6.11).
The committed copy is read first; a OneDrive path is only a local fallback.

| Folder | What | Loaded by |
|---|---|---|
| `ebanker-pack-2026-10-07/` | The 18 follow-up extracts of 7 October 2026 and the 5 afternoon queries: the ledger, masters, rates, PLR, charges, loan-book history, balance history, charts, plans, schedules, status history, daily accrual | `eir:bootstrap` step 3 (route 1 pack into the landing zone) |
| `ebanker-pack-2026-10-08/` | Pack 2 of 8 October 2026: the ledger and balance history since August, the interest product, security, guarantors, LOS requests, the GL_03 batch legs | step 3, with the first pack (listed in the top-level manifest) |
| `ebanker-pack-2026-10-09/` | Pack 3 of 9 October 2026, with its own manifest: the Mega Farm loan master, month-end balances, status history, rate set-up and charges; the September 2026 Loan Book Report runs; the scheme master; table counts | step 3, landed after the first pack (`landLaterPacks`) |
| `packs/ebanker-pack-2026-10-12.zip` | Pack 4 of 12 October 2026, zipped because the CSVs are 143 MB (12.4 MB zipped): the full Mega Farm book (6,974 accounts), its ledger (298,259 rows) and month-ends December 2024 to September 2026; the full charges table; fee postings; write-offs; the reschedule history; E-Banker's own trial balance rows. Unzipped into `storage/app/bootstrap-packs/` | step 3, landed after pack 3 |
| `data-dictionary-2026-10-06/` | The 24 data-dictionary results of 6 October 2026 (tables, columns, keys, schemes, codes, row counts, samples) | reference; `DD_09` schemes read for product names |
| `takeon/` | Tamanda's take-on workbook as received (11 Sep 2026) and the mapping workbook returned by MAIIC on 13 Oct 2026 with the fees completed (101 facilities, 362,307,251.23); the 7 Oct copy sent for completion is kept under `takeon/superseded/` | `eir:bootstrap` step 4 (take-on build: the EIR recomputed from origination with the fees) |
| `trial-balances/monthly/` | The 20 monthly trial balances, January 2025 to August 2026, as received from Finance | `eir:bootstrap` step 3 (landed as `ebanker_trial_balances`; the GL side of the reconciliation is derived from them) |
| `trial-balances/afs-bridge-2025-12/` | The December 2025 AFS bridge in its two received versions (19 Aug 2026, amounts as text; 10 Sep 2026, amounts numeric, the one that is loaded): final AFS TB, initial TB to the auditor, TB December 2025, final E-Banker TB mapped | step 3; sheet 'Final E-Banker TB Dec 2025' |
| `trial-balances/afs-mapping-2023-to-2026-08/` | Every GL line mapped to its audited-accounts category with year-end balances Dec 2023, Dec 2024, Dec 2025 and Aug 2026 | committed for the category bridge to the audited accounts (F17); no code reads it yet |
| `queries/` | The SQL that produced the pack, version 1 | the E-Banker Feed's query register |
| `manifest.json` | Every file with its query id, row count and SHA-256; the accepted exceptions the gates allow | the gates |

Every file is exactly as received. None is opened or re-saved in Excel. A replacement (for example the
workbook returned by Finance with the fees filled in) is a new file beside the old one and a new manifest
entry; nothing here is edited in place.

Packs 5 to 7 (12 October 2026) are not here yet: their query ids are not in the query register, and pack 7 (the ledgers from before the migration) must not join the live ledger family. Pack 8 has no results yet.

Dates in this first pack are m/d/yyyy, as the export tool wrote them; the gates parse that format for this
pack by its manifest, and ISO for every later pack produced with the RUN 0 session settings.
