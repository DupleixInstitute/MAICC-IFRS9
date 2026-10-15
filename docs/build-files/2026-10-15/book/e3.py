p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\LoanBooks\Index.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)
rep('''                                <th>Stage</th>
                                <th class="num" title="Days past due">DPD</th>
                                <th title="Reserve Bank of Malawi classification by days past due and term">RBM class</th>
                                <th class="num">Carrying (MWK)</th>
                                <th class="num">Undrawn (MWK)</th>''', '''                                <th title="IFRS 9 stage, and the Reserve Bank of Malawi class by days past due and term">Stage / RBM class</th>
                                <th class="num" title="Days past due">DPD</th>
                                <th class="num" title="Carrying amount, with the undrawn commitment below it">Carrying / undrawn (MWK)</th>''')
rep('''                                    <td class="whitespace-nowrap"><span :class="['maiic-badge', stageBadge(stageOf(loan))]">Stage {{ stageOf(loan) }}</span></td>
                                    <td class="num"><span :class="getOverdueClass(loan.overdue_days)">{{ Number(loan.overdue_days || 0).toLocaleString() }}</span></td>
                                    <td class="whitespace-nowrap"><span :class="['maiic-badge', rbmBadge(loan.lineage.rbm_class)]">{{ loan.lineage.rbm_class }}</span></td>
                                    <td class="num">{{ formatMoney(loan.lineage.carrying) }}</td>
                                    <td class="num">{{ loan.lineage.undrawn ? formatMoney(loan.lineage.undrawn) : '-' }}</td>''', '''                                    <td class="whitespace-nowrap">
                                        <span :class="['maiic-badge', stageBadge(stageOf(loan))]">Stage {{ stageOf(loan) }}</span>
                                        <div class="mt-0.5 text-[11px] font-semibold" :class="rbmText(loan.lineage.rbm_class)">{{ loan.lineage.rbm_class }}</div>
                                    </td>
                                    <td class="num"><span :class="getOverdueClass(loan.overdue_days)">{{ Number(loan.overdue_days || 0).toLocaleString() }}</span></td>
                                    <td class="num">
                                        <div>{{ formatMoney(loan.lineage.carrying) }}</div>
                                        <div v-if="loan.lineage.undrawn" class="text-[11px] text-gray-500" title="Undrawn commitment">+ {{ formatMoney(loan.lineage.undrawn) }} undrawn</div>
                                    </td>''')
s = s.replace('colspan="12"', 'colspan="10"')
rep('''const rbmBadge = (c) => c === 'Pass' ? 'maiic-badge-green' : c === 'Special mention' ? 'maiic-badge-gold' : 'maiic-badge-red';''',
    '''const rbmText = (c) => c === 'Pass' ? 'text-maiic-700' : c === 'Special mention' ? 'text-amber-700' : 'text-red-600';''')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
