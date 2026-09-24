# Module 00 — The Client: Who MAIIC Actually Is

> **Why this module comes first.** You cannot build a good credit model for an institution you don't understand. Every "weird" decision in the MAIIC model — the 10% loans, the 13-month moratoria, the fact that the whole book is about 96 accounts — traces back to what kind of animal MAIIC is. If you skip this module, you will write technically correct code that produces commercially wrong numbers.

---

## 1. What is a DFI, in plain language?

Imagine two kinds of lender in a town.

**The first is a commercial bank.** Its job is to make money. If a farmer walks in wanting a loan for an irrigation scheme that pays back over seven years in a drought-prone district, the bank says no. Not because the bank is cruel — because its shareholders want returns and its depositors want their money safe. The bank lends where risk is lowest and returns are quickest.

**The second is a Development Finance Institution (DFI).** Its job is to make *the town* work. It exists precisely to lend to that farmer — because if nobody funds irrigation, the district stays poor forever. A DFI is capitalised (usually by government and development partners) with a mandate that reads something like *"promote agricultural and industrial development"*. Making money is a constraint, not the objective. It must not lose money forever — but it is not trying to beat the market.

**MAIIC is the second kind.** Malawi Agricultural and Industrial Investment Corporation plc, Gowa House, African Unity Avenue, City Centre, Lilongwe.

Now hold that thought, because it explains everything that follows.

### The consequence that matters for your code

> **A DFI deliberately holds the loans a bank would refuse.**

That is not a bug in their credit process. It is the mandate being executed. So when you see a book where a large share of exposure sits in Stage 2 or Stage 3, your instinct should *not* be "this data is wrong". It might be exactly right. A DFI's book is *supposed* to look riskier than a commercial bank's.

This has a direct modelling consequence. Every off-the-shelf IFRS 9 assumption — 30 days past due means trouble, defaults are rare, portfolios are large and statistically well-behaved — is calibrated on **commercial bank retail books**. Almost none of those assumptions survive contact with MAIIC. Module 04 works through this properly for SICR.

---

## 2. MAIIC by the numbers

| Fact | Value | Why you care |
|---|---|---|
| Book size | **~96 accounts per month** in the legacy tape; ~4,657 rows across all months in the Ebanker export | This is **not** a statistical portfolio. One borrower moves the whole book. |
| Currency | Malawi Kwacha (**MWK / MK**) | Amounts run to 10–12 digits. `decimal(65,4)` in the schema is not paranoia. |
| Core lending rate | **>32%** | Nominal rates are huge because inflation is huge. |
| FinES portfolio rate | **10%** (concessional, World Bank–funded) | A *below-market* rate. Flagged in the data by the 10% rate itself. |
| Typical tenor | 5+ years, with **moratoria up to 13 months** | Long, patient, lumpy money. |
| Auditor | **Deloitte** (Kenya + Malawi member firms) | They reperform. See Module 07. |
| Regulator | **Reserve Bank of Malawi (RBM)** | Financial Asset Classification Directive 2014; Credit Risk Management for DFI Directive 2018. |

### The portfolios

MAIIC is not one book. As at Oct 2025 there were five distinct products, and they behave differently:

1. **MAIIC core** — the main commercial-ish lending book, rates >32%.
2. **FinES** — World Bank–funded, **10% concessional**. Identified in the data *by its 10% interest rate* (we added a `funding_source` column to separate it).
3. **Mega Farm** — retail agricultural loans, **new in Oct 2025**. Our review flagged this as a *significant* risk: corporate PD/LGD is being applied to a retail agri portfolio, which under-provisions.
4. **Money Market** — treasury instruments.
5. **Preference Shares** — e.g. the WI Jays exposure. This is why **IAS 32** appears in the purchase order (see Module 08): preference shares raise a debt-vs-equity classification question before you even get to IFRS 9.

> **Teaching point.** A "loan book" that contains preference shares and treasury bills is not a loan book. Different instruments, different measurement, different EIR treatment. Your module must not assume every row is an amortising term loan. It isn't.

---

## 3. The Malawi context (this is not colour — it's model input)

**Inflation is severe and rates are extreme.** MAIIC lends at 32%+ because a 10% rate in Malawi would be lending at a real loss. When you see a 30% contractual rate in the EIR example, that is not a typo.

**There is a forex crunch.** Our ZAR87,500 invoice from 9 December 2025 was still unpaid on 2 April 2026 — sitting *"in a queue for forex"*, with MAIIC's Finance function moving to "Plan B". This is a real constraint on the client, and it explains why price matters so much in the bid.

**And here is where it gets interesting for the model.** Our own FY2025 review found:

> *"the correlation in the 2025 update has fallen to 40% and hence unreliable. The inflation rate has risen significantly but the NPLs have been inelastic. This is a phenomenon experienced in hyper-inflation economies where the default rates reduce even when the inflation rates increase BUT without an interest rate adjustment."*

Read that again, because it is genuinely counter-intuitive: **in a hyperinflationary economy, inflation can make defaults go *down*.** Why? Because the borrower's debt is fixed in nominal Kwacha while their revenues inflate. If you borrowed MK1bn and your prices double, your debt just halved in real terms. Inflation is *good* for existing borrowers — as long as the lender doesn't reprice.

