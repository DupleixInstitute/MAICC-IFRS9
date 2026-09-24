# IFRS 9 for the MAIIC Engine — A Course for Kundai

**Audience:** Kundai, developer on the MAIIC IFRS 9 system (this repo)
**Author:** Dupleix Institute
**Purpose:** Build the **Effective Interest Rate (EIR) / interest recalculation module** — with enough IFRS 9 understanding that you can make the hundred small judgement calls the spec doesn't cover.

---

## Why this course exists (read this first)

On **19 May 2026**, MAIIC's Margaret Chirombe sent us a document titled *"Understanding IFRS 9 EIR Loan Revenue Recognition — MAIIC Requirements (ECL Module)"*. It asked us to confirm **in writing** that our engine can:

1. **Calculate the EIR** used to discount estimated contractual cashflows for ECL purposes; and
2. **Calculate EIR revenue** as demonstrated in their Table 2.

On **20 May 2026**, Themba Mazibuko signed that confirmation: the system computes IFRS 9 revenue recognition for **Stages 1, 2 and 3**, at **no additional cost**.

That signature is why you are reading this. This is not a feature request — it is a **contractual promise made in a competitive bid** against Avati (who quoted $70,000 to our $30,000). MAIIC's Board was due to decide at end-June 2026.

**The uncomfortable truth:** at the time of writing, this repo cannot do what we said it does. There is no EIR anywhere in the codebase. Not a column, not a class, not a test. Your job is to close that gap. This course teaches you what you need to close it *correctly*, not just plausibly.

---

## How to use this course

Read in order. Each module is designed as a **lesson** — explained the way a teacher would explain it, with everyday analogies, not as a standards excerpt. IFRS 9 is genuinely simple once the picture lands; it only looks hard because accountants write for accountants.

| # | Module | What you'll be able to do |
|---|---|---|
| [00](00-the-client.md) | **The Client: who MAIIC actually is** | Understand why a Malawian DFI is not a bank, and why that changes the model |
| [01](01-ifrs9-and-where-eir-lives.md) | **IFRS 9 foundations + where EIR lives** | Point to the *three* places EIR appears in IFRS 9 (most people know only one) |
| [02](02-eir-mechanics.md) | **EIR mechanics + MAIIC's worked example** | Reproduce MAIIC's Table 1 and Table 2 to the cent, and explain the 39.47% |
| [03](03-stage3-interest.md) | **Stage 3: the heart of the module** | Explain gross vs net, unwinding, cure, POCI — and what MAIIC does today |
| [04](04-sicr-for-a-dfi.md) | **SICR — and why a DFI is different** | Explain why a 30-day trigger is wrong for MAIIC and what to do instead |
| [05](05-sp-pd-lineage.md) | **The S&P PD lineage** | Trace a PD from an S&P study to `grade_tenor_pd` to `ecl_value` |
| [06](06-advanced-excel.md) | **Advanced Excel for IFRS 9 models** | Open any of our workbooks and work out how it functions, unaided |
| [07](07-audit-and-reporting.md) | **Interest during & after audit, and reporting** | Know what Deloitte will ask for and have it ready |
| [08](08-disclosure.md) | **Disclosure** | Know which tables the financial statements need from your code |
| [09](09-build-plan.md) | **The build plan** | Schema, solver, hooks, tests — the actual work |
| [10](10-defects-register.md) | **Defects found in the current engine** | Fix what's already broken before layering EIR on top |

**Modules 02, 03 and 09 are the load-bearing ones.** If you only have a week, read 00, 02, 03, 09.

---

## The five things that matter most

If you remember nothing else:

1. **EIR does not create revenue. It moves fees.** In MAIIC's own example, EIR interest income exceeds contractual interest income by **MK46,159,999** — and total fees are **MK46,160,000**. That is not a coincidence; it is the entire concept. EIR takes the fees you'd otherwise book on day one and spreads them across the loan's life as interest.

2. **Stage 3 is where interest changes basis.** Stages 1 and 2 earn EIR on the **gross** carrying amount. Stage 3 earns EIR on the **net** amount (gross − ECL allowance). This is the single rule MAIIC is buying, and the one thing their current model documents but does not do.

3. **The discount rate for ECL is the *original* EIR.** Not today's rate. Not the contractual rate. Not `0.10`. Today `CalculateDiscountingJob` uses `$loanBook->interest_rate ?? 0.10` — see [Module 10](10-defects-register.md).

4. **Units will destroy you.** MAIIC's own tapes carry interest rates as `32.75` (percent) in one file and `0.33` (fraction) in another. Our review report already flagged this as causing "Errors in Effective Interest Rate (EIR) calculations". `loan_books.interest_rate` is `decimal(8,2)` — it cannot even hold an EIR to the precision you need.

5. **Everything must be reproducible.** Deloitte will reperform your numbers. If an auditor cannot rebuild your amortisation schedule from stored data, the number does not exist. The importer spec already says it: *"No hidden derivations; Staging stored physically; Reproducibility of results."*

---

## A note on names

- The **client** is **MAIIC** — Malawi Agricultural and Industrial Investment Corporation plc.
- This **repo** is called `MAICC-IFRS9` (MAICC, transposed). It's a typo that stuck. Don't let it confuse you when searching.
- The OneDrive folder is `MAIIIC` (three I's). Also a typo. Also stuck.

---

## Source material

Everything in this course is traceable to real project artefacts:

| Source | Where |
|---|---|
| MAIIC's EIR requirements (the spec you're building to) | `UNDERSTANDING IFRS 9 EIR LOAN REVENUE RECOGNITION – MAIIC REQUIREMENTS (ECL MODULE).pdf`, attached to the 20 May 2026 signed confirmation |
| The live Excel model | `D:\OneDrive\Documents\CLIENTS\MALAWI\MAIIIC\Database\ECL Models\MAIIC ECL Model - October 2025.xlsx` |
| The richest loan tape | `...\MAIIIC\Database\Data Cleaning\Ebanker Loan Books.csv` (37 columns) |
| Our independent review | `...\MAIIIC\2025 Audit\MAICC Review Report (29January2026).docx` |
| Deloitte's challenges | Kondwani Msowoya (11 Dec 2024); Tasneem Ahmed (13 Jan 2025) |
| S&P PD source | S&P *Annual Global Corporate Default And Rating Transition Study*, Table 25, Emerging Markets row |
| Method documentation | `...\MAIIIC\IFRS9\2022\` (5 versions of FY2022 Model Documentation) |
