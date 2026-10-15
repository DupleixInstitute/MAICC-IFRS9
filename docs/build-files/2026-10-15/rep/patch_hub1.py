import sys
p = sys.argv[1]
lines = open(p, encoding='utf-8').read().split('\n')
single = {955, 994, 1094, 1209}
n = 0
for i, l in enumerate(lines, start=1):
    if 'COALESCE(ecl_value,0)' not in l:
        continue
    q = "'" if i in single else '"'
    lines[i - 1] = l.replace('COALESCE(ecl_value,0)', q + ' . self::ECL_SQL . ' + q)
    n += l.count('COALESCE(ecl_value,0)')
s = '\n'.join(lines)
old = "    private const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';"
assert old in s
s = s.replace(old, old + """

    // The loss allowance every report carries is the booked allowance: discounted at the
    // original EIR where the engine discounted the loan, the programme measure for Mega Farm,
    // else the undiscounted ECL, which the report's basis section counts and names
    // (BookedEcl; built 14 October 2026, the reports read the undiscounted ecl_value until then).
    private const ECL_SQL = \\App\\Services\\Reports\\BookedEcl::SQL;""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('replaced', n)
