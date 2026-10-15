p = r'C:\xampp\htdocs\MAICC-IFRS9\database\seeders\data\help_user_content\06-eir-and-revenue-recognition.php'
s = open(p, encoding='utf-8', newline='').read()
reps = [
    ("""                . '<li>Filters: <b>Status</b> (<code>All</code>, <code>Awaiting a classification</code>, <code>Proposed</code>, <code>Approved</code>), <b>Type</b> (<code>Moved with the PLR</code>, <code>Spread changed</code>) and <b>Loan</b>.</li>'""",
     """                . '<li>Filters: <b>Classification</b> (<code>All</code>, <code>Awaiting a classification</code>, <code>Proposed</code>, <code>Approved</code>), <b>Type</b> (<code>Moved with the PLR</code>, <code>Spread changed</code>) and <b>Loan</b>.</li>'"""),
    ("""                . '<li>Every month whose loan-book rate differs from the month before. Columns:""",
     """                . '<li>Each prime-rate reset of a floating loan, found once from the PLR series on the spread locked at drawdown and set against the rate the loan book shows E-Banker charged, and each change of the spread as its own row (a PLR reset and a spread change in the same month are two rows on one date, each classified on its own). A move with the PLR that the reset pipeline cannot take (the loan is held there, or the engine treats it as fixed-rate) is still listed, marked held, with the reason. Columns:"""),
    ("""and "Restructure candidate #n" for a negotiated move) and <b>Actions</b>.</li></ul>'""",
     """and "Restructure candidate #n" for a negotiated move; under it "Reset pipeline:" detected, Reset schedule built (with the schedule version), held (with the reason) or, for a spread change, not scheduled) and <b>Actions</b>. Neither the classification nor the pipeline moves the EIR: a floating loan\\'s EIR follows the rate charged each month (decision D38).</li></ul>'"""),
]
for old, new in reps:
    if old not in s:
        raise SystemExit('missing: ' + old[:80])
    s = s.replace(old, new, 1)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
