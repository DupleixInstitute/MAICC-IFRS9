# Bootstrap inputs

The client input files a clean install of MAICC-IFRS9 is bootstrapped from, committed so that a
server builds the whole EIR data foundation without an external file (spec v4, section 6.11).
The committed copy is read first; a OneDrive path is only a local fallback.

| Folder | What | Loaded by |
|---|---|---|
| `ebanker-pack-2026-10-07/` | The 18 follow-up extracts of 7 October 2026 and the 5 afternoon queries: the ledger, masters, rates, PLR, charges, loan-book history, balance history, charts, plans, schedules, status history, daily accrual | `eir:bootstrap` step 3 (route 1 pack into the landing zone) |
| `data-dictionary-2026-10-06/` | The 24 data-dictionary results of 6 October 2026 (tables, columns, keys, schemes, codes, row counts, samples) | reference; `DD_09` schemes read for product names |
| `takeon/` | The take-on amortisation schedules mapped to E-Banker accounts, with the fee columns for Finance | `eir:bootstrap` step 4 (take-on build) |
| `trial-balances-2025-01-to-2026-07/` | The 19 monthly trial balances (January 2025 to July 2026) and the AFS bridge workbook for December 2025, as received from Finance on 19 August 2026 | `eir:bootstrap` step 3 (landed as `ebanker_trial_balances`; the GL side of the reconciliation is derived from them) |
| `queries/` | The SQL that produced the pack, version 1 | the E-Banker Feed's query register |
| `manifest.json` | Every file with its query id, row count and SHA-256; the accepted exceptions the gates allow | the gates |

Every file is exactly as received. None is opened or re-saved in Excel. A replacement (for example the
workbook returned by Finance with the fees filled in) is a new file beside the old one and a new manifest
entry; nothing here is edited in place.

Dates in this first pack are m/d/yyyy, as the export tool wrote them; the gates parse that format for this
pack by its manifest, and ISO for every later pack produced with the RUN 0 session settings.
