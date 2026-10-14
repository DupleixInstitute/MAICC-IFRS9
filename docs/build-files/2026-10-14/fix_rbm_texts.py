import io, os, re
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def edit(path, fn):
    s = io.open(path, encoding='utf-8').read(); t = fn(s); assert t != s, path
    io.open(path, 'w', encoding='utf-8', newline='\n').write(t)

def wb(s):
    s = s.replace("apply the doubtful and loss bands by term (181/361 days short-term, 361/721 otherwise)",
                  "apply the doubtful and loss bands by term from section 10 of the Gazette (181/366 days short-term, 366/747 otherwise)")
    s = s.replace("Doubtful from 181 (short-term) or 361 days (medium and long term); loss from 361 or 721 days, or where recovery is not expected.",
                  "Doubtful from 181 (short-term) or 366 days (medium and long term); loss beyond 365 or 746 days, or where recovery is not expected.")
    # F3: record the second correction honestly
    s = re.sub(r'\("F3", "9 to 11", .*?\),\n',
        '("F3", "9 to 11", "The RBM Classification report\'s day bands differed from the directive\'s", "The older RBM Classification report classified every facility on one ladder (90, 180, 365 days) with 1 percent on pass and special mention. Resolved in two steps: on 9 October 2026 the report, the provision comparison and the arrears ageing were put on one rule with the RBM Return; the system audit of the same day then found that rule itself carried wrong medium- and long-term bands (30/180/360/720, from a note carried between sessions) and both were corrected to the Gazette\'s section 10: short-term 30/90/180/365, medium and long 90/180/365/746, with a test at every boundary on both terms.", "None remaining: both screens classify by the Gazette\'s bands.", "Done.", "Dupleix", "Closed"),\n',
        s, count=1, flags=re.S)
    return s
edit('tools/compliance/standards/rbm_dfi_directive.py', wb)

def rep(s):
    s = s.replace("A the facilities by class and term under the directive's day bands (90/180/360 short-term; 180/360/720 medium and long)",
                  "A the facilities by class and term under the directive's day bands (section 10 of the Gazette: 30/90/180/365 short-term; 90/180/365/746 medium and long)")
    s = s.replace("The older RBM Classification report, the provision comparison and the arrears ageing now classify by the same bands by term and rates (they had used one 90/180/365 ladder at 1 percent on pass and special mention), and a test proves the report and the return agree at every band edge on both terms (workbook 4 finding F3, closed)",
                  "The older RBM Classification report, the provision comparison and the arrears ageing now classify by the same bands by term and rates (they had used one 90/180/365 ladder at 1 percent on pass and special mention). The system audit of 9 October found the shared rule itself carried wrong medium- and long-term bands (30/180/360/720); both were corrected to the Gazette, and a test proves the report and the return agree at every band edge on both terms (workbook 4 finding F3, closed)")
    return s
edit('docs/After_Build_Report_2026-10-08.md', rep)
print('ok')
