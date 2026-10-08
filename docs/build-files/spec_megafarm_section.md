## 16. The Mega Farm loans, seed loans included: a full treatment

### 16.1 Background: what the Mega Farm programme is

In 2024 the Government of Malawi set aside K20 billion under the Malawi 2063 Agenda to lend to farmers for inputs: fertilizer, seed, pesticides, working capital, equipment and irrigation. It asked MAIIC to run the programme. Under the agreement, MAIIC originates the loans, disburses them (in 2025 largely as farm-input vouchers rather than cash), services them and collects the repayments, which often arrive as maize delivered to ADMARC, NFRA or ACE rather than as money. The loans carry 15 percent a year, of which 5 percent is MAIIC's and 10 percent is credited to the Government's fund. MAIIC also earns a 5 percent management fee on the fund received and a commission. Losses on the loans are charged to the fund, not to MAIIC: in the 2025 financial statements the fund's own movement shows the expected credit losses of K37.4 billion and the maize write-down of K1.2 billion, and the fund closed the year at K16.0 billion.

In E-Banker the programme lives in eight schemes (96 fertilizer, 97 irrigation, 98 seed, 99 CAPEX, 100 working capital, 101 pesticides, 102 and 103 equipment) with about 7,000 accounts, on their own general-ledger series: loans on 1050301 to 1050307, interest receivable on 1059 to 1067, income on 4214 to 4224 and 4260 to 4262, the fund on 2070 to 2072. None of these schemes was in the 18 of the 7 October extracts, and none of the ledger, balance-history or loan-book pulls covers a single Mega Farm account; pack 2 of 8 October fetches them.

### 16.2 What the December 2025 financial statements say

The statements keep the programme apart from MAIIC's own lending on every page. On the statement of financial position the loans, the cash, the maize inventory and the fund deposits each have their own "Mega Farms" line. Note 8 reports them as a separate table:

| Mega Farms, 31 December 2025 (K thousand) | Stage 1 | Stage 2 | Stage 3 | Total |
|---|---|---|---|---|
| Gross loans | 2,799,359 | nil | 48,744,898 | 51,544,257 |
| Expected credit losses | (1,162) | nil | (39,760,244) | (39,761,406) |
| Net | 2,798,197 | nil | 8,984,654 | 11,782,851 |

Ninety-five percent of the book is credit-impaired, with a loss allowance of 82 percent on the impaired part. The seed loans are K7.59 billion of the K51.54 billion gross (QuickBooks 1055; E-Banker 1050302), with their own interest receivable (1062, K0.79 billion after the audit journals) and their own income line (4217, K412 million).

Three audit journals explain how the interest is treated. "Transfer of MAIIC interest to receivable" moved MAIIC's 5 percent share out of each Mega Farm interest-receivable account into other receivables, which note 9a reports as "interest income from Mega farms amounting to K2.8 billion, which carries expected credit losses of K2.1 billion"; for seed that journal was the whole of 4217, K412 million. The Government's 10 percent share is credited to the fund (2071, K6.4 billion). And "fair value adjustment on maize" wrote the ADMARC, NFRA and ACE receivables down to the net realisable value of the maize received in repayment. Note 19 charges "impairments on MAIIC Mega farm interest income" of K2.1 billion to MAIIC's own results: that is the only Mega Farm loss MAIIC bears.

The trial balances show the same shape from the other side: the seed balance did not move at all from January to June 2025 and was repaid only in the harvest months; interest accrues to the receivable and never to the loan; the implied rate on seed income is 4.6 percent, which is MAIIC's 5 percent share and not the 15 percent the borrower pays; and K350 million left the seed receivable in June 2026 without passing through income, which is the same transfer-to-receivable pattern applied in-year.

### 16.3 What kind of asset this is, explained

The question a reviewer asks first is not "what is the EIR" but "whose loan is it". IFRS 9 recognises a financial asset when the entity becomes party to the contract, and derecognises it, or keeps it, according to who holds the risks and rewards and who controls it (3.2). IFRS 15 and IFRS 10 ask the related question of whether MAIIC acts as principal or as agent in the arrangement; the statements themselves note the IFRS 10 "de facto agent" assessment.

Read against those tests, the Mega Farm loans have three features that set them apart from the MAIIC book:

- **The credit risk is the fund's.** The expected credit losses reduce the Government's fund liability, not MAIIC's equity. MAIIC bears loss only on its own 5 percent interest share, which is why that share is moved to other receivables and impaired there.
- **The return is a contractual split, not a yield.** The borrower pays 15 percent; MAIIC's part is 5 percent, fixed by the agreement, plus a management fee on the fund and a commission on the programme. There are no integral fees charged to the borrower that an EIR would spread, and the rate is a programme rate that does not move with the prime lending rate.
- **MAIIC services and controls the loans.** It originates, disburses, collects and stages them, and the statements present them gross on MAIIC's balance sheet with the fund as a matching liability.

