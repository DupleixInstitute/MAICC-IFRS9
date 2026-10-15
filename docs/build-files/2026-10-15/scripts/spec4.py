import json, sys
R = 'Received 12 October 2026/Pack 4/Export Results/'
def f(file, q, kind): return {'file': file, 'query_id': q, 'kind': kind, 'received_as': R + file}
files = [f('MF_01_account_master.csv', 'MF_01', 'pack 4 RUN 1: Mega Farm account master (ACMASTER), schemes 96 to 103')]
files.append(f('P2_13_los_requests_text.csv', 'P2_13', 'pack 4 RUN 2: re-run of P2_13, the 200 LOS requests fetched by id with the CLOB payloads rendered as text (no NEW_AC_NUMBER column)'))
for p in ['a', 'b', 'c', 'd', 'e', 'f', 'g1', 'g2', 'g3', 'g4']:
    files.append(f(f'MF_03_ledger_{p}.csv', 'MF_03', f'pack 4 RUN 3{p}: Mega Farm ledger (CUMVOUCH), part {p} by account range; count in CHECK_MF_03_row_counts.csv'))
for p in ['h1a', 'h1b', 'h2', 'h3', 'h4', 'h5', 'h6']:
    files.append(f(f'MF_05_loan_book_month_ends_{p}.csv', 'MF_05', f'pack 4 RUN 3{p}: Mega Farm Loan Book Report, latest run per account and month-end, part {p}; count in CHECK_MF_05_row_counts.csv'))
files += [
    f('CH_02a_los_disb_charges_140_145.csv', 'CH_02a', 'pack 4 RUN 4a: LOS charges for term-loan schemes 140 to 145'),
    f('CH_02b_disb_batch_legs_140_145.csv', 'CH_02b', 'pack 4 RUN 4b: every leg of the drawdown batches, schemes 140 to 145'),
    f('P2_07_disbursement_charges.csv', 'P2_07', 'pack 4 RUN 5: the full LOS_DISB_CHARGES_POST table (all schemes, 10,064 lines); a superset of the 7 Oct P2_07 and of MF_08'),
    f('GL_04a_fee_gl_legs.csv', 'GL_04a', 'pack 4 RUN 6a: fee income GL legs 4871, 4873, 4874, 4875'),
    f('GL_04b_fee_batch_legs.csv', 'GL_04b', 'pack 4 RUN 6b: every leg of the fee batches'),
    f('GL_05_writeoff_batch_legs.csv', 'GL_05', 'pack 4 RUN 7: write-off batch legs, GL 1064 and 1876'),
]
for i in range(1, 6):
    files.append(f(f'RS_02a_superseded_instalments_{i}.csv', 'RS_02a', f'pack 4 RUN 8a{i}: superseded instalment rows (FIXLOAN_INST_CHART, deleted), account block {i}; count in CHECK_RS_02_row_counts.csv'))
files += [
    f('RS_02b_chart_300824.csv', 'RS_02b', 'pack 4 RUN 8b: instalment chart copy of 30 Aug 2024'),
    f('RS_02c_chart_141124.csv', 'RS_02c', 'pack 4 RUN 8c: instalment chart copy of 14 Nov 2024'),
    f('RS_02d_chart_28sep.csv', 'RS_02d', 'pack 4 RUN 8d: instalment chart copy of 28 Sep'),
    f('RS_02e_chart1.csv', 'RS_02e', 'pack 4 RUN 8e: instalment chart copy CHART1'),
    f('RS_02f_modification_log.csv', 'RS_02f', 'pack 4 RUN 8f: modification log'),
    f('RS_02g_account_closer_history.csv', 'RS_02g', 'pack 4 RUN 8g: account closure history'),
    f('TB_02_trial_balance_history_probe.csv', 'TB_02', 'pack 4 RUN 9a: TRIAL_BALANCE_HISTORY row counts by month (probe)'),
    f('CV_01_cumovuch_old_probe.csv', 'CV_01', 'pack 4 RUN 9b: CUMOVUCH_OLD postings by year (probe)'),
    f('TB_03_trial_balance_rows.csv', 'TB_03', 'pack 4 RUN 9c: E-Banker trial balance rows, Dec 2025 to 2 Jan 2026 and 1 Aug to 2 Oct 2026'),
]
header = {
    'pack': 'MAIIC E-Banker pack 4, received 12 October 2026',
    'assembled': '2026-10-12',
    'period': '2026-09',
    'source': 'E-Banker (Virtual Galaxy CBS, schema VGCBS), run by Barry Makumba in SQL Developer from FOLLOW-UP extracts for Barry - pack 4 - 11 Oct 2026 (all runs).sql, one file per query; RUN 0 was not applied, so dates are exported as m/d/yyyy; amounts signed, debits negative',
    'date_format': 'm/d/Y',
    'scope_scheme_ids': [84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 140, 141, 142, 143, 144, 145, 96, 97, 98, 99, 100, 101, 102, 103],
    'accepted_exceptions': [
        'RUN 0 not applied: every date is m/d/yyyy; the manifest declares the format (D23)',
        'MF_03 arrives in 10 parts (a to g4) and MF_05 in 7 parts (h1a to h6) for the SQL Developer 100 MB grid limit (spec v4 6.4); keys do not overlap across parts; the counts are in the CHECK files, which are kept in the folder and never landed',
        'MF_03: 63,071 of 298,259 rows carry DELETE_FLAG = Y; they are landed as received and every reader leaves them out',
        'Mega Farm ledger (MF_03) and loan book runs (MF_05): the accounts_in_master and month_end_rows gates do not test Mega Farm accounts, because GLs 1050301 to 1050307 are not in LandingZoneReader::LOAN_GLS; the pack 4 results review of 12 Oct 2026 tied every part to its control count',
        'P2_13 re-run (RUN 2) has no NEW_AC_NUMBER column: it is the same 200 LOS_REQ_IDs as the 8 Oct file with the payloads rendered as text; it versions the 8 Oct rows (the replacement the 7 Oct manifest anticipated), and the account stays on the superseded version',
        'TB_03: the stored trial balance is out of balance by 88,000.00 on GL 6730 from 2026-08-01 (pack 4 and 5 reviews); landed as received, not used as the trial balance until Finance answers',
        'P2_07 full re-pull (10,064 lines) contains the 3,651 lines of 7 Oct and the 9,855 Mega Farm lines of MF_08 with identical content',
    ],
    'not_landed': [
        {'received_as': R + 'CHECK_MF_03_row_counts.csv, CHECK_MF_05_row_counts.csv, CHECK_RS_02_row_counts.csv', 'reason': 'check files (row counts per part), never landed (spec v4 6.4); kept in the folder'},
        {'received_as': R + '* (1).csv', 'reason': 'byte-identical second downloads of 18 files (same SHA-256)'},
    ],
    'classification': 'MAIIC client data: borrower names and balances. Private repository; access as for the production database.',
}
json.dump({'header': header, 'files': files}, open(sys.argv[1], 'w', encoding='utf-8'), indent=1)
print(len(files))
