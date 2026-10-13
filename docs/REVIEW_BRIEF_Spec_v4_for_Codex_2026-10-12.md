# Review brief: MAIIC EIR Engine Specification v4

For an independent reviewer (Codex). Read this first; it says where everything is, what changed most recently, what to check, and the conventions to keep. 12 October 2026.

## 1. Where the specification is

| What | Where |
|---|---|
| **The specification (source of truth)** | Repository `https://github.com/DupleixInstitute/MAICC-IFRS9`, branch **`eir_revenue_recognition`**, file **`docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md`** (1,334 lines, sections 0 to 17) |
| Rendered copies (same text) | OneDrive `2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.docx` and `.pdf`, built by `docs/build-files/spec_render.py` |
| Version 3 (superseded; still applies where v4 is silent) | OneDrive `9. Archive\Specifications superseded by v4 (2026-10-07)\` |

Review the Markdown in the repository. Pull the branch first: the latest commit touching the spec is `7bc97a5` (12 October 2026).

## 2. The context: what the engine is

MAIIC (Malawi Agricultural and Industrial Investment Corporation) runs its loans in a core banking system called **E-Banker** (Oracle) and keeps its statutory accounts in **QuickBooks**. E-Banker has no effective-interest-rate (EIR) calculation, so the 2025 audit used estimated adjustments. Dupleix Institute is building an IFRS 9 EIR engine (with an ECL module) inside this Laravel application. The current focus is understanding E-Banker for three purposes: pulling the data out, reconciling it, and integrating with it. The data arrives as read-only SQL extracts run by MAIIC's ICT (Barry), in "packs".

## 3. Read in this order

1. Section 0 (how the document is written: "Agreed", "Decided and seeded", "Proven") and section 1 (the change log; the newest rows are at the top).
2. Sections 2 and 3: the data and what is proven about E-Banker's arithmetic. **Sections 3.7, 3.8 and 3.9 are new (11 to 12 October 2026) and are the main things to review.**
3. Section 4.2: every Governance Centre setting with its default.
4. Sections 6 and 7: ingestion and the importers. **Section 7.8 (fees, end to end) is new.**
5. Sections 8 and 9: the build plan and the acceptance baselines (the figures every build must reproduce).

## 4. What changed most recently, and the evidence

| Section | Claim | Evidence to check |
|---|---|---|
| 3.7 | The fee GLs 4871 and 4873 agree to the trial balance every month; about 44 percent of 2025 fee income never touched a loan account; QuickBooks 4206 = E-Banker 4215 + 4216 + 42019, plus a 21,865,867.35 suspense reversal, plus 259,317,000 of fees presented as EIR interest (an audit estimate) | `docs/build-files/fee_gl_recon_2026-10-11.py`; the GL spools and trial balances in OneDrive `2. Documents from clients\New Doc Received 19 Aug\` and `Trial Balances\`; the AFS mapping workbook in `AFS 2025\` |
| 3.8 | Status code H means written off and closed; a batch on 29 June 2026 (TRF 3, type 305 "ACCOUNT CLOSURE") cleared 38,492,032.54 into GL 1064; a write-off must never be read as a collection | `P1_02`, `P3_13`, `P2_09` and `P1_01` under `docs/bootstrap/`; the June 2026 trial balance; Finance's loan books in OneDrive `New Doc Received 11 Sep\`; E-Banker manual section 7.4 (`New Doc Received 21 Sep\Ebanker MANUAL.pdf`) |
| 3.9 | Reschedules can be rebuilt from E-Banker's own tables (superseded instalment rows, four dated chart copies, MODIFICATION_LOG); the stored loan books are not a register | `DD_02_tables.csv` and `DD_03_columns.csv` (data dictionary, 6 October); `P2_08` |
| 7.8 | How fees are ingested and reconciled: six sources, one source of record (the GL posting), five delivery routes, the batch link to the loan, five checks, the settings, Finance's steps | Section 3.7; the research note `docs/MAIIC_Fees_2025_Workbook_GL_and_Design_2026-10-11.md` |

Related notes: `docs/MAIIC_TB_to_AFS_analysis_2026-09-24.md` (corrected 11 October); `docs/sessions/README.md` lists the session transcripts of 5 to 9 October; `docs/build-files/README.md` says which script produced which figure.

## 5. What to look for

- **Internal consistency**: a figure, a setting name or a query id stated differently in two sections (for example section 3.7 against 7.8, 4.2 against 7.8.6, section 5 against 3.8 and 3.9, section 9 against section 3).
- **Claims the evidence does not support**: in particular the readings of status H (3.8) and the fee sources (3.7, 7.8). Say where an inference is presented as proven.
- **Built against "to build"**: the spec marks each item; check the code agrees. Built on 11 October: `app/Services/Eir/FeeGlReconciliationService.php`, the `fee_gl_reconciliation` setting in `app/Services/Eir/GovernanceService.php`, the `eir.fees.import` permission. Not built yet: `fee_source_precedence`, the `GL_04` batch link, the write-off rule of 3.8, the reschedule register of 3.9.
- **IFRS 9 treatment**: fees in the EIR (B5.4.1 to B5.4.3), write-offs (5.4.4), modifications versus resets (5.4.3, B5.4.5), interest on credit-impaired loans (5.4.1(b)).
- **Gaps**: anything the engine needs that no section says how it is obtained.

## 6. Conventions to keep in any suggested text

- Plain language first in every section; the same three words (Agreed, Decided and seeded, Proven) as section 0.
- **ASCII hyphens only: no em dashes or en dashes** (a pre-commit check refuses them).
- Amounts in kwacha with thousands separators and two decimals where the source has them.
- Never propose a hardcoded or fallback figure: a missing input fails closed and is named.

## 7. How to return the review

A Markdown file `docs/REVIEW_Spec_v4_Codex_<date>.md` with one row per finding: section, the text quoted, the issue, the evidence, and the suggested correction. Do not edit the specification directly; the Dupleix team applies the corrections and records them in section 1.
