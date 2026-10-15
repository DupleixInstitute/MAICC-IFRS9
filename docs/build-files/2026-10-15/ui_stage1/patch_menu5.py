p='C:/xampp/htdocs/MAICC-IFRS9/config/menu.php'
s=open(p,encoding='utf-8').read()
start=s.index("        // Administration & support, in three small sub-blocks ('subheading',")
end=s.index("        ], 8),\n", start)+len("        ], 8),\n")
new="""        // Administration & support: three groups, each folding like the
        // other entries. The entries are the ones the old Administration group
        // held, with the same routes and permissions.
        // Governance: the maker-checker centre for the governed settings (EIR
        // and FLI), and the audit record.
        ['color' => '#C4B5FD', 'heading' => 'Administration & support'] + $group('Governance', 'shield-alt', [
            $leaf('Governance Centre', 'eir-governance.index', 'shield-alt', permission: 'eir.govern'),
            $section('Audit', [
                $tab('Audit Trail', 'audit-trail.index'),
                $tab('Audit Trace', 'audit-trace.index'),
                $tab('Compliance Audits', 'compliance-audits.index'),
            ], 'history'),
        ], 9),

        // Access & setup: who may use the system, its periods and settings.
        ['color' => '#C4B5FD', 'heading' => 'Administration & support'] + $group('Access & Setup', 'user-lock', [
            $section('Users & Roles', [
                $tab('Users', 'users.index'),
                $tab('Roles & Permissions', 'users.roles.index'),
            ], 'user-lock'),
            $leaf('Financial Periods', 'accounting.financial_periods.index', 'calendar'),
            $leaf('Settings', 'settings.index', 'cog'),
        ], 10),

        // Support & maintenance. System Documentation is Contract Schedule 1
        // deliverables 5, 6 and 7: the two manuals are database content (help
        // centre); the technical manual and the installation guide are
        // repository Markdown under docs/manuals.
        ['color' => '#C4B5FD', 'heading' => 'Administration & support'] + $group('Support & Maintenance', 'wrench', [
            $leaf('Support Tickets', 'tickets.index', 'ticket-alt'),
            $leaf('Maintenance', 'maintenance.index', 'wrench', permission: 'settings'),
            $group('System Documentation', 'book-open', [
                $leaf('User Manual', 'help.index'),
                $leaf('Administrator Manual', 'help.admin'),
                $leaf('Technical Manual', 'docs.technical'),
                $leaf('Installation Guide', 'docs.installation'),
            ], 8),
        ], 11),
"""
s=s[:start]+new+s[end:]
old="""
| 'subheading' splits a block into smaller, unclickable sub-blocks the
| same way (Administration & support: Governance, Access & setup, Support &
| maintenance)."""
assert s.count(old)==1
s=s.replace(old,"")
open(p,'w',encoding='utf-8',newline='').write(s)

t='C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/NavigationTest.php'
s=open(t,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep("""'ECL Processing', 'Governance Centre', 'Audit', 'Users & Roles', 'Financial Periods', 'Settings', 'Support Tickets', 'Maintenance', 'System Documentation'], $entries);""",
    """'ECL Processing', 'Governance', 'Access & Setup', 'Support & Maintenance'], $entries);""")
rep("""        $this->assertSame('Governance Centre', $where['eir-governance.index']);""","""        $this->assertSame('Governance', $where['eir-governance.index']);""")
rep("""        $this->assertSame('Financial Periods', $where['accounting.financial_periods.index']);""","""        $this->assertSame('Access & Setup', $where['accounting.financial_periods.index']);
        $this->assertSame('Support & Maintenance', $where['help.index']);""")
rep("""['Administration & support', 'Governance Centre'], array_column(""","""['Administration & support', 'Governance', 'Governance Centre'], array_column(""")
rep("""['Administration & support', 'Audit', 'Audit Trail'], array_column(""","""['Administration & support', 'Governance', 'Audit', 'Audit Trail'], array_column(""")
rep("""$this->assertSame(['Administration & support', 'Users & Roles', 'Users'], array_column($edit['crumbs'], 'label'));""","""$this->assertSame(['Administration & support', 'Access & Setup', 'Users & Roles', 'Users'], array_column($edit['crumbs'], 'label'));""")
open(t,'w',encoding='utf-8',newline='').write(s)
print('ok')
