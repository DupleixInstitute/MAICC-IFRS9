import io
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\MegaFarm\MegaFarmEclService.php'
s = io.open(p, encoding='utf-8', newline='').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:80], s.count(old))
    s = s.replace(old, new)
rep("""        $loans = $this->loans($period)->get(['id', 'contract_id', 'product_group', 'ifrs9stage_post_qualitative', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'lgd_value', 'collection_lgd', 'pd_prefli']);""",
"""        $loans = $this->loans($period)->get(array_merge(['id', 'contract_id', 'product_group', 'ifrs9stage_post_qualitative', 'carrying_amount', 'commitments', 'facility_utilisation_rate', 'lgd_value', 'collection_lgd', 'pd_prefli'],
            \App\Services\Ecl\EclExposure::hasBasis() ? ['build_basis'] : []));""")
rep("""                // a credit balance is owed to the farmer, not an exposure (spec 3.8): no negative allowance
                $ead = max(0.0, (float) $l->carrying_amount + (float) $l->commitments * (float) ($l->facility_utilisation_rate ?? 1));""",
"""                // the engine's EAD (EclExposure): a credit balance is owed to the farmer, not an exposure
                // (spec 3.8), so no negative allowance; a loan written off to nil has no exposure at all
                $ead = \App\Services\Ecl\EclExposure::of($l);""")
io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
