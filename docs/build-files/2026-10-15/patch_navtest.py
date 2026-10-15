p = r'c:\xampp\htdocs\MAICC-IFRS9\tests\Feature\NavigationTest.php'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, old[:70]
    s = s.replace(old, new)
rep(""" * The contract-aligned navigation, restored on 9 October 2026: every leaf
 * names a registered route, the groups keep their contract order, the new
 * screens sit in those groups, and the report-hub tiles are not repeated
 * as menu items.""", """ * The contract-aligned navigation, restored on 9 October 2026 and
 * consolidated into tabbed sections: every leaf and every tab names a
 * registered route, the entries keep their contract order, the new screens
 * sit in those entries, and the report-hub tiles are not menu items.""")
rep("""            if (empty($item['dropdown']) && ! empty($item['route']) && ! Route::has($item['route'])) {
                $missing[] = $item['name'] . ' -> ' . $item['route'];
            }""", """            if (empty($item['dropdown']) && ! empty($item['route']) && ! Route::has($item['route'])) {
                $missing[] = $item['name'] . ' -> ' . $item['route'];
            }
            foreach ($item['tabs'] ?? [] as $tab) {
                if (! Route::has($tab['route'])) {
                    $missing[] = $item['name'] . ' / ' . $tab['name'] . ' -> ' . $tab['route'];
                }
            }""")
rep("""    public function test_the_contract_groups_are_in_order(): void
    {
        $groups = array_values(array_map(fn ($g) => $g['name'], array_filter(config('menu.admin'), fn ($g) => ! empty($g['dropdown']))));
        $this->assertSame(['Reports', 'Portfolio Setup', 'Customer & Loan Data', 'Collateral Management', 'EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'ECL Processing', 'System Documentation', 'Administration'], $groups);
    }""", """    public function test_the_contract_entries_are_in_order(): void
    {
        $entries = array_map(fn ($g) => $g['name'], config('menu.admin'));
        $this->assertSame(['Dashboard', 'Workspace', 'Reports', 'Portfolio Setup', 'Customer & Loan Data', 'Collateral Management', 'EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'ECL Processing', 'System Documentation', 'Administration'], $entries);
    }

    public function test_reports_is_one_entry_to_the_hub(): void
    {
        $reports = collect(config('menu.admin'))->firstWhere('name', 'Reports');
        $this->assertSame('ifrs9-reports.index', $reports['route']);
        $this->assertTrue(empty($reports['dropdown']));
    }""")
rep("""                if (! empty($item['route'])) {
                    $where[$item['route']] = $group['name'];
                }""", """                if (! empty($item['route'])) {
                    $where[$item['route']] = $group['name'];
                }
                foreach ($item['tabs'] ?? [] as $tab) {
                    $where[$tab['route']] = $group['name'];
                }""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
