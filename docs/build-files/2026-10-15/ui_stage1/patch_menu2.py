p='C:/xampp/htdocs/MAICC-IFRS9/config/menu.php'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)

reports_old = """        // The hub lists every report, reconciliation and export; the screens
        // it opens light this entry up.
        ['color' => '#F6B131', 'heading' => 'Overview', 'match' => [
            'ifrs9-reports.*', 'reports.*', 'stress-testing.*', 'rbm-return.*', 'auditor-pack.*',
        ]] + $leaf('Reports', 'ifrs9-reports.index', 'chart-bar'),

"""
rep(reports_old, "")
reports_new = """        // The hub lists every report, reconciliation and export; the screens
        // it opens light this entry up.
        ['color' => '#F6B131', 'heading' => 'Risk & regulatory', 'match' => [
            'ifrs9-reports.*', 'reports.*', 'stress-testing.*', 'rbm-return.*', 'auditor-pack.*',
        ]] + $leaf('Reports', 'ifrs9-reports.index', 'chart-bar'),

"""
# headings, Stress Testing suite grouping adapted for MAIIC (13 Oct 2026)
s = s.replace("'heading' => 'Data management'", "'heading' => 'Data foundation'")
for name in ['EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'Forward-Looking Information']:
    rep("'heading' => 'Modelling & calculations'] + $group('" + name + "'", "'heading' => 'Financial modelling'] + $group('" + name + "'")
rep("'heading' => 'Modelling & calculations'] + $group('ECL Processing'", "'heading' => 'Risk & regulatory'] + $group('ECL Processing'")
s = s.replace("'heading' => 'Reporting & governance'", "'heading' => 'Administration & support'")

# Reports follows ECL Processing
ecl_end = """            $leaf('Mega Farm Programme', 'megafarm.index'),
        ], 7),

"""
rep(ecl_end, ecl_end + reports_new)

# Governance Centre: out of EIR & Revenue Recognition, its own entry at the
# head of Administration & support (route and permission unchanged)
rep("""            $leaf('Governance Centre', 'eir-governance.index', permission: 'eir.govern'),
        ], 4),""", """        ], 4),""")
docs = """        // Contract Schedule 1 deliverables 5, 6 and 7."""
rep(docs, """        // The maker-checker centre for the governed settings (EIR and FLI).
        ['color' => '#A3E635', 'heading' => 'Administration & support'] + $leaf('Governance Centre', 'eir-governance.index', 'shield-alt', permission: 'eir.govern'),

""" + docs)

rep("""| 'heading' names the block of the sidebar the entry sits in (Overview,
| Data management, Modelling & calculations, Reporting & governance); the""", """| 'heading' names the block of the sidebar the entry sits in (Overview,
| Data foundation, Financial modelling, Risk & regulatory, Administration &
| support: the Stress Testing suite's grouping, adapted for MAIIC); the""")
assert 'Modelling & calculations' not in s and 'Data management' not in s and 'Reporting & governance' not in s
open(p,'w',encoding='utf-8',newline='').write(s)
print('ok')