The statements resolve this by presenting the loans on the balance sheet but ring-fenced: gross loans less a loss allowance, both belonging in substance to the programme, with MAIIC's own exposure limited to its interest share. The engine must respect that presentation, because the auditors signed it, and must not fold a programme book of K51.5 billion into an effective-interest calculation designed for MAIIC's own K14.7 billion of lending.

### 16.4 The treatment, decision D30

| Question | Treatment | Why |
|---|---|---|
| Are the Mega Farm loans in the EIR engine? | **No.** Out of the EIR engine's scope, disclosed as such in the engine's reports and in the audit workbooks | The rate is a contractual split on a programme fund, not a yield with integral fees; 95 percent of the book is credit-impaired; the loss belongs to the fund. An EIR on these loans would restate nothing that the statements report |
| Is MAIIC's 5 percent share computed? | **Yes**, by the engine, as interest on the net carrying amount of each loan (5.4.1(b)) at MAIIC's share of the rate, posted to MAIIC's receivable; the fund's 10 percent share computed alongside and credited to the fund | That share is MAIIC's own income, it was the subject of a K2.1 billion impairment, and the auditors look at it; computing it on the net amount is what IFRS 9 requires for a credit-impaired asset |
| Are the loans in the ECL module? | **Yes**, fully: staging, PD, LGD, ECL per scheme, with the loss charged to the fund liability and MAIIC's interest-share impairment charged to MAIIC | The staging and the K39.76 billion allowance are MAIIC's calculation and were audited; the module exists and holds the other book already |
| Repayments in kind | Maize received is recorded as a receivable from the off-taker (ADMARC, NFRA, ACE) at the value credited to the loan, and written down to net realisable value with the write-down charged to the fund | Note 9c; the engine records the loan as repaid at the credited value and the inventory risk as the fund's |
| Where the fund sits | A liability, 2070 to 2072, rolled forward each month: funds received, interest credited (10 percent share), loans created, repayments, write-downs, expected credit losses | Note 11(c) is the roll-forward the auditors want; the system produces it instead of Finance's spreadsheet |
| Contract profile | Its own scheme settings read from E-Banker (DD_10): the 15 percent rate and its split, interest to the receivable not the balance, seasonal bullet repayment, the off-taker as payer | The MAIIC book's conventions (sanction-versus-balance basis, moratorium shapes, capitalised interest) do not describe these loans |

### 16.5 How the seed loans flow through the system, step by step

1. **Landing.** The pack 2 extracts for scheme 98 land in the same zone as everything else: masters, ledger, balance history, loan-book runs, status history, rate set-up, charges. The gates are the same.
2. **Build.** The loan book for the seed accounts is built by the method in force, with two scheme-specific rules: the carrying amount is the loan balance plus the loan's share of the interest receivable, and the fund share of the receivable is a separate column.
3. **Staging and ECL.** Each seed account is staged and provisioned by the ECL module like any other, under the Mega Farm scheme's parameters (its own PD curve from its own history, its LGD reflecting repayment in maize). The ECL is posted against the fund.
4. **Interest.** The engine computes 15 percent on the net carrying amount for the month, splits it 5 to MAIIC's receivable and 10 to the fund, and compares the MAIIC share with what E-Banker posted to the receivable. The EIR engine proper (schedules, solver, revenue recognition) is not run.
5. **Impairment of MAIIC's share.** The MAIIC receivable is itself provisioned, in MAIIC's own books, as note 9a does.
6. **Reporting.** The Mega Farms table of note 8, the fund roll-forward of note 11(c), the receivable and its allowance of note 9a, and the maize inventory of note 9c come out of the Report Hub as a Mega Farms report, so the figures the auditors sign are produced rather than typed.
7. **Audit.** The IFRS 9 impairment workbook carries the Mega Farm rows (staging, ECL, net-basis interest); the Contract Schedule 1 workbook records the scope decision.

### 16.6 Two reconciliations that will come out of the pack 2 data

The seed interest receivable rose by K700 million in 2025 while seed interest income was K379 million on the trial balance; the K320 million difference is the fund's 10 percent share being credited to the receivable and then to the fund, and the ledger will show it posting by posting. And the K350 million that left the receivable in June 2026 should be the in-year transfer of MAIIC's share to other receivables; if it is a write-off instead, that is a different conversation with Finance. Both are tests the engine runs once the extracts arrive, and both go on the Baselines sheet.

### 16.7 What this does to the headline number

Dr Thom asked for the revenue shift per year from moving to a real EIR. That number is about MAIIC's own lending: K1.4 billion of loan interest in 2024, K5.6 billion in 2025. The Mega Farm interest, 49 percent of 2025 loan interest on the trial balance, is a programme return of which two-thirds belongs to the fund and the rest is largely accrued and impaired; it does not move with the EIR and is left where the statements put it. Taking the programme out of the EIR scope makes the revenue-shift number smaller, cleaner and defensible, which is what Dr Thom needs it to be.