The practical consequence: **MAIIC's FY2025 ECL has no forward-looking overlay at all.** The FLI model was built, updated, and then *not applied*, because the regression failed its own R² ≥ 0.60 gate. So FY2025 numbers are through-the-cycle S&P PDs with no point-in-time adjustment. That is the single largest methodological exposure in the file, and Deloitte may well raise it.

---

## 4. The people

| Who | Role | What they want from you |
|---|---|---|
| **Dr Thomson Kumwenda** | Chief Finance Officer | The constant counterparty for five years. Named lead on the PO. Technically engaged — he argues methodology. |
| **Lloyd Banda** | Acting Chief Executive Officer | Attended the demo. |
| **Margaret Chirombe** | Head of HR & Administration | **Owns the PO, the invoice, and the bid process.** She sent the EIR requirements document. |
| **Tamanda Sitimawina** | Accountant | |
| **Taziona Chaponda** | Managing Director | Signed the 2021/2022 reference letters. |

**Deloitte:** Kondwani Msowoya (Malawi), Clinton Wafula FIA and Tasneem Ahmed (Kenya, actuarial).

**Dupleix:** Themba Mazibuko (President & CEO — signed the EIR confirmation), Edward (Director, Data Analytics & Software Development), Farisai Maburutse (PM), Wadzanai Rombe (Senior Consultant).

---

## 5. The five-year story (why they don't just trust us)

This relationship has history, and the history is mostly *auditors challenging our model*. You need to know this, because it sets the standard your code must meet.

| When | What happened |
|---|---|
| **2021** | MAIIC **buys an Excel ECL model from Dupleix**. |
| **Dec 2022 cycle** | Auditors review it and **rewrite our LGD and ECL formulae** "to comply with the strict IFRS 9 definitions". They find our model applied cashflow reductions to **Stage 3 only** — they extend it to Stages 1–3. They also find *"no explicit loan default formula, the percentage that was on the template was hardcoded"*. Ouch. |
| **Jun–Oct 2023** | We're engaged to review. MAIIC's CFO had swapped the FLI credit index for the 91-day TB rate to make the regression pass. **We reversed him** — the TB rate fails the sign test ("We can't have a positive relationship between TB rates and NPLs"). Gross ECL MK472m (CFO version) vs MK450m. |
| **11 Dec 2024** | **Deloitte round 1.** PIT vs overall PDs; haircut direction; LGD error handling; EAD basis. |
| **13 Jan 2025** | **Deloitte round 2.** Years-to-maturity inconsistent; **lifetime PDs applied to Stage 2 while EAD is not amortised on a lifetime basis**; preference-share cashflows capped at 1 year. |
| **Dec 2024** | MAIIC asks Deloitte for their PIT PD model and logit parameters. **Deloitte refuses** — *"Sharing them would effectively result in us reviewing our own numbers, which would essentially amount to a self-review."* |
| **3 Nov 2025** | PO issued: *"Review of the ECL Model for MAIIC plc"*. Fee ZAR87,500. |
| **Dec 2025** | We receive the loan books and hit a **data-integrity crisis** — customer IDs changed month to month, sector mappings inconsistent. We rebuild on Ebanker account numbers. 22 loan books loaded (Jan 2024 – Oct 2025). |
| **29 Jan 2026** | Our independent review report issued. |
| **18 May 2026** | System demo to MAIIC. |
| **19–20 May 2026** | **MAIIC issues the EIR requirements; Themba signs the confirmation.** |
| **Jun 2026** | Bid evaluation; Board decision due end-June. |

### What this history should teach you

**Every single time, the auditor found the model did something the documentation said it didn't.** The 2022 model *said* it had a default formula; it was hardcoded. The current model *says* Stage 3 interest is recognised on amortised cost; it isn't (Module 03 proves it).

> **The pattern is: documentation describes the intent; the spreadsheet does something else; the auditor finds the gap.**

Your module is the chance to break that pattern. Which means: **if the code can't do it, don't let the doc claim it.**

---

## 6. Why EIR, why now?

Because MAIIC asked, in writing, and we said yes, in writing.

But there's a deeper reason, and it's worth understanding. Look at MAIIC's own worked example: a **MK1bn loan at 30% with MK46.16m of fees**. Under the contractual method they recognise MK169.8m of interest and book MK46.16m of fees on day one. Under EIR they recognise **MK216.0m of interest** and no day-one fee income.

Same cash. Same borrower. **Same total profit.** Different *timing* — and therefore a different profit number in every single reporting period until the loan matures.

For a DFI with a small book of large loans, the fee on one MK1bn facility can swing a month's reported profit. That's why the CFO cares. And on **Stage 3**, where interest must be earned on the *net* number, EIR directly determines how much revenue MAIIC is allowed to recognise on loans that may never be repaid. That's why the *auditor* cares.

---

## Check yourself

Before moving on, you should be able to answer:

1. Why would a DFI have a riskier-looking book than a commercial bank, *without* that indicating a problem?
2. Why does the FinES portfolio lend at 10% when MAIIC core lends at 32%+ — and how do we identify FinES rows in the data?
3. Why might rising inflation *reduce* defaults in Malawi?
4. What did Deloitte refuse to share, and on what grounds?
5. MAIIC's book is ~96 accounts a month. Name two IFRS 9 techniques that assumption breaks.

→ Next: [Module 01 — IFRS 9 foundations and where EIR lives](01-ifrs9-and-where-eir-lives.md)
