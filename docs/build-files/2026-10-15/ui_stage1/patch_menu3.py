p='C:/xampp/htdocs/MAICC-IFRS9/config/menu.php'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
reports_cur = """        // The hub lists every report, reconciliation and export; the screens
        // it opens light this entry up.
        ['color' => '#F6B131', 'heading' => 'Risk & regulatory', 'match' => [
            'ifrs9-reports.*', 'reports.*', 'stress-testing.*', 'rbm-return.*', 'auditor-pack.*',
        ]] + $leaf('Reports', 'ifrs9-reports.index', 'chart-bar'),

"""
rep(reports_cur, "")
ws = """        ['color' => '#86EFAC', 'heading' => 'Overview'] + $leaf('Workspace', 'workspace.index', 'tasks'),

"""
rep(ws, ws + reports_cur.replace("'heading' => 'Risk & regulatory'", "'heading' => 'Overview'"))
open(p,'w',encoding='utf-8',newline='').write(s)

t='C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/NavigationTest.php'
s=open(t,encoding='utf-8').read()
rep("""['Dashboard', 'Workspace', 'Portfolio Setup', 'Customer & Loan Data', 'Collateral Management', 'EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'Forward-Looking Information', 'ECL Processing', 'Reports', 'Governance Centre', 'System Documentation', 'Administration']""",
    """['Dashboard', 'Workspace', 'Reports', 'Portfolio Setup', 'Customer & Loan Data', 'Collateral Management', 'EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'Forward-Looking Information', 'ECL Processing', 'Governance Centre', 'System Documentation', 'Administration']""")
open(t,'w',encoding='utf-8',newline='').write(s)
print('ok')
