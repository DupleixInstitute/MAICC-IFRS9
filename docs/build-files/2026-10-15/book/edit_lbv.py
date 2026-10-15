import re
base = r'C:\Users\DELL\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\c9a0f915-2064-4f18-9b98-77857ef71421\scratchpad\book'
p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\LoanBooks\Index.vue'
s = open(p, encoding='utf-8').read()
top = open(base + r'\lb_top.vue', encoding='utf-8').read()
i = s.index('    <!-- Export summary -->')
s = top + s[i:]

def rep(old, new, count=1):
    global s
    assert old in s, old
    s = s.replace(old, new, count)

rep("""import { ref } from 'vue';""", """import { reactive, ref } from 'vue';""")
rep("""import HelpManual from '@/Components/HelpManual.vue';""", """import HelpManual from '@/Components/HelpManual.vue';
import LoanLineage from '@/Components/Ifrs9/LoanLineage.vue';""")
rep("""    periods: { type: Array, default: () => [] },
});""", """    periods: { type: Array, default: () => [] },
    productGroups: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
});

// Rows opened to show how their ECL was built.
const open = reactive({});
const toggle = (id) => { open[id] = !open[id]; };""")
rep("""    stage: '',
    search: '',
    ...props.filters""", """    stage: '',
    search: '',
    product_group: '',
    sector: '',
    ...props.filters""")
# both fetch calls: add product group + sector
rep("""            stage: filters.value.stage || undefined
        }, {""", """            stage: filters.value.stage || undefined,
            product_group: filters.value.product_group || undefined,
            sector: filters.value.sector || undefined,
        }, {""")
rep("""            stage: filters.value.stage || undefined
        }));""", """            stage: filters.value.stage || undefined,
            product_group: filters.value.product_group || undefined,
            sector: filters.value.sector || undefined,
        }));""")
# replace helpers after formatRate
i = s.index('// Final IFRS 9 stage: post-qualitative wins')
j = s.index('</script>')
s = s[:i] + """// Final IFRS 9 stage: post-qualitative wins, then calculated, then imported.
const stageOf = (loan) => {
    return Number(loan.ifrs9stage_post_qualitative ?? loan.calculated_ifrs9_stage ?? loan.ifrs9_stage ?? 1);
};
const stageBadge = (s) => s === 3 ? 'maiic-badge-red' : s === 2 ? 'maiic-badge-gold' : 'maiic-badge-green';
// RBM classes: performing green, special mention gold, non-performing red.
const rbmBadge = (c) => c === 'Pass' ? 'maiic-badge-green' : c === 'Special mention' ? 'maiic-badge-gold' : 'maiic-badge-red';
const customerOf = (loan) => loan.client?.name || loan.customer_name || loan.external_identity_id || '-';

// Large amounts on the figure strip in millions / billions; the full figure is the tooltip.
const compact = (value) => {
    const v = Number(value || 0), a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toFixed(2) + ' bn';
    if (a >= 1e6) return (v / 1e6).toFixed(2) + ' m';
    return formatMoney(v);
};

const getOverdueClass = (days) => {
    if (!days) return 'text-maiic-600';
    if (days <= 30) return 'text-amber-600';
    return 'text-red-600 font-semibold';
};

""" + s[j:]
rep("""const formatCurrency = (value) => {
    return formatMoney(value);
};

""", "")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
