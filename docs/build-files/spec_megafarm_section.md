## 16. The Mega Farm loans, seed loans included: a full treatment

### 16.1 Background: what the Mega Farm programme is

In 2024 the Government of Malawi set aside K20 billion to lend to farmers for the things a season needs: fertilizer, seed, pesticides, working capital, equipment and irrigation. It asked MAIIC to run the programme. MAIIC finds the farmers, approves the loans, pays them out (in 2025 mostly as vouchers for farm inputs rather than cash), keeps the accounts and collects the repayments. Many farmers repay not in money but in maize, delivered to one of three buyers (ADMARC, NFRA or ACE), who then owe MAIIC the value of the maize.

The loans carry interest at 15 percent a year. By agreement, 5 percent of that belongs to MAIIC and 10 percent belongs to the Government's fund. MAIIC also earns a management fee of 5 percent on the money the fund receives, and a commission. When a farmer does not repay, the loss is charged to the Government's fund, not to MAIIC. The 2025 financial statements show the fund absorbing K37.4 billion of expected losses and a K1.2 billion write-down on maize, and closing the year at K16.0 billion.

In E-Banker the programme lives in eight schemes (96 fertilizer, 97 irrigation, 98 seed, 99 CAPEX, 100 working capital, 101 pesticides, 102 and 103 equipment) with about 7,000 accounts. They have their own general-ledger codes: the loans on 1050301 to 1050307, the interest owed by farmers on 1059 to 1067, the income on 4214 to 4224 and 4260 to 4262, the fund on 2070 to 2072. None of these schemes was in the 18 that the 7 October extracts covered, so we hold no postings, balances or loan-book runs for any Mega Farm account yet; pack 2 of 8 October fetches them.

### 16.2 What the December 2025 financial statements say

The statements keep the programme separate from MAIIC's own lending on every page. On the balance sheet the loans, the cash, the maize and the fund each have their own "Mega Farms" line. Note 8 gives them their own table, which uses the three "stages" of IFRS 9. In plain words: Stage 1 is a loan that is performing, Stage 2 is a loan whose risk has gone up significantly since it was made, and Stage 3 is a loan that is in default. The "expected credit loss" is the provision, the amount set aside because some of the loan will not come back.

| Mega Farms, 31 December 2025 (K thousand) | Stage 1 | Stage 2 | Stage 3 | Total |
|---|---|---|---|---|
| Gross loans | 2,799,359 | nil | 48,744,898 | 51,544,257 |
| Expected credit losses | (1,162) | nil | (39,760,244) | (39,761,406) |
| Net | 2,798,197 | nil | 8,984,654 | 11,782,851 |

So 95 percent of the programme is in default, and 82 percent of the defaulted amount has been provided for. The seed loans are K7.59 billion of the K51.54 billion (code 1055 in the accounting system, 1050302 in E-Banker), with their own interest-owed account (1062, K0.79 billion after the audit adjustments) and their own income line (4217, K412 million).

Three adjustments made during the audit show how the interest is handled:

- **"Transfer of MAIIC interest to receivable."** MAIIC's 5 percent share of the interest was moved out of the Mega Farm interest accounts into MAIIC's own "other receivables". Note 9a describes it: "interest income from Mega farms amounting to K2.8 billion, which carries expected credit losses of K2.1 billion". For seed, that transfer was the whole of the K412 million.
- **The fund's 10 percent share** is credited to the fund (code 2071, K6.4 billion).
- **"Fair value adjustment on maize."** The amounts owed by the three maize buyers were written down to what the maize is actually worth.

Note 19 then charges "impairments on MAIIC Mega farm interest income" of K2.1 billion to MAIIC's own profit. That is the only Mega Farm loss MAIIC itself bears; the rest is the fund's.

The monthly trial balances tell the same story from the other side. The seed loan balance did not change at all from January to June 2025 and was repaid only in the harvest months. Interest is added to a separate "owed" account, never to the loan itself. The income on seed works out at 4.6 percent of the balance, which is MAIIC's 5 percent share, not the 15 percent the farmer pays. And K350 million left the seed interest-owed account in June 2026 without passing through income, which is the same "transfer to receivable" being done during the year.

### 16.3 Whose loan is it, explained

Before asking what interest rate a loan earns, an accountant asks whose loan it is. The accounting rules put the question in two ways. First, does the lender carry the risks and rewards of the loan: does it lose if the borrower fails, and gain if the borrower pays? Second, is the lender acting for itself (a "principal") or on behalf of someone else (an "agent")? The statements say Deloitte looked at exactly this question for the programme (the note on IFRS 10 and "de facto agents", page 34).

For the Mega Farm loans the answers point the same way:

- **The risk is the Government's.** When a farmer fails to pay, the loss comes off the Government's fund. MAIIC loses only its own 5 percent interest share, which is why that share is moved to MAIIC's own receivables and provided for there.
- **MAIIC's return is a fixed slice, not a yield.** The farmer pays 15 percent. MAIIC's part is 5 percent, set by the agreement, plus the fee and the commission. There are no arrangement or legal fees charged to the farmer that an effective interest rate would have to spread, and the rate does not move with the prime rate.
- **MAIIC does the work and keeps the accounts.** It approves, pays out, collects and stages the loans, and the statements show the loans on MAIIC's balance sheet with the fund as a matching liability on the other side.

