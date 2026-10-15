p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\LoanBooks\Index.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)
rep('description="What the book holds at a month-end: each loan\'s exposure, stage, arrears, locked EIR and ECL. Open a loan to see how its ECL was built."',
    'description="Each loan at a month-end: exposure, stage, arrears, locked EIR and ECL. Open a loan to see how its ECL was built."')
rep('<div class="maiic-kpi-label">Carrying amount (MWK)</div>', '<div class="maiic-kpi-label">Carrying (MWK)</div>')
rep('<table class="maiic-table [&_td]:px-3 [&_th]:px-3">', '<table class="maiic-table text-[13px] [&_td]:!px-2.5 [&_th]:!px-2.5">')
rep('''                                <th>Product group</th>
                                <th>Sector</th>''', '''                                <th>Product group / sector</th>''')
rep('''                                <th class="num">Coverage</th>
                                <th class="!text-center">Trace</th>''', '''                                <th class="num">Coverage</th>''')
rep('''                                    <td class="max-w-[11rem] truncate text-xs" :title="loan.product_group">{{ loan.product_group || '-' }}</td>
                                    <td class="max-w-[11rem] truncate text-xs" :title="loan.sector">{{ loan.sector }}</td>''',
'''                                    <td class="max-w-[13rem]">
                                        <div class="truncate text-xs text-gray-800" :title="loan.product_group">{{ loan.product_group || '-' }}</div>
                                        <div class="truncate text-xs text-gray-500" :title="loan.sector">{{ loan.sector }}</div>
                                    </td>''')
i = s.index('                                    <td class="text-center" @click.stop>')
j = s.index('</td>', s.index('</Link>', i)) + len('</td>\n')
s = s[:i] + s[j:]
s = s.replace('colspan="14"', 'colspan="12"')
rep('<div class="max-w-[14rem] truncate text-xs text-gray-500"', '<div class="max-w-[12rem] truncate text-xs text-gray-500"')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
