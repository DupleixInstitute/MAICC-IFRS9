p = "maiic_report.py"
s = open(p, encoding="utf-8").read()
def rep(old, new, n=1):
    global s
    assert s.count(old) == n, (old[:60], s.count(old))
    s = s.replace(old, new)
# section 3, row 4: disbursements
rep('''     "The schedule holds one planned entry. Loans drawn in several parts show as one, so the dates money actually went out are lost.",
     "Every populated row has exactly one tranche. Ebenezer Midian shows one, while the ledger shows five."],''',
    '''     "The schedule gives the planned dates. The date money actually went out, which is when interest starts to run, is in the ledger and can be different.",
     "Ebenezer Midian: five entries dated 15 July 2025 in Extract A; the ledger shows the same five amounts paid out on 8 August 2025. Of the 35 accounts with ledger disbursements in 2025, the totals agree on 30 and the number of entries on 28."],''')
rep('''     "The count is the tenor in months plus one. The first repayment date equals the loan start date on 63 of 128 rows. 53 accounts have no chart rows at all."],''',
    '''     "Where a chart exists, the count is the tenor in months plus one on the rows we checked. The first repayment date equals the loan start date on 63 of 128 rows. 53 accounts have no chart rows at all."],''')
# section 4 evidence
rep('"On one account: interest of 21,497,946.96', '"On one account (Ifracon Limited): interest of 21,497,946.96')
# section 8 row
rep('''    ["Why does every loan show one disbursement tranche?", "The script reads the disbursement schedule, not the ledger.", "Explained", "Runs 9 and 11"],''',
    '''    ["Do the disbursement tranches come through?", "Partly. 58 of the 314 populated rows list two to ten entries, so our September statement that every row had one was wrong. But the entries come from the disbursement schedule, with planned dates, not from the ledger.", "Corrected", "Runs 9 and 11"],''')
# tier 2 text
rep('''We chose it because we already know from Extract B "
  "that it was drawn in five tranches totalling MWK 314,900,900, so the result can be checked the moment it arrives.")''',
    '''We chose it because we already know two things about "
  "it: the ledger shows five amounts totalling MWK 314,900,900 paid out on 8 August 2025, and its disbursement schedule "
  "shows the same five amounts dated 15 July 2025. The result can be checked the moment it arrives.")''')
# section 11
rep("Take drawdowns from the ledger.", "Take drawdown dates and amounts from the ledger.")
# appendix A
rep('''    ["Extract A: principal disbursed against sanctioned amount", "Populated on 157 accounts; equal to the sanctioned amount on 138"],''',
    '''    ["Extract A: principal disbursed against sanctioned amount", "Populated on 157 accounts; equal to the sanctioned amount on 138"],
    ["Extract A: entries in the tranche list", "Populated on 314 of 362 rows. One entry on 256 rows; two to ten entries on 58"],
    ["Extract A tranche list against ledger disbursements in Extract B", "35 accounts have ledger disbursements in 2025. Total amount agrees on 30; number of entries agrees on 28. Ebenezer Midian: 15 July 2025 in Extract A, 8 August 2025 in the ledger"],''')
# appendix B exact rows
i = s.index('    ["000104430000068", "Nyamunyamu Processors Limited"'); j = s.index('], [0.17, 0.27, 0.15, 0.13, 0.15, 0.13], keep=True)')
s = s[:i] + '''    ["000104430000068", "Nyamunyamu Processors Limited", "131,899,213.80", "69,330,809.54", "62,568,404.26", "69,330,809.54"],
    ["000104430000002", "Edge View Academy Limited", "117,296,781.00", "10,457,152.33", "106,839,628.67", "10,457,152.33"],
    ["000104430000073", "Saile Financial Services Limited Company", "90,522,529.35", "2,395,117.82", "88,127,411.53", "2,395,117.82"],
    ["000104420000062", "Happie Foods Limited", "38,421,398.75", "9,018,700.44", "36,402,698.31", "2,018,700.44"],
    ["000104420000052", "Kambewu Organic Fertilizer Investments", "42,329,779.82", "913,541.02", "41,416,238.80", "913,541.02"],
    ["000104420000032", "African Honey Products Industries", "29,893,579.18", "51,362.16", "29,842,217.02", "51,362.16"],
''' + s[j:]
rep('''NOTE("The Extract B and Extract C interest columns are rounded from the files to the nearest unit shown; the differences and "
     "the \\"other\\" amounts are exact. On Happie Foods the \\"other\\" rows include a further 7,000,000 that is not an interest adjustment.")''',
    '''NOTE("The six differences add up to MWK 85,166,683.31, which is the whole difference between the two extracts for 2025. On "
     "Happie Foods the \\"other\\" rows include a further 7,000,000 that Extract C does not subtract.")''')
rep('''NOTE("Sanctioned amounts are shown as rounded in our working of the file.")\n''', "")
open(p, "w", encoding="utf-8").write(s)
# run-order file: correct the DD_16c reason
q = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\2. Documents from clients\Raw Query Scripts\Query Requests to MAIIC\RUN ORDER - data dictionary queries by priority.sql"
t = open(q, encoding="utf-8", newline="").read()
old = "-- WHY:  Sample loan: the disbursement schedule table. Extract A showed one tranche for\r\n--       a loan the ledger shows drawn in five."
assert t.count(old) == 1, t.count(old)
t = t.replace(old, "-- WHY:  Sample loan: the disbursement schedule table. Extract A lists five entries\r\n--       dated 15 July 2025; the ledger shows the same amounts paid out on 8 August 2025.")
open(q, "w", encoding="utf-8", newline="").write(t)
print("patched")
