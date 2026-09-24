# What Dr Thom's trial balance mapping tells us

Received 24 September 2026, `Mapping TBs August 2026 to Audited Financial Statements4.xlsx`, filed in
`2. Documents from clients\New Doc Received 21 Sep\`. Column F carries the classification and column K the tie to the
audited schedules, as Dr Thom's note said.

**How the tie works, and what it does not claim.** Column K flags every row as included in the tie; on the balance
sheet all 142 populated rows read "Tied to AFS". The tie is asserted **on the category totals in column F**, not
line by line. An individual general ledger account is the make-up behind an audited figure, not an audited figure
itself, which is exactly what Dr Thom demonstrated by opening a line and showing the accounts underneath it. Every
account balance quoted below should be read that way.

**This closes the item we left open at the meeting.** We could not previously marry the arrangement and legal fees on
the ledger to the audited accounts. The workbook now shows both, by year, as named sub-accounts of 4870 Services
Income, and every line carries its AFS classification. All amounts are kwacha.

## 1. It agrees with what was said in the room

| Figure | Workbook | Said at the meeting |
|---|---:|---|
| Total income on the EIR line, 2024 | 9,458,671,532 | "2024, 9.45 billion" |
| Interest income on loans and advances, 2024 | 1,438,630,092 | "the loan income, in 2024, was 1.4 billion" |
| Money market and investments, 2024 | 8,020,041,440 | "almost 90 percent would be revenue from money market" (85 percent) |

That agreement matters: it means the mapping in this workbook is the same basis Dr Thom was speaking from, so we can
build the reconciliation on it without a further translation.

## 2. The fees, at last, by year

| Account | 2023 | 2024 | 2025 | Jan to Aug 2026 |
|---|---:|---:|---:|---:|
| 4873 Arrangement fees | 78,983,020 | 1,337,574,206 | 52,025,792 | 414,947,879 |
| 4871 Legal fees | 84,163,508 | 270,114,433 | 171,128,135 | 243,389,041 |
| 4874 Insurance fees | 1,084,837 | 0 | 2,463,131 | 63,828,000 |
| 4875 PCG guarantee fees | 2,474,362 | 345,000 | 1,284,900 | 3,275,440 |
| **Arrangement and legal together** | 163,146,528 | 1,607,688,639 | 223,153,927 | 658,336,920 |
| 6752 Legal costs recharged to clients | -27,831,303 | -168,702,040 | -125,774,122 | -64,170,683 |

Two things follow.

**The paid leg exists and must come through as well.** Account 6752, legal costs recharged to clients, is classified
in the workbook as revenue rather than as an expense, because it nets against the legal fee income. For the effective
interest rate both legs count: what the borrower was charged reduces the amount advanced, and what MAIIC paid out on
the borrower's behalf is a transaction cost. The fee template carries a direction column for exactly this, so the
legal cost rows should come through marked PAID rather than being netted off before they reach us.

**The fee accounts here are before the reclassification, which is what we want.** At the meeting Dr Thom explained
that once the EIR assessment is done a manual journal moves the EIR-related part of fee income into Interest on term
loans, normally post year end at audit time. So a ledger figure for fees will read higher than the fee line in the
published accounts, and the two reconcile only when interest income and loan-related income are taken together. The
figures above are the ledger position, gross of that journal. That is the right starting point for an effective
interest rate, because what we need is the fee as it was charged to the borrower. The published fee line is after the
journal, and using it would strip out the part that has already been moved.

**The 2024 arrangement fee is still worth a question.** In the ledger it is 1,337,574,206 for 2024 against 52,025,792
for 2025, and in the same year it is close to double the 1,127,466,814 of interest earned on the MAIIC, FinES and
staff term loans. Arrangement fees of one to four percent of an approved amount do not usually produce that shape.
The likeliest explanation is the Mega Farms facilities that were written that year, but we should hear it rather than
assume it, because the answer decides how much of 2024 belongs in the effective interest rate and how much belongs
somewhere else.

## 3. What the engine actually recalculates

Column F labels every interest line, money market included, as EIR Interest Income. That is the category the audited
accounts present, not the engine's scope. Money market income is already earned at its own effective rate, as Dr Thom
said, so nothing there is recalculated. The engine's scope is the loan book:

| | 2023 | 2024 | 2025 | Jan to Aug 2026 |
|---|---:|---:|---:|---:|
| MAIIC, FinES and staff term loans | 1,000,690,082 | 1,127,466,814 | 2,860,969,477 | 3,244,428,187 |
| Mega Farms facilities | 0 | 311,163,278 | 2,787,930,845 | 1,550,795,814 |
| **Total the engine recalculates** | 1,000,690,082 | 1,438,630,092 | 5,648,900,322 | 4,795,224,000 |
| Money market, treasury and coupon (out of scope) | 2,188,431,409 | 8,020,041,440 | 5,746,819,763 | 2,938,174,763 |
| **Total on the EIR line in the accounts** | 3,217,659,572 | 9,458,671,532 | 11,395,720,085 | 7,733,398,763 |

So the figure at risk in any EIR adjustment is 1,438,630,092 for 2024, not the 9,458,671,532 on the face of
the accounts. That is worth saying to the auditors early, because it bounds the exercise.

## 4. Mega Farms is the question nobody has asked yet

Mega Farms facilities produced 2,787,930,845 of the 5,648,900,322 of loan interest in 2025, which is
49 percent of everything the engine would recalculate. They sit on their own general
ledger series, 1054 to 1067 on the balance sheet and 4214 to 4219 in income, separate from term loans at 1050.

The balance sheet also shows a very large expected credit loss carried against them, 1066 ECL Megafarm Loans, set
against the facilities themselves. A loan that is credit impaired accrues interest on the amount net of its loss
allowance under IFRS 9 5.4.1(b), not on the gross amount. Before we calculate anything on this population we need to
know whether Mega Farms is inside the scope of this engagement, and if it is, what stage those facilities sit in and
on what basis their interest has been recognised.

## 5. Questions this raises, for the written follow-up

1. What sits in 4873 in 2024 that makes arrangement fees almost double the term loan interest for that year?
2. Are Mega Farms facilities inside the scope of the EIR engine, and what stage are they in?
3. Should insurance fees (4874) and the PCG guarantee fee (4875) be treated as part of the loan's yield, or as
   separate service income? They are small today but 4874 is growing.
4. Legal costs recharged to clients (6752): does the borrower see the gross legal fee deducted from the proceeds, with
   MAIIC paying the lawyer separately, or only the net?
5. Confirmation that the manual reclassification journal from fee income into Interest on term loans is visible in the
   general ledger for 2024 and 2025, so that we can reproduce it and then replace it. We need the amount moved in each
   year, because that is the figure our engine's output has to be compared against.

---

*Prepared by Dupleix Institute, 24 September 2026, from the workbook as received. Figures are read from columns G to J
and are not adjusted.*
