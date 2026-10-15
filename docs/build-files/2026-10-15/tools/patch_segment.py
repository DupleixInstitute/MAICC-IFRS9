import re
R = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/'

def register(s):
    # add the import and the component registration
    m = re.search(r"import AppLayout from '@/Layouts/AppLayout.vue';?\n", s)
    assert m, 'AppLayout import'
    s = s[:m.end()] + "import SegmentCell from '@/Components/Maiic/SegmentCell.vue';\n" + s[m.end():]
    m = re.search(r'components\s*:\s*\{', s)
    assert m, 'components'
    s = s[:m.end()] + '\n        SegmentCell,' + s[m.end():]
    return s

for f, v, lvl, idf, codef, rel in [
    ('TransitionMatrix/Index.vue', 'matrix', 'pd_calculation_level', 'pd_calculation_id', 'pd_calculation_code', 'portfolio'),
    ('TransitionMatrix/Cummulative.vue', 'matrix', 'pd_calculation_level', 'pd_calculation_id', 'pd_calculation_code', 'portfolio'),
    ('LossGivenDefault/Cummulative.vue', 'lgdC', 'lgd_calculation_level', 'lgd_calculation_id', 'lgd_calculation_code', 'portfolio_group'),
]:
    p = R + f
    s = open(p, encoding='utf-8').read()
    pat = re.compile(r'<td>\s*<span class="maiic-badge maiic-badge-grey">\{\{ ' + v + r'\.' + lvl + r' \?.*?</div>\s*</td>', re.S)
    m = pat.search(s)
    assert m, f
    cell = (f'<td class="min-w-[11rem]"><SegmentCell :level="{v}.{lvl}" '
            f':name="{v}.{lvl} === \'portfolio\' ? ({v}.{rel}?.name || \'\') : ({v}.{lvl} === \'sector\' ? ({v}.sector?.name || \'\') : \'\')" '
            f':code="{v}.{lvl} === \'sector\' ? ({v}.sector?.code || {v}.{codef}) : {v}.{idf}"/></td>')
    s = s[:m.start()] + cell + s[m.end():]
    s = register(s)
    open(p, 'w', encoding='utf-8').write(s)
    print('ok', f)

p = R + 'LossGivenDefault/Index.vue'
s = open(p, encoding='utf-8').read()
a = "<td>{{ lgd.portfolio_group?.name || '-' }}</td>"
assert a in s
s = s.replace(a, """<td class="min-w-[11rem]"><SegmentCell :level="lgd.portfolio_group ? 'portfolio' : 'book'" :name="lgd.portfolio_group?.name || ''"/></td>""")
s = s.replace('                <th>Portfolio</th>', '                <th>Segment</th>', 1)
s = register(s)
open(p, 'w', encoding='utf-8').write(s)
print('ok lgd')