The statements settle it by showing the loans on MAIIC's balance sheet but kept apart, with the fund as the other side and MAIIC's own exposure limited to its interest share. The engine has to follow that, because the auditors signed it, and it must not pour a K51.5 billion programme book into a calculation built for MAIIC's own K14.7 billion of lending.

### 16.4 The treatment, decision D30

| Question | Treatment | Why, in plain words |
|---|---|---|
| Are the Mega Farm loans in the EIR engine? | **No.** They stay outside, and every engine report and audit workbook says so | The effective interest rate exists to spread a loan's fees and discounts over its life. These loans have no such fees, their rate is fixed by agreement and split by contract, and MAIIC's return is 5 percent whatever happens. Recalculating would change nothing the statements report |
| Is MAIIC's 5 percent share computed? | **Yes.** The engine works out 15 percent on what is actually expected to come back from each loan (the balance after provisions), splits it 5 to MAIIC's receivable and 10 to the fund | That share is MAIIC's own income; it was written down by K2.1 billion; the auditors look at it. The rules say interest on a defaulted loan is counted on the amount expected to be recovered, not on the full balance |
| Are the loans in the ECL module? | **Yes**, completely: staging, the chance of default, the loss if default happens, and the provision for every scheme; the provision charged to the fund, and the write-down of MAIIC's interest share charged to MAIIC | The staging and the K39.76 billion provision are MAIIC's own calculation and were audited; the module exists and already does this for the other book |
| Repayment in maize | The loan is treated as repaid by the value credited; what the maize buyer owes is recorded as a receivable and written down to what the maize is worth, the write-down charged to the fund | This is what note 9c does; the engine records it rather than Finance typing it |
| The fund | Shown as a liability (codes 2070 to 2072) and rolled forward every month: money received, the fund's 10 percent interest, new loans, repayments, write-downs, provisions | Note 11(c) is that roll-forward; the system produces it instead of a spreadsheet |
| How the loans are described in the system | Their own scheme settings, read from E-Banker: the 15 percent and its split, interest kept in a separate account, repayment once a year after harvest, a maize buyer as the payer | The MAIIC book's rules (interest added to the balance, moratoria, instalments) do not describe these loans |

### 16.5 How a seed loan flows through the system, step by step

1. **Landing.** The pack 2 extracts for scheme 98 arrive through the same door as everything else: the account and loan masters, every posting, the month-end balances, the loan-book runs, the status history, the rates, the charges. The same checks apply.
2. **Build.** The seed accounts are built into the loan book by whichever method is in force, with two rules of their own: the amount owed is the loan balance plus MAIIC's share of the interest owed, and the fund's share of the interest owed is kept in its own column.
3. **Staging and provision.** Each seed account is staged and provided for by the ECL module like any other loan, using the Mega Farm scheme's own settings (its own default history, its own recovery assumptions, which allow for repayment in maize). The provision is charged to the fund.
4. **Interest.** The engine works out 15 percent for the month on the amount expected to be recovered, puts 5 to MAIIC's receivable and 10 to the fund, and compares MAIIC's share with what E-Banker actually posted. The EIR calculation proper (schedules, the solved rate, the revenue shift) is not run on these loans.
5. **MAIIC's own write-down.** MAIIC's receivable for its 5 percent is itself provided for in MAIIC's own books, as note 9a shows.
6. **Reporting.** The Mega Farms table of note 8, the fund roll-forward of note 11(c), the receivable and its provision of note 9a, and the maize of note 9c come out of the Report Hub as one Mega Farms report, so the figures the auditors sign are produced by the system rather than typed.
7. **Audit.** The IFRS 9 impairment workbook carries the Mega Farm rows; the Contract Schedule 1 workbook records that the programme is outside the EIR engine and why.

### 16.6 Two checks the pack 2 data will settle

The seed interest-owed account rose by K700 million in 2025 while seed income on the trial balance was K379 million. The K320 million gap should be the fund's 10 percent share, credited to the account and then passed to the fund; the postings will show it line by line. And the K350 million that left the account in June 2026 should be MAIIC's share being transferred to its own receivables during the year; if it turns out to be a write-off, that is a different conversation with Finance. Both become checks the system runs once the extracts arrive, and both go on the Baselines sheet.

### 16.7 What this does to the headline number

Dr Thom asked for one number: how much revenue moves between years when MAIIC's interest is recalculated at the effective rate. That number is about MAIIC's own lending: K1.4 billion of loan interest in 2024 and K5.6 billion in 2025. The Mega Farm interest, half of all loan interest in 2025 on the trial balance, is a programme return of which two-thirds belongs to the Government and the rest is largely owed but not collected; it does not change with the effective rate and stays where the statements put it. Leaving the programme out of the EIR makes the number smaller, cleaner and easier to defend, which is what it needs to be.
