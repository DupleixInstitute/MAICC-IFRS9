p='C:/xampp/htdocs/MAICC-IFRS9/config/menu.php'
s=open(p,encoding='utf-8').read()
start = s.index("        // The maker-checker centre for the governed settings (EIR and FLI).")
end = s.index("        ], 9),\n", start) + len("        ], 9),\n")
new = """        // Administration & support, in three small sub-blocks ('subheading',
        // printed by the sidebar, not clickable). The entries are the ones the
        // Administration group held, with the same routes and permissions.
        // Governance: the maker-checker centre for the governed settings (EIR
        // and FLI), and the audit record.
        ['color' => '#A3E635', 'heading' => 'Administration & support', 'subheading' => 'Governance'] + $leaf('Governance Centre', 'eir-governance.index', 'shield-alt', permission: 'eir.govern'),
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Governance'] + $section('Audit', [
            $tab('Audit Trail', 'audit-trail.index'),
            $tab('Audit Trace', 'audit-trace.index'),
            $tab('Compliance Audits', 'compliance-audits.index'),
        ], 'history'),

        // Access & setup: who may use the system, its periods and settings.
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Access & setup'] + $section('Users & Roles', [
            $tab('Users', 'users.index'),
            $tab('Roles & Permissions', 'users.roles.index'),
        ], 'user-lock'),
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Access & setup'] + $leaf('Financial Periods', 'accounting.financial_periods.index', 'calendar'),
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Access & setup'] + $leaf('Settings', 'settings.index', 'cog'),

        // Support & maintenance. System Documentation is Contract Schedule 1
        // deliverables 5, 6 and 7: the two manuals are database content (help
        // centre); the technical manual and the installation guide are
        // repository Markdown under docs/manuals.
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Support & maintenance'] + $leaf('Support Tickets', 'tickets.index', 'ticket-alt'),
        ['color' => '#C4B5FD', 'heading' => 'Administration & support', 'subheading' => 'Support & maintenance'] + $leaf('Maintenance', 'maintenance.index', 'wrench', permission: 'settings'),
        ['color' => '#67E8F9', 'heading' => 'Administration & support', 'subheading' => 'Support & maintenance'] + $group('System Documentation', 'book-open', [
            $leaf('User Manual', 'help.index'),
            $leaf('Administrator Manual', 'help.admin'),
            $leaf('Technical Manual', 'docs.technical'),
            $leaf('Installation Guide', 'docs.installation'),
        ], 8),
"""
s = s[:start] + new + s[end:]
old_doc = """| It is on every entry, so a block keeps its heading when a permission
| hides its first entry."""
assert s.count(old_doc)==1
s = s.replace(old_doc, old_doc + """
| 'subheading' splits a block into smaller, unclickable sub-blocks the
| same way (Administration & support: Governance, Access & setup, Support &
| maintenance).""")
open(p,'w',encoding='utf-8',newline='').write(s)

t='C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/NavigationTest.php'
s=open(t,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep("""'ECL Processing', 'Governance Centre', 'System Documentation', 'Administration'], $entries);""",
    """'ECL Processing', 'Governance Centre', 'Audit', 'Users & Roles', 'Financial Periods', 'Settings', 'Support Tickets', 'Maintenance', 'System Documentation'], $entries);""")
rep("""        $this->assertSame('Administration', $where['accounting.financial_periods.index']);""",
    """        $this->assertSame('Financial Periods', $where['accounting.financial_periods.index']);""")
rep("""        $admin = collect(config('menu.admin'))->firstWhere('name', 'Administration');
        $this->assertNotContains('Legacy FLI tools', array_column($admin['children'], 'name'));""",
    """        // nothing forward-looking sits in the administration block (it was
        // one Administration group until the split of 13 October 2026)
        $admin = collect(config('menu.admin'))->where('heading', 'Administration & support');
        $this->assertNotEmpty($admin);
        $this->assertNotContains('Legacy FLI tools', $admin->pluck('name')->all());
        $this->walk($admin->all(), fn (array $item) => $this->assertNotSame('Legacy FLI tools', $item['name']));""")
open(t,'w',encoding='utf-8',newline='').write(s)
print('ok')
