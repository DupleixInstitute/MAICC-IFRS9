p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\Eir\Restructures\Index.vue'
s = open(p, encoding='utf-8').read()
crlf = '\r\n' in s
s = s.replace('\r\n', '\n')
reps = [
    ('<label class="maiic-flabel" for="rr-status">Status</label>', '<label class="maiic-flabel" for="rr-status">Classification</label>'),
    ("""<p class="mt-1 text-xs text-gray-500">Every month whose loan-book rate differs from the month before, on a loan of the EIR engine, with the PLR at both month-ends and the spread. The EIR already follows the rate charged each month on a floating loan (decision D38, IFRS 9 B5.4.5, no catch-up); this register records each move and its classification. In-contract: a move the contract provides for. Negotiated: a re-pricing agreed outside it, which becomes a restructure candidate.</p>""",
     """<p class="mt-1 text-xs text-gray-500">Each prime-rate reset of a floating loan, found once from the PLR series on the spread locked at drawdown, set against the rate the loan book shows E-Banker charged; and each change of the spread, as its own event even on the date of a PLR reset. A move the reset pipeline cannot take (the loan is held there, or the engine treats it as fixed-rate) is still recorded, marked held, with the reason. The EIR already follows the rate charged each month on a floating loan (decision D38, IFRS 9 B5.4.5, no catch-up); this register records each move and its classification. In-contract: a move the contract provides for. Negotiated: a re-pricing agreed outside it, which becomes a restructure candidate.</p>"""),
    ("""<input v-if="r.status !== 'APPROVED'" v-model="selected\"""", """<input v-if="r.classification_status !== 'APPROVED'" v-model="selected\""""),
    ("""<span class="maiic-badge" :class="resetBadge(r.status)">{{ resetLabel(r.status) }}</span>""",
     """<span class="maiic-badge" :class="resetBadge(r.classification_status)">{{ resetLabel(r.classification_status) }}</span>"""),
    ("""<div v-if="r.close_reason && r.status === 'DETECTED'" class="text-gray-500">{{ r.close_reason }}</div>""",
     """<div v-if="r.close_reason && r.classification_status === 'DETECTED'" class="text-gray-500">{{ r.close_reason }}</div>
                    <div class="mt-1 text-gray-500 dark:text-slate-400" :title="r.note || ''">Reset pipeline: {{ pipelineLabel(r) }}<template v-if="r.new_schedule_version">, schedule version {{ r.new_schedule_version }}</template></div>
                    <div v-if="r.status === 'BLOCKED' && r.note" class="text-amber-700 dark:text-amber-300">{{ r.note }}</div>"""),
    ("""<div v-if="canGovern && r.status === 'PROPOSED'" class="flex justify-end gap-1.5">""",
     """<div v-if="canGovern && r.classification_status === 'PROPOSED'" class="flex justify-end gap-1.5">"""),
    ("""(!resetFilters.status || r.status === resetFilters.status)""", """(!resetFilters.status || r.classification_status === resetFilters.status)"""),
    ("""const allOnPage = computed(() => pagedResets.value.filter(r => r.status !== 'APPROVED').length > 0 && pagedResets.value.filter(r => r.status !== 'APPROVED').every(r => selected.value.includes(r.id)))""",
     """const allOnPage = computed(() => pagedResets.value.filter(r => r.classification_status !== 'APPROVED').length > 0 && pagedResets.value.filter(r => r.classification_status !== 'APPROVED').every(r => selected.value.includes(r.id)))"""),
    ("""  const ids = pagedResets.value.filter(r => r.status !== 'APPROVED').map(r => r.id)""",
     """  const ids = pagedResets.value.filter(r => r.classification_status !== 'APPROVED').map(r => r.id)"""),
    ("""const resetBadge = (s) => ({ DETECTED: 'maiic-badge-grey', PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green' }[s] || 'maiic-badge-grey')""",
     """const resetBadge = (s) => ({ DETECTED: 'maiic-badge-grey', PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green' }[s] || 'maiic-badge-grey')
// the reset pipeline's own state (status), beside the classification (classification_status)
const pipelineLabel = (r) => r.event_type === 'SPREAD_CHANGE' ? 'not scheduled (a spread change is classified here)'
  : ({ DETECTED: 'detected', SCHEDULED: 'Reset schedule built', SOLVED: 'solved', BLOCKED: 'held' }[r.status] || r.status || '-')"""),
]
for old, new in reps:
    if old not in s:
        raise SystemExit('missing: ' + old[:90])
    s = s.replace(old, new, 1)
# the selection helpers read the classification too
for old, new in [
    ("""const selectedDetected = computed(() => selected.value.filter(id => byId.value[id]?.status === 'DETECTED'))""",
     """const selectedDetected = computed(() => selected.value.filter(id => byId.value[id]?.classification_status === 'DETECTED'))"""),
    ("""const selectedApprovable = computed(() => selected.value.filter(id => byId.value[id]?.status === 'PROPOSED' && byId.value[id]?.proposed_by !== props.me))""",
     """const selectedApprovable = computed(() => selected.value.filter(id => byId.value[id]?.classification_status === 'PROPOSED' && byId.value[id]?.proposed_by !== props.me))"""),
]:
    if old not in s:
        raise SystemExit('missing: ' + old[:90])
    s = s.replace(old, new, 1)
if crlf:
    s = s.replace('\n', '\r\n')
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok crlf=', crlf)
